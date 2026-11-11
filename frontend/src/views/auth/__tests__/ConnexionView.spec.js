import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import http from '@/api/http'
import vuetify from '@/plugins/vuetify'
import { useAuthStore } from '@/stores/auth'
import ConnexionView from '@/views/auth/ConnexionView.vue'

// Aucun appel réseau : on simule le client HTTP de l'API Laravel.
vi.mock('@/api/http', () => ({
  default: { post: vi.fn(), get: vi.fn() },
}))

const UTILISATEUR = {
  id: 1,
  nom: 'Ben Salah',
  prenom: 'Yassine',
  email: 'yassine.bensalah@univ.tn',
  profil: 'ETUDIANT',
  droits: [],
}

async function monter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/connexion', name: 'connexion', component: ConnexionView },
      { path: '/espaces', name: 'espaces', component: { template: '<div />' } },
    ],
  })

  await router.push('/connexion')
  await router.isReady()

  const pinia = createPinia()
  setActivePinia(pinia)

  const wrapper = mount(ConnexionView, { global: { plugins: [vuetify, router, pinia] } })

  return { wrapper, router }
}

/** Remplit l'e-mail et le mot de passe. */
async function remplir(wrapper) {
  const champs = wrapper.findAll('input')
  await champs[0].setValue('yassine.bensalah@univ.tn')
  await champs[1].setValue('MotDePasse1')
}

describe('écran de connexion', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    sessionStorage.clear()
  })

  it('connecte la personne et la redirige vers l’accueil', async () => {
    http.post.mockResolvedValue({ data: { token: 'jeton-123', user: UTILISATEUR } })

    const { wrapper, router } = await monter()
    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(http.post).toHaveBeenCalledWith('/auth/login', {
      email: 'yassine.bensalah@univ.tn',
      password: 'MotDePasse1',
    })

    const auth = useAuthStore()
    expect(auth.token).toBe('jeton-123')
    expect(auth.user).toEqual(UTILISATEUR)
    expect(router.currentRoute.value.name).toBe('home')
  })

  it('affiche l’erreur quand les identifiants sont incorrects', async () => {
    http.post.mockRejectedValue({
      response: {
        status: 422,
        data: { message: 'Identifiants incorrects.', tentatives_restantes: 4 },
      },
    })

    const { wrapper, router } = await monter()
    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Identifiants incorrects.')
    expect(wrapper.text()).toContain('Il vous reste 4 tentatives.')
    expect(router.currentRoute.value.name).toBe('connexion')
  })

  it('affiche l’erreur quand le compte est bloqué', async () => {
    http.post.mockRejectedValue({
      response: { status: 423, data: { message: "Compte bloqué jusqu'à 23:02." } },
    })

    const { wrapper } = await monter()
    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain("Compte bloqué jusqu'à 23:02.")
  })

  it('affiche l’erreur quand le compte est suspendu ou désactivé', async () => {
    http.post.mockRejectedValue({
      response: {
        status: 403,
        data: { message: "Ce compte est suspendu : contactez l'administration." },
      },
    })

    const { wrapper } = await monter()
    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain("Ce compte est suspendu : contactez l'administration.")
  })

  it('garde le jeton en sessionStorage si « Rester connecté » n’est pas coché', async () => {
    http.post.mockResolvedValue({ data: { token: 'jeton-123', user: UTILISATEUR } })

    const { wrapper } = await monter()

    // On décoche « Rester connecté ».
    await wrapper.find('input[type="checkbox"]').setValue(false)

    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(localStorage.getItem('uniconnect.token')).toBeNull()
    expect(sessionStorage.getItem('uniconnect.token')).toBe('jeton-123')
  })

  it('garde le jeton en localStorage si « Rester connecté » est coché', async () => {
    http.post.mockResolvedValue({ data: { token: 'jeton-123', user: UTILISATEUR } })

    const { wrapper } = await monter()
    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(localStorage.getItem('uniconnect.token')).toBe('jeton-123')
    expect(sessionStorage.getItem('uniconnect.token')).toBeNull()
  })
})
