import { beforeEach, describe, expect, it, vi } from 'vitest'

import http from '@/api/http'
import {
  annulerInvitation,
  changerRole,
  creerEspace,
  envoyerMessage,
  inviter,
  modifierMessage,
  quitterEspace,
  rechercherUtilisateurs,
  recupererEspace,
  recupererInvitationsEspace,
  recupererMembres,
  recupererMessages,
  retirerMembre,
  supprimerMessage,
  transfererPropriete,
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
    http.patch.mockResolvedValue({
      data: { data: { id: 9, contenu: 'Bonjour corrigé', modifie: true } },
    })
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

  it('crée un groupe privé puis invite des personnes (SCRUM-89)', async () => {
    http.post
      .mockResolvedValueOnce({ data: { data: { id: 7, nom: 'Projet', role: 'PROPRIETAIRE' } } })
      .mockResolvedValueOnce({ data: { creees: 2, deja_membres: [], deja_invites: [] } })

    await expect(creerEspace({ nom: 'Projet' })).resolves.toMatchObject({ id: 7 })
    expect(http.post).toHaveBeenCalledWith('/espaces', { nom: 'Projet' })

    await expect(inviter(7, [3, 4])).resolves.toEqual({
      creees: 2,
      deja_membres: [],
      deja_invites: [],
    })
    expect(http.post).toHaveBeenCalledWith('/espaces/7/invitations', { user_ids: [3, 4] })
  })

  it('recherche des personnes avec le paramètre q', async () => {
    http.get.mockResolvedValue({ data: { data: [{ id: 3, nom: 'Ben Ali' }] } })

    await expect(rechercherUtilisateurs('ben')).resolves.toEqual([{ id: 3, nom: 'Ben Ali' }])
    expect(http.get).toHaveBeenCalledWith('/utilisateurs', { params: { q: 'ben' } })
  })

  it('appelle les routes de gestion des membres (SCRUM-91)', async () => {
    http.get.mockResolvedValue({ data: { data: [] } })
    http.patch.mockResolvedValue({ data: { data: { role: 'CO_GESTIONNAIRE' } } })
    http.post.mockResolvedValue({ data: { data: { role: 'PROPRIETAIRE' } } })
    http.delete.mockResolvedValue({})

    await recupererMembres(7)
    expect(http.get).toHaveBeenCalledWith('/espaces/7/membres')

    await recupererInvitationsEspace(7)
    expect(http.get).toHaveBeenCalledWith('/espaces/7/invitations')

    await changerRole(7, 3, 'CO_GESTIONNAIRE')
    expect(http.patch).toHaveBeenCalledWith('/espaces/7/membres/3', { role: 'CO_GESTIONNAIRE' })

    await transfererPropriete(7, 3)
    expect(http.post).toHaveBeenCalledWith('/espaces/7/transferer', { user_id: 3 })

    await retirerMembre(7, 4)
    expect(http.delete).toHaveBeenCalledWith('/espaces/7/membres/4')

    await annulerInvitation(12)
    expect(http.delete).toHaveBeenCalledWith('/invitations/12')

    await quitterEspace(7)
    expect(http.post).toHaveBeenCalledWith('/espaces/7/quitter')
  })
})
