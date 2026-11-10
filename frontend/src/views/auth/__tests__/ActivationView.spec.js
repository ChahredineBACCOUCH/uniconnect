import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import http from '@/api/http'
import vuetify from '@/plugins/vuetify'
import { useAuthStore } from '@/stores/auth'
import ActivationView from '@/views/auth/ActivationView.vue'

// Aucun appel réseau : on simule le client HTTP de l'API Laravel.
vi.mock('@/api/http', () => ({
  default: { post: vi.fn() },
}))

const UTILISATEUR = {
  id: 1,
  nom: 'Ben Salah',
  prenom: 'Yassine',
  email: 'yassine.bensalah@univ.tn',
  profil: 'ETUDIANT',
  droits: [],
}

// La page lit les paramètres signés dans l'URL : on rejoue le lien de l'e-mail.
const LIEN = '/activation/5?expires=1700000000&signature=abc123'

async function monter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/activation/:id', name: 'activation', component: ActivationView },
    ],
  })

  await router.push(LIEN)
  await router.isReady()

  const pinia = createPinia()
  setActivePinia(pinia)

  const wrapper = mount(ActivationView, { global: { plugins: [vuetify, router, pinia] } })

  return { wrapper, router }
}

/** Remplit les deux champs avec un mot de passe qui respecte les quatre règles. */
async function remplir(wrapper, motDePasse = 'MotDePasse1') {
  const champs = wrapper.findAll('input')
  await champs[0].setValue(motDePasse)
  await champs[1].setValue(motDePasse)
}

describe('écran d’activation', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('envoie le mot de passe avec les paramètres signés puis ouvre la session', async () => {
    http.post.mockResolvedValue({ data: { token: 'jeton-123', user: UTILISATEUR } })

    const { wrapper, router } = await monter()
    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    // Les paramètres signés du lien sont renvoyés dans la chaîne de requête.
    expect(http.post).toHaveBeenCalledWith('/auth/activate/5?expires=1700000000&signature=abc123', {
      password: 'MotDePasse1',
      password_confirmation: 'MotDePasse1',
    })

    // Le jeton est enregistré et la personne arrive à l'accueil.
    const auth = useAuthStore()
    expect(auth.token).toBe('jeton-123')
    expect(auth.user).toEqual(UTILISATEUR)
    expect(router.currentRoute.value.name).toBe('home')
  })

  it('coche les règles en direct et n’autorise l’envoi qu’avec un mot de passe valide', async () => {
    const { wrapper } = await monter()
    const bouton = wrapper.find('button[type="submit"]')

    expect(wrapper.text()).toContain('8 caractères minimum')
    expect(wrapper.text()).toContain('Une majuscule')
    expect(wrapper.text()).toContain('Une minuscule')
    expect(wrapper.text()).toContain('Un chiffre')
    expect(bouton.attributes('disabled')).toBeDefined()

    // Un mot de passe trop faible coche une seule règle.
    const champs = wrapper.findAll('input')
    await champs[0].setValue('abc')
    await champs[1].setValue('abc')
    expect(wrapper.findAll('.regle--ok')).toHaveLength(1)
    expect(bouton.attributes('disabled')).toBeDefined()

    // Un mot de passe valide, mais une confirmation différente : l'envoi reste bloqué.
    await champs[0].setValue('MotDePasse1')
    await champs[1].setValue('MotDePasse2')
    expect(wrapper.findAll('.regle--ok')).toHaveLength(4)
    expect(bouton.attributes('disabled')).toBeDefined()

    await champs[1].setValue('MotDePasse1')
    expect(bouton.attributes('disabled')).toBeUndefined()
  })

  it('affiche l’écran « lien expiré » quand l’API répond 403', async () => {
    http.post.mockRejectedValue({ response: { status: 403, data: { message: 'Lien expiré.' } } })

    const { wrapper } = await monter()
    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain("Ce lien d'activation a expiré")
    expect(wrapper.text()).toContain("Renvoyer un e-mail d'activation")
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('demande un nouveau lien d’activation à l’adresse saisie', async () => {
    http.post.mockRejectedValue({ response: { status: 403, data: { message: 'Lien expiré.' } } })

    const { wrapper } = await monter()
    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    http.post.mockResolvedValue({ data: { message: 'E-mail envoyé' } })

    await wrapper.find('input[type="email"]').setValue('yassine.bensalah@univ.tn')
    const boutons = wrapper.findAll('button')
    await boutons[boutons.length - 1].trigger('click')
    await flushPromises()

    expect(http.post).toHaveBeenCalledWith('/auth/activate/renvoyer', {
      email: 'yassine.bensalah@univ.tn',
    })
    expect(wrapper.text()).toContain("Un nouvel e-mail d'activation a été envoyé à")
  })

  it('affiche l’erreur quand le renvoi du lien échoue', async () => {
    http.post.mockRejectedValue({ response: { status: 403, data: { message: 'Lien expiré.' } } })

    const { wrapper } = await monter()
    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    http.post.mockRejectedValue(new Error('Réseau indisponible'))

    await wrapper.find('input[type="email"]').setValue('yassine.bensalah@univ.tn')
    const boutons = wrapper.findAll('button')
    await boutons[boutons.length - 1].trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain("L'e-mail n'a pas pu être envoyé")
  })

  it('affiche les erreurs de l’API sous le champ concerné', async () => {
    http.post.mockRejectedValue({
      message: 'Le mot de passe est trop faible.',
      code: null,
      errors: { password: ['Le mot de passe doit contenir au moins 8 caractères.'] },
    })

    const { wrapper } = await monter()
    await remplir(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const messages = wrapper.findAll('.v-messages__message').map((message) => message.text())
    expect(messages).toContain('Le mot de passe doit contenir au moins 8 caractères.')
  })
})
