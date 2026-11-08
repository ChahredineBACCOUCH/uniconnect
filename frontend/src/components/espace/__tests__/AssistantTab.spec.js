import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

import http from '@/api/http'
import AssistantTab from '../AssistantTab.vue'

vi.mock('@/api/http', () => ({
  default: { post: vi.fn() },
}))

// jsdom ne fournit pas ResizeObserver, utilisé par certains composants Vuetify.
globalThis.ResizeObserver ??= class {
  observe() {}
  unobserve() {}
  disconnect() {}
}

function monter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/espaces/:id/:onglet?', name: 'espace', component: { template: '<div />' } }],
  })
  return mount(AssistantTab, {
    props: { espaceId: 3 },
    global: { plugins: [createVuetify({ components, directives }), router] },
  })
}

describe('AssistantTab', () => {
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
})
