import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { recupererMessages } from '@/api/espaces'
import DiscussionTab from '@/components/espaces/DiscussionTab.vue'
import vuetify from '@/plugins/vuetify'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/espaces', () => ({
  recupererEspace: vi.fn(),
  recupererMessages: vi.fn(),
  envoyerMessage: vi.fn(),
  modifierMessage: vi.fn(),
  supprimerMessage: vi.fn(),
}))

const espace = {
  id: 2,
  nom: 'Bases de données',
  type: 'PRIVE',
  etat: 'ACTIF',
  role: 'MEMBRE',
  duree_visibilite_heures: 24,
  duree_courte_autorisee: false,
  mode_annonce: false,
}

const MessageItemStub = {
  props: ['message', 'utilisateurId', 'signale'],
  template: '<div class="message-stub">{{ message.contenu }}</div>',
}

const ComposerStub = {
  props: ['espace', 'reponseA', 'utilisateurId', 'envoyer'],
  template: '<div class="composer-stub" />',
}

function message(id, contenu, creeLe, auteur = { id: 1, prenom: 'Amine', nom: 'Benali', profil: 'ETUDIANT' }) {
  return {
    id,
    contenu,
    auteur,
    parent_id: null,
    expire_le: null,
    modifie: false,
    modifie_le: null,
    cree_le: creeLe,
    pieces_jointes: [],
  }
}

function monterDiscussion() {
  return mount(DiscussionTab, {
    props: { espace },
    global: {
      plugins: [vuetify],
      stubs: {
        MessageItem: MessageItemStub,
        Composer: ComposerStub,
      },
    },
  })
}

describe('DiscussionTab', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    // Les messages de test sont datés du 10/11/2026 : on fige l'horloge au même
    // jour pour que les libellés « Aujourd'hui » / « Hier » ne dépendent pas de
    // la date d'exécution du CI.
    vi.setSystemTime(new Date(2026, 10, 10, 10, 0, 0))
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    setActivePinia(createPinia())
    useAuthStore().user = { id: 1, prenom: 'Amine', nom: 'Benali' }
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
    delete document.hidden
  })

  it('charge les messages puis récupère les nouveaux avec after_id', async () => {
    recupererMessages
      .mockResolvedValueOnce({
        data: [message(1, 'Premier message', new Date(2026, 10, 10, 9, 0, 0).toISOString())],
      })
      .mockResolvedValueOnce({
        data: [message(2, 'Nouveau message', new Date(2026, 10, 10, 9, 5, 0).toISOString())],
      })

    const wrapper = monterDiscussion()
    await flushPromises()

    expect(recupererMessages).toHaveBeenCalledWith(2)
    expect(wrapper.text()).toContain('Premier message')
    expect(wrapper.text()).toContain("Aujourd'hui")

    await vi.advanceTimersByTimeAsync(5000)
    await flushPromises()

    expect(recupererMessages).toHaveBeenLastCalledWith(2, { after_id: 1 })
    expect(wrapper.text()).toContain('Nouveau message')

    wrapper.unmount()
  })

  it('arrête le polling quand on quitte l’onglet', async () => {
    recupererMessages.mockResolvedValue({ data: [] })

    const wrapper = monterDiscussion()
    await flushPromises()
    expect(recupererMessages).toHaveBeenCalledTimes(1)

    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(15_000)
    await flushPromises()

    expect(recupererMessages).toHaveBeenCalledTimes(1)
  })

  it('affiche une erreur et permet de réessayer', async () => {
    recupererMessages
      .mockRejectedValueOnce(new Error('Serveur indisponible'))
      .mockResolvedValueOnce({ data: [message(1, 'De retour', new Date().toISOString())] })

    const wrapper = monterDiscussion()
    await flushPromises()

    expect(wrapper.text()).toContain('Serveur indisponible')

    const bouton = wrapper.findAll('button').find((element) => element.text().includes('Réessayer'))
    await bouton.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('De retour')

    wrapper.unmount()
  })
})
