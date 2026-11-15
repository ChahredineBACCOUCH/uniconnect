import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'

const { push } = vi.hoisted(() => ({ push: vi.fn() }))

vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))

vi.mock('@/api/notifications', () => ({
  recupererNotifications: vi.fn(),
  marquerNotificationLue: vi.fn(),
  toutMarquerLu: vi.fn(),
}))

vi.mock('@/api/espaces', () => ({
  recupererMesEspaces: vi.fn(),
  recupererMesInvitations: vi.fn(),
  accepterInvitation: vi.fn(),
  refuserInvitation: vi.fn(),
}))

import { marquerNotificationLue, recupererNotifications, toutMarquerLu } from '@/api/notifications'
import {
  accepterInvitation,
  recupererMesEspaces,
  recupererMesInvitations,
  refuserInvitation,
} from '@/api/espaces'
import vuetify from '@/plugins/vuetify'
import NotificationsView from '@/views/NotificationsView.vue'

const ANNONCE = {
  id: 'n1',
  type: 'ANNONCE',
  titre: 'Report de la séance de TP',
  texte: 'Nouvelle annonce : Report de la séance de TP au lundi 20/10',
  lien: '/espaces/3/annonces',
  lue: false,
  cree_le: new Date().toISOString(),
}

const RESSOURCE = {
  id: 'n2',
  type: 'RESSOURCE',
  titre: 'Chapitre 3 – Normalisation',
  texte: 'Nouvelle ressource : Chapitre 3 – Normalisation.pdf',
  lien: '/espaces/3/ressources',
  lue: true,
  cree_le: new Date(Date.now() - 86_400_000).toISOString(),
}

const INVITATION = {
  id: 9,
  etat: 'EN_ATTENTE',
  espace: { id: 12, nom: 'Club Robotique' },
  invite_par: { id: 4, prenom: 'Karim', nom: 'Mansour', profil: 'ENSEIGNANT' },
  cree_le: '2026-11-10T10:00:00+00:00',
  expire_le: '2026-11-24T10:00:00+00:00',
}

let derniere = null

afterEach(() => {
  derniere?.unmount()
  derniere = null
})

function monter() {
  const pinia = createPinia()
  setActivePinia(pinia)

  derniere = mount(NotificationsView, {
    global: {
      plugins: [vuetify, pinia],
    },
  })

  return derniere
}

describe('écran Invitations et notifications', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    push.mockResolvedValue()
    recupererNotifications.mockResolvedValue({
      notifications: [{ ...ANNONCE }, { ...RESSOURCE }],
      nonLues: 1,
    })
    recupererMesEspaces.mockResolvedValue({ organisationnels: [], prives: [], channels: [] })
    recupererMesInvitations.mockResolvedValue([INVITATION])
    marquerNotificationLue.mockResolvedValue()
    toutMarquerLu.mockResolvedValue()
    accepterInvitation.mockResolvedValue({ id: 12 })
    refuserInvitation.mockResolvedValue()
  })

  it('affiche les notifications et le compteur de non-lues', async () => {
    const wrapper = monter()
    await flushPromises()

    expect(wrapper.text()).toContain('Invitations et notifications')
    expect(wrapper.text()).toContain(ANNONCE.texte)
    expect(wrapper.text()).toContain(RESSOURCE.texte)
    expect(wrapper.text()).toContain('1 notification non lue')
    expect(wrapper.text()).toContain('Tout marquer comme lu')
  })

  it('marque la notification comme lue et ouvre son lien au clic', async () => {
    const wrapper = monter()
    await flushPromises()

    const ligne = wrapper
      .findAll('a.notifications__ligne')
      .find((element) => element.text().includes(ANNONCE.texte))

    await ligne.trigger('click')
    await flushPromises()

    expect(marquerNotificationLue).toHaveBeenCalledWith('n1')
    expect(push).toHaveBeenCalledWith('/espaces/3/annonces')
  })

  it('suit le lien sans rappeler l’API pour une notification déjà lue', async () => {
    const wrapper = monter()
    await flushPromises()

    const ligne = wrapper
      .findAll('a.notifications__ligne')
      .find((element) => element.text().includes(RESSOURCE.texte))

    await ligne.trigger('click')
    await flushPromises()

    expect(marquerNotificationLue).not.toHaveBeenCalled()
    expect(push).toHaveBeenCalledWith('/espaces/3/ressources')
  })

  it('marque toutes les notifications comme lues', async () => {
    const wrapper = monter()
    await flushPromises()

    const bouton = wrapper
      .findAll('button')
      .find((element) => element.text().includes('Tout marquer comme lu'))

    await bouton.trigger('click')
    await flushPromises()

    expect(toutMarquerLu).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Tout est à jour')
    expect(wrapper.findAll('.notifications__point')).toHaveLength(0)
  })

  it('affiche les invitations et permet de les accepter', async () => {
    const wrapper = monter()
    await flushPromises()

    await wrapper.findAll('[role="tab"]')[0].trigger('click')
    await nextTick()

    expect(wrapper.text()).toContain('Club Robotique')
    expect(wrapper.text()).toContain('invité par Karim Mansour')

    const accepter = wrapper.findAll('button').find((element) => element.text() === 'Accepter')

    await accepter.trigger('click')
    await flushPromises()

    expect(accepterInvitation).toHaveBeenCalledWith(9)
    expect(push).toHaveBeenCalledWith({ name: 'espace', params: { id: 12 } })
  })

  it('permet de refuser une invitation', async () => {
    const wrapper = monter()
    await flushPromises()

    await wrapper.findAll('[role="tab"]')[0].trigger('click')
    await nextTick()

    const refuser = wrapper.findAll('button').find((element) => element.text() === 'Refuser')

    await refuser.trigger('click')
    await flushPromises()

    expect(refuserInvitation).toHaveBeenCalledWith(9)
    expect(accepterInvitation).not.toHaveBeenCalled()
  })

  it('affiche les états vides', async () => {
    recupererNotifications.mockResolvedValue({ notifications: [], nonLues: 0 })
    recupererMesInvitations.mockResolvedValue([])

    const wrapper = monter()
    await flushPromises()

    expect(wrapper.text()).toContain("Aucune notification pour l'instant.")

    await wrapper.findAll('[role="tab"]')[0].trigger('click')
    await nextTick()

    expect(wrapper.text()).toContain("Aucune invitation pour l'instant.")
  })
})
