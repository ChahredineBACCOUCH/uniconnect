import { beforeEach, describe, expect, it, vi } from 'vitest'

import http from '@/api/http'
import {
  apercuGeneration,
  archiverGroupe,
  genererGroupes,
  recupererFiltresGroupes,
  recupererGroupes,
  synchroniserGroupe,
} from '@/api/groupes'

vi.mock('@/api/http', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

/*
 * SCRUM-83 : appels de l'écran A06 (groupes organisationnels).
 */
describe('API des groupes organisationnels', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('récupère les groupes en ignorant les filtres vides', async () => {
    http.get.mockResolvedValue({
      data: {
        annee: { id: 7, libelle: '2026/2027' },
        data: [{ id: 1, nom: 'L2 Informatique A' }],
        total: 1,
      },
    })

    await expect(
      recupererGroupes({ filiere_id: 2, niveau_id: null, classe_id: '', enseignant_id: undefined }),
    ).resolves.toEqual({
      annee: { id: 7, libelle: '2026/2027' },
      groupes: [{ id: 1, nom: 'L2 Informatique A' }],
      total: 1,
    })

    expect(http.get).toHaveBeenCalledWith('/admin/groupes', { params: { filiere_id: 2 } })
  })

  it('renvoie une liste vide quand aucune année n’est active', async () => {
    http.get.mockResolvedValue({ data: { annee: null, data: [], total: 0 } })

    await expect(recupererGroupes()).resolves.toEqual({
      annee: null,
      groupes: [],
      total: 0,
    })
  })

  it('récupère les options de filtres avec des valeurs par défaut', async () => {
    http.get.mockResolvedValue({
      data: { annee: null, filieres: [], niveaux: [], classes: [], enseignants: [] },
    })

    await expect(recupererFiltresGroupes()).resolves.toEqual({
      annee: null,
      filieres: [],
      niveaux: [],
      classes: [],
      enseignants: [],
    })
    expect(http.get).toHaveBeenCalledWith('/admin/groupes/filtres')
  })

  it('demande l’aperçu puis la génération', async () => {
    http.get.mockResolvedValue({ data: { total: 9, a_creer: [] } })
    http.post.mockResolvedValue({ data: { crees: 9, membres_ajoutes: 16 } })

    await expect(apercuGeneration()).resolves.toEqual({ total: 9, a_creer: [] })
    expect(http.get).toHaveBeenCalledWith('/admin/groupes/apercu')

    await expect(genererGroupes()).resolves.toEqual({ crees: 9, membres_ajoutes: 16 })
    expect(http.post).toHaveBeenCalledWith('/admin/groupes/generer')
  })

  it('synchronise et archive un groupe', async () => {
    http.post.mockResolvedValue({ data: { membres_ajoutes: 1 } })

    await expect(synchroniserGroupe(4)).resolves.toEqual({ membres_ajoutes: 1 })
    expect(http.post).toHaveBeenCalledWith('/admin/groupes/4/synchroniser')

    await expect(archiverGroupe(7)).resolves.toEqual({ membres_ajoutes: 1 })
    expect(http.post).toHaveBeenCalledWith('/admin/groupes/7/archiver')
  })
})
