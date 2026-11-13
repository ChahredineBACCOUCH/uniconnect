import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/api/http', () => ({
  default: { get: vi.fn(), patch: vi.fn(), post: vi.fn() },
}))

import http from '@/api/http'
import {
  NOTIFICATION_ROUTES,
  marquerNotificationLue,
  recupererNotifications,
  toutMarquerLu,
} from '@/api/notifications'

describe('API notifications', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('lit la liste et le compteur de non-lues', async () => {
    http.get.mockResolvedValue({
      data: {
        data: [{ id: 'a', type: 'ANNONCE', texte: 'Annonce', lue: false }],
        meta: { non_lues: 3 },
      },
    })

    const resultat = await recupererNotifications({ non_lues: true })

    expect(http.get).toHaveBeenCalledWith(NOTIFICATION_ROUTES.liste, {
      params: { non_lues: true },
    })
    expect(resultat).toEqual({
      notifications: [{ id: 'a', type: 'ANNONCE', texte: 'Annonce', lue: false }],
      nonLues: 3,
    })
  })

  it('renvoie zéro non-lue si la méta est absente', async () => {
    http.get.mockResolvedValue({ data: { data: [] } })

    await expect(recupererNotifications()).resolves.toEqual({ notifications: [], nonLues: 0 })
  })

  it('marque une notification comme lue', async () => {
    http.patch.mockResolvedValue({ status: 204 })

    await marquerNotificationLue('abc')

    expect(http.patch).toHaveBeenCalledWith('/notifications/abc/lue')
  })

  it('marque toutes les notifications comme lues', async () => {
    http.post.mockResolvedValue({ status: 204 })

    await toutMarquerLu()

    expect(http.post).toHaveBeenCalledWith('/notifications/tout-lire')
  })
})
