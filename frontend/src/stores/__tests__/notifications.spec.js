import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/api/notifications', () => ({
  recupererNotifications: vi.fn(),
  marquerNotificationLue: vi.fn(),
  toutMarquerLu: vi.fn(),
}))

import { marquerNotificationLue, recupererNotifications, toutMarquerLu } from '@/api/notifications'
import { useNotificationsStore } from '@/stores/notifications'

const NOTIFICATIONS = [
  { id: 'a', type: 'ANNONCE', texte: 'Nouvelle annonce', lue: false },
  { id: 'b', type: 'RESSOURCE', texte: 'Nouvelle ressource', lue: true },
  { id: 'c', type: 'INVITATION', texte: 'Invitation', lue: false },
]

describe('store notifications', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    recupererNotifications.mockResolvedValue({
      notifications: NOTIFICATIONS.map((notification) => ({ ...notification })),
      nonLues: 2,
    })
    marquerNotificationLue.mockResolvedValue()
    toutMarquerLu.mockResolvedValue()
  })

  it('charge la liste et le compteur de non-lues depuis l’API', async () => {
    const store = useNotificationsStore()

    await store.charger()

    expect(recupererNotifications).toHaveBeenCalledTimes(1)
    expect(store.liste.map((notification) => notification.id)).toEqual(['a', 'b', 'c'])
    expect(store.nonLues).toBe(2)
    expect(store.charge).toBe(true)
    expect(store.chargement).toBe(false)
    expect(store.estVide).toBe(false)
  })

  it('garde un message d’erreur si l’API échoue', async () => {
    recupererNotifications.mockRejectedValue({
      response: { data: { message: 'Serveur indisponible' } },
    })
    const store = useNotificationsStore()

    await store.charger()

    expect(store.erreur).toBe('Serveur indisponible')
    expect(store.charge).toBe(false)
    expect(store.chargement).toBe(false)
    expect(store.estVide).toBe(true)
  })

  it('marque une notification comme lue et décrémente le compteur', async () => {
    const store = useNotificationsStore()
    await store.charger()

    await store.marquerLue('a')

    expect(marquerNotificationLue).toHaveBeenCalledWith('a')
    expect(store.liste[0].lue).toBe(true)
    expect(store.nonLues).toBe(1)
  })

  it('ne rappelle pas l’API pour une notification déjà lue', async () => {
    const store = useNotificationsStore()
    await store.charger()

    await store.marquerLue('b')

    expect(marquerNotificationLue).not.toHaveBeenCalled()
    expect(store.nonLues).toBe(2)
  })

  it('restaure la notification si le marquage échoue', async () => {
    marquerNotificationLue.mockRejectedValue(new Error('réseau'))
    const store = useNotificationsStore()
    await store.charger()

    await expect(store.marquerLue('a')).rejects.toThrow('réseau')

    expect(store.liste[0].lue).toBe(false)
    expect(store.nonLues).toBe(2)
  })

  it('marque toutes les notifications comme lues', async () => {
    const store = useNotificationsStore()
    await store.charger()

    await store.toutLire()

    expect(toutMarquerLu).toHaveBeenCalledTimes(1)
    expect(store.liste.every((notification) => notification.lue)).toBe(true)
    expect(store.nonLues).toBe(0)
  })

  it('restaure les notifications si « tout lire » échoue', async () => {
    toutMarquerLu.mockRejectedValue(new Error('réseau'))
    const store = useNotificationsStore()
    await store.charger()

    await expect(store.toutLire()).rejects.toThrow('réseau')

    expect(store.liste.map((notification) => notification.lue)).toEqual([false, true, false])
    expect(store.nonLues).toBe(2)
  })

  it('vide le store à la déconnexion', async () => {
    const store = useNotificationsStore()
    await store.charger()

    store.vider()

    expect(store.liste).toEqual([])
    expect(store.nonLues).toBe(0)
    expect(store.charge).toBe(false)
  })
})
