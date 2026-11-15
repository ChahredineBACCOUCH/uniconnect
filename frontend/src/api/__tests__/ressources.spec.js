import { beforeEach, describe, expect, it, vi } from 'vitest'

import http from '@/api/http'
import {
  creerChapitre,
  creerRessource,
  deplacerRessource,
  modifierRessource,
  recupererChapitres,
  supprimerRessource,
  telechargerRessource,
} from '@/api/ressources'

vi.mock('@/api/http', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}))

describe('API des ressources', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('récupère les chapitres et leurs ressources', async () => {
    const chapitres = [{ id: 1, titre: 'Chapitre 1', ressources: [] }]
    http.get.mockResolvedValue({ data: { data: chapitres } })

    await expect(recupererChapitres(7)).resolves.toEqual(chapitres)
    expect(http.get).toHaveBeenCalledWith('/espaces/7/chapitres')
  })

  it('crée un chapitre', async () => {
    http.post.mockResolvedValue({ data: { data: { id: 2, titre: 'Chapitre 2' } } })

    await expect(creerChapitre(7, { titre: 'Chapitre 2' })).resolves.toMatchObject({ id: 2 })
    expect(http.post).toHaveBeenCalledWith('/espaces/7/chapitres', { titre: 'Chapitre 2' })
  })

  it('ajoute une ressource en multipart dans son chapitre', async () => {
    http.post.mockResolvedValue({ data: { data: { id: 5 } } })
    const fichier = new File(['contenu'], 'cours.pdf', { type: 'application/pdf' })

    await expect(
      creerRessource(3, { titre: 'Cours', description: null, fichier, url: null }),
    ).resolves.toEqual({ id: 5 })

    const [url, donnees] = http.post.mock.calls[0]
    expect(url).toBe('/chapitres/3/ressources')
    expect(donnees).toBeInstanceOf(FormData)
    expect(donnees.get('titre')).toBe('Cours')
    expect(donnees.get('fichier')).toBe(fichier)
    // Les champs vides ne sont pas envoyés.
    expect(donnees.has('description')).toBe(false)
    expect(donnees.has('url')).toBe(false)
  })

  it('renomme une ressource en JSON et remplace un fichier en multipart', async () => {
    http.patch.mockResolvedValue({ data: { data: { id: 5 } } })
    http.post.mockResolvedValue({ data: { data: { id: 5 } } })
    const fichier = new File(['contenu'], 'cours.pdf', { type: 'application/pdf' })

    await modifierRessource(5, { titre: 'Nouveau nom' })
    expect(http.patch).toHaveBeenCalledWith('/ressources/5', { titre: 'Nouveau nom' })

    await modifierRessource(5, { titre: 'Nouveau nom', fichier })
    const [url, donnees] = http.post.mock.calls[0]
    expect(url).toBe('/ressources/5')
    expect(donnees.get('_method')).toBe('PATCH')
    expect(donnees.get('fichier')).toBe(fichier)
  })

  it('déplace et supprime une ressource', async () => {
    http.patch.mockResolvedValue({ data: { data: { id: 5, chapitre_id: 2 } } })
    http.delete.mockResolvedValue({})

    await deplacerRessource(5, 2)
    expect(http.patch).toHaveBeenCalledWith('/ressources/5/deplacer', { chapitre_id: 2 })

    await supprimerRessource(5)
    expect(http.delete).toHaveBeenCalledWith('/ressources/5')
  })

  it('télécharge le fichier en Blob via la route protégée', async () => {
    const blob = new Blob(['contenu'])
    http.get.mockResolvedValue({ data: blob })

    await expect(telechargerRessource(5)).resolves.toBe(blob)
    expect(http.get).toHaveBeenCalledWith('/ressources/5/telecharger', { responseType: 'blob' })
  })
})
