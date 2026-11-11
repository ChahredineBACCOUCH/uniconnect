import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import http from '@/api/http'
import vuetify from '@/plugins/vuetify'
import InscriptionView from '@/views/auth/InscriptionView.vue'

// Aucun appel réseau : on simule le client HTTP de l'API Laravel.
vi.mock('@/api/http', () => ({
  default: { post: vi.fn() },
}))

// Le bouton « Se connecter » est un lien : il lui faut un routeur.
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/connexion', component: { template: '<div />' } }],
})

function monter() {
  return mount(InscriptionView, { global: { plugins: [vuetify, router] } })
}

/** Remplit le formulaire comme le ferait la personne : matricule, nom, prénom, e-mail, charte. */
async function remplir(wrapper) {
  const champs = wrapper.findAll('input')
  await champs[0].setValue('LI2-2026-0457')
  await champs[1].setValue('Ben Salah')
  await champs[2].setValue('Yassine')
  await champs[3].setValue('yassine.bensalah@univ.tn')
  await champs[4].setValue(true)
}

async function envoyer(wrapper) {
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

describe('écran d’inscription', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('envoie le formulaire puis affiche l’écran « Vérifie ta boîte mail »', async () => {
    http.post.mockResolvedValue({ data: { message: 'E-mail envoyé', email_envoye: true } })

    const wrapper = monter()
    await remplir(wrapper)
    await envoyer(wrapper)

    expect(http.post).toHaveBeenCalledWith('/auth/register', {
      matricule: 'LI2-2026-0457',
      nom: 'Ben Salah',
      prenom: 'Yassine',
      email: 'yassine.bensalah@univ.tn',
      charte: true,
    })

    expect(wrapper.text()).toContain('Vérifie ta boîte mail')
    expect(wrapper.text()).toContain('yassine.bensalah@univ.tn')
    // Le formulaire a laissé place à l’écran de confirmation.
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('affiche chaque erreur renvoyée par l’API sous son champ', async () => {
    http.post.mockRejectedValue({
      message: 'Données invalides.',
      code: null,
      errors: {
        matricule: ['Le matricule est obligatoire.'],
        email: ['Cette adresse ne correspond pas à la liste des comptes autorisés.'],
      },
    })

    const wrapper = monter()
    await remplir(wrapper)
    await envoyer(wrapper)

    const messages = wrapper.findAll('.v-messages__message').map((message) => message.text())
    expect(messages).toContain('Le matricule est obligatoire.')
    expect(messages).toContain('Cette adresse ne correspond pas à la liste des comptes autorisés.')
    // Le formulaire reste affiché pour corriger la saisie.
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('affiche un bandeau bloquant quand la plateforme est fermée', async () => {
    http.post.mockRejectedValue({
      message: "La plateforme est fermée : aucune année universitaire n'est active.",
      code: 'PLATEFORME_FERMEE',
      errors: {},
    })

    const wrapper = monter()
    await remplir(wrapper)
    await envoyer(wrapper)

    expect(wrapper.text()).toContain(
      "La plateforme n'est pas encore ouverte pour cette année universitaire.",
    )

    const bouton = wrapper.find('button[type="submit"]')
    expect(bouton.attributes('disabled')).toBeDefined()
  })

  it('affiche un message général quand l’API ne renvoie pas d’erreur de champ', async () => {
    http.post.mockRejectedValue({
      message: "Un compte a déjà été créé pour ce matricule : l'inscription a déjà été effectuée.",
      code: 'DEJA_INSCRIT',
      errors: {},
    })

    const wrapper = monter()
    await remplir(wrapper)
    await envoyer(wrapper)

    expect(wrapper.text()).toContain(
      "Un compte a déjà été créé pour ce matricule : l'inscription a déjà été effectuée.",
    )
  })

  it('n’envoie rien tant que les champs obligatoires sont vides', async () => {
    const wrapper = monter()
    await envoyer(wrapper)

    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Le matricule est obligatoire.')
  })
})
