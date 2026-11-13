import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

import http from '@/api/http'
import AssistantIATab from '../AssistantIATab.vue'

vi.mock('@/api/http', () => ({
  default: { post: vi.fn(), patch: vi.fn() },
}))

const ESPACE = {
  id: 3,
  nom: 'Bases de données',
  assistant: { actif: true, nb_documents_indexes: 2 },
}

function monter(espace = ESPACE) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/espaces/:id/:onglet?', name: 'espace', component: { template: '<div />' } }],
  })
  return mount(AssistantIATab, {
    props: { espace },
    global: { plugins: [createVuetify({ components, directives }), router] },
  })
}

describe('AssistantIATab', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('propose des questions quand la conversation est vide', () => {
    const wrapper = monter()

    expect(wrapper.text()).toContain('Assistant du cours')
    expect(wrapper.text()).toContain('Résume les notions clés du cours')
  })

  it('affiche la question, puis la réponse avec ses sources et la mention IA', async () => {
    http.post.mockResolvedValue({
      data: {
        conversation_id: 1,
        echange_id: 1,
        reponse: 'Selon le cours :\n- **%FOUND** : une ligne a été lue [1]',
        statut: 'OK',
        sources: [{ n: 1, fichier: 'chap03.pdf', page: 3, ressource_id: 1 }],
        quota_restant: 19,
      },
    })
    const wrapper = monter()

    await wrapper.find('textarea').setValue('Quels sont les attributs ?')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const texte = wrapper.text()
    expect(texte).toContain('Quels sont les attributs ?')
    expect(wrapper.find('li strong').text()).toBe('%FOUND')
    expect(texte).toContain('[1]')
    expect(texte).toContain('chap03.pdf · p. 3')
    expect(texte).toContain('Réponse générée par IA, à vérifier dans les sources.')
    expect(texte).toContain("Questions restantes aujourd'hui : 19 sur 20")
    expect(texte).not.toContain('Résume les notions clés du cours')
  })

  it('affiche « momentanément indisponible » et Réessayer après une erreur 503', async () => {
    http.post.mockRejectedValue(
      Object.assign(new Error('503'), { response: { status: 503, data: {} } }),
    )
    const wrapper = monter()

    await wrapper.find('textarea').setValue('Quels sont les attributs ?')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain("L'assistant est momentanément indisponible.")
    expect(wrapper.text()).toContain('Réessayer')
  })

  // SCRUM-144 : erreurs et états vides.
  it('affiche le message de quota de l’API après une erreur 429, sans Réessayer', async () => {
    const message = 'Vous avez atteint vos 20 questions du jour. Revenez demain à partir de 00:00.'
    http.post.mockRejectedValue(
      Object.assign(new Error('429'), { response: { status: 429, data: { message } } }),
    )
    const wrapper = monter()

    await wrapper.find('textarea').setValue('Quels sont les attributs ?')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Quota atteint')
    expect(wrapper.text()).toContain(message)
    expect(wrapper.text()).not.toContain('Réessayer')
    expect(wrapper.find('textarea').element.disabled).toBe(true)
  })

  it('indique quand le cours ne contient pas la réponse (SANS_SOURCE)', async () => {
    http.post.mockResolvedValue({
      data: {
        conversation_id: 1,
        echange_id: 2,
        reponse: null,
        statut: 'SANS_SOURCE',
        sources: [],
      },
    })
    const wrapper = monter()

    await wrapper.find('textarea').setValue('Quelle est la météo demain ?')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain("Je n'ai pas trouvé de réponse fiable dans les ressources")
  })

  it('affiche un état vide quand aucun support n’est indexé', () => {
    const wrapper = monter({ ...ESPACE, assistant: { actif: true, nb_documents_indexes: 0 } })

    expect(wrapper.text()).toContain('Pas encore de supports de cours')
    expect(wrapper.text()).not.toContain('Résume les notions clés du cours')
    expect(wrapper.find('textarea').element.disabled).toBe(true)
  })
  it('rend les marqueurs [n] cliquables et met la source en évidence (SCRUM-143)', async () => {
    http.post.mockResolvedValue({
      data: {
        conversation_id: 1,
        echange_id: 1,
        reponse: 'Le curseur nomme une zone [1]. Les attributs sont listés [2]. Voir aussi [9].',
        statut: 'OK',
        sources: [
          { n: 1, fichier: 'chap03.pdf', page: 1, ressource_id: 4 },
          { n: 2, fichier: 'chap03.pdf', page: 3, ressource_id: 4 },
        ],
        quota_restant: 18,
      },
    })
    const wrapper = monter()

    await wrapper.find('textarea').setValue('Que fait un curseur ?')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const marqueurs = wrapper.findAll('button.citation')
    // [9] n'a pas de source : il reste du texte simple.
    expect(marqueurs.map((m) => m.text())).toEqual(['[1]', '[2]'])
    expect(wrapper.text()).toContain('Voir aussi [9].')

    const chip = () => wrapper.find('#source-0-2')
    expect(chip().classes()).toContain('v-chip--variant-outlined')

    await marqueurs[1].trigger('click')
    await flushPromises()

    expect(marqueurs[1].classes()).toContain('citation--active')
    expect(chip().classes()).toContain('v-chip--variant-flat')
    expect(chip().attributes('href')).toBe('/espaces/3/ressources?ressource=4')
  })
  // SCRUM-146 : avis 👍/👎 sous la réponse.
  it("enregistre l'avis 👍 puis 👎 sur une réponse", async () => {
    http.post.mockResolvedValue({
      data: {
        conversation_id: 1,
        echange_id: 7,
        reponse: 'Les attributs sont %FOUND et %NOTFOUND [1].',
        statut: 'OK',
        sources: [{ n: 1, fichier: 'chap03.pdf', page: 3, ressource_id: 1 }],
        quota_restant: 19,
      },
    })
    http.patch.mockResolvedValue({ status: 204 })
    const wrapper = monter()

    await wrapper.find('textarea').setValue('Quels sont les attributs ?')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Cette réponse vous a-t-elle aidé ?')
    const utile = wrapper.find('[aria-label="Réponse utile"]')
    const pasUtile = wrapper.find('[aria-label="Réponse pas utile"]')

    await utile.trigger('click')
    await flushPromises()
    expect(http.patch).toHaveBeenLastCalledWith('/ia/echanges/7/avis', { utile: true })
    expect(utile.attributes('aria-pressed')).toBe('true')
    expect(wrapper.text()).toContain('Merci pour votre avis.')

    await pasUtile.trigger('click')
    await flushPromises()
    expect(http.patch).toHaveBeenLastCalledWith('/ia/echanges/7/avis', { utile: false })
    expect(pasUtile.attributes('aria-pressed')).toBe('true')
    expect(utile.attributes('aria-pressed')).toBe('false')
  })

  it("prévient si l'avis n'a pas pu être enregistré", async () => {
    http.post.mockResolvedValue({
      data: {
        conversation_id: 1,
        echange_id: 8,
        reponse: null,
        statut: 'SANS_SOURCE',
        sources: [],
      },
    })
    http.patch.mockRejectedValue(new Error('Network Error'))
    const wrapper = monter()

    await wrapper.find('textarea').setValue('Quelle est la météo demain ?')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    await wrapper.find('[aria-label="Réponse pas utile"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain("Votre avis n'a pas pu être enregistré.")
    expect(wrapper.find('[aria-label="Réponse pas utile"]').attributes('aria-pressed')).toBe(
      'false',
    )
  })

  it("ne propose pas d'avis après une erreur", async () => {
    http.post.mockRejectedValue(
      Object.assign(new Error('503'), { response: { status: 503, data: {} } }),
    )
    const wrapper = monter()

    await wrapper.find('textarea').setValue('Quels sont les attributs ?')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[aria-label="Réponse utile"]').exists()).toBe(false)
  })
})
