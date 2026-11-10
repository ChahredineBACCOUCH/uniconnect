import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { flushPromises, mount } from '@vue/test-utils'

import InscriptionView from '@/views/auth/InscriptionView.vue'
import http, { ValidationError } from '@/api/http'

// Aucun appel réseau : on simule les réponses de l'API.
vi.mock('@/api/http', () => {
  class ValidationError extends Error {
    constructor(message, errors, code = null) {
      super(message)
      this.name = 'ValidationError'
      this.errors = errors
      this.code = code
    }
  }

  return { default: { post: vi.fn() }, ValidationError }
})

const vuetify = createVuetify({ components, directives })

function creerRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/inscription', component: { template: '<div />' } },
      { path: '/connexion', component: { template: '<div />' } },
    ],
  })
}

async function monter() {
  const router = creerRouter()
  await router.push('/inscription')
  await router.isReady()

  const wrapper = mount(InscriptionView, {
    global: { plugins: [vuetify, router] },
  })
  await flushPromises()

  return wrapper
}

// Remplit le formulaire et l'envoie.
async function remplir(wrapper, { matricule, email, charte = true }) {
  await wrapper.find('#matricule').setValue(matricule)
  await wrapper.find('#nom').setValue('Ben Salah')
  await wrapper.find('#prenom').setValue('Yassine')
  await wrapper.find('#email').setValue(email)
  if (charte) await wrapper.find('#charte').setValue(true)
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

describe('écran d’inscription', () => {
  beforeEach(() => {
    http.post.mockReset()
  })

  it('affiche les champs de la maquette E02', async () => {
    const wrapper = await monter()

    expect(wrapper.find('#matricule').exists()).toBe(true)
    expect(wrapper.find('#nom').exists()).toBe(true)
    expect(wrapper.find('#prenom').exists()).toBe(true)
    expect(wrapper.find('#email').exists()).toBe(true)
    expect(wrapper.find('#charte').exists()).toBe(true)
    expect(wrapper.text()).toContain('Créer mon compte')
    expect(wrapper.text()).toContain('Seules les personnes figurant sur la liste des comptes autorisés')
  })

  it('envoie le formulaire à l’API et remplace l’écran par la confirmation', async () => {
    http.post.mockResolvedValue({ status: 201, data: { message: 'E-mail envoyé' } })

    const wrapper = await monter()
    await remplir(wrapper, { matricule: 'ETU2026-001', email: 'etu2026-001@uniconnect.test' })

    expect(http.post).toHaveBeenCalledWith('/auth/register', {
      matricule: 'ETU2026-001',
      nom: 'Ben Salah',
      prenom: 'Yassine',
      email: 'etu2026-001@uniconnect.test',
      charte: true,
    })
    expect(wrapper.text()).toContain('Vérifie ta boîte mail')
    expect(wrapper.text()).toContain('etu2026-001@uniconnect.test')
    expect(wrapper.find('#matricule').exists()).toBe(false)
  })

  it('affiche l’erreur renvoyée par l’API sous le champ concerné', async () => {
    const message = 'Ce matricule n’est pas autorisé à créer un compte.'
    http.post.mockRejectedValue(
      new ValidationError(message, { email: [message] }, 'NON_AUTORISE'),
    )

    const wrapper = await monter()
    await remplir(wrapper, { matricule: 'ETU2026-001', email: 'inconnu@univ.tn' })

    expect(wrapper.text()).toContain(message)
    expect(wrapper.find('#matricule').exists()).toBe(true)
  })

  it('bloque le formulaire quand la plateforme est fermée', async () => {
    http.post.mockRejectedValue(
      new ValidationError('La plateforme est fermée.', {}, 'PLATEFORME_FERMEE'),
    )

    const wrapper = await monter()
    await remplir(wrapper, { matricule: 'ETU2026-001', email: 'etu2026-001@uniconnect.test' })

    expect(wrapper.text()).toContain("La plateforme n'est pas encore ouverte")
    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
  })

  it('refuse l’envoi tant que la charte n’est pas acceptée', async () => {
    const wrapper = await monter()
    await remplir(wrapper, {
      matricule: 'ETU2026-001',
      email: 'etu2026-001@uniconnect.test',
      charte: false,
    })

    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Vous devez accepter la charte')
  })
})
