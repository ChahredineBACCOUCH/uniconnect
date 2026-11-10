import { beforeEach, describe, expect, it, vi } from 'vitest'

import http from '@/api/http'
import {
  envoyerMessage,
  modifierMessage,
  recupererEspace,
  recupererMessages,
  supprimerMessage,
} from '@/api/espaces'

vi.mock('@/api/http', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}))

describe('API des espaces', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('déballe l’espace renvoyé dans data', async () => {
    http.get.mockResolvedValue({ data: { data: { id: 2, nom: 'Bases de données' } } })

    await expect(recupererEspace(2)).resolves.toEqual({ id: 2, nom: 'Bases de données' })
    expect(http.get).toHaveBeenCalledWith('/espaces/2')
  })

  it('transmet after_id pour le polling des messages', async () => {
    const page = { data: [{ id: 4 }], meta: { per_page: 30 } }
    http.get.mockResolvedValue({ data: page })

    await expect(recupererMessages(2, { after_id: 3 })).resolves.toEqual(page)
    expect(http.get).toHaveBeenCalledWith('/espaces/2/messages', { params: { after_id: 3 } })
  })

  it('envoie, modifie et supprime un message', async () => {
    http.post.mockResolvedValue({ data: { data: { id: 9, contenu: 'Bonjour' } } })
    http.patch.mockResolvedValue({ data: { data: { id: 9, contenu: 'Bonjour corrigé', modifie: true } } })
    http.delete.mockResolvedValue({})

    await expect(envoyerMessage(2, { contenu: 'Bonjour' })).resolves.toEqual({
      id: 9,
      contenu: 'Bonjour',
    })
    expect(http.post).toHaveBeenCalledWith('/espaces/2/messages', { contenu: 'Bonjour' })

    await expect(modifierMessage(9, 'Bonjour corrigé')).resolves.toMatchObject({
      contenu: 'Bonjour corrigé',
      modifie: true,
    })
    expect(http.patch).toHaveBeenCalledWith('/messages/9', { contenu: 'Bonjour corrigé' })

    await supprimerMessage(9)
    expect(http.delete).toHaveBeenCalledWith('/messages/9')
  })
})
