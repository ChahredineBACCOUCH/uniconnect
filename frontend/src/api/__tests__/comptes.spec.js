import { beforeEach, describe, expect, it, vi } from 'vitest'

import http from '@/api/http'
import {
  analyserImport,
  creerCompte,
  desactiverCompte,
  modifierCompte,
  recupererClasses,
  recupererComptes,
  supprimerCompte,
  telechargerModele,
  validerImport,
} from '@/api/comptes'

vi.mock('@/api/http', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}))

/*
 * SCRUM-77 : appels de l'écran A03 (comptes autorisés).
 */

describe('API des comptes autorisés', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('récupère la liste paginée avec ses filtres', async () => {
    const page = { data: [{ id: 1 }], meta: { page: 1, par_page: 10, total: 1 } }
    http.get.mockResolvedValue({ data: page })

    await expect(
      recupererComptes({ page: 1, par_page: 10, q: 'ben', profil: 'ETUDIANT' }),
    ).resolves.toEqual(page)
    expect(http.get).toHaveBeenCalledWith('/admin/comptes-autorises', {
      params: { page: 1, par_page: 10, q: 'ben', profil: 'ETUDIANT' },
    })
  })

  it('crée, modifie, désactive et supprime une autorisation', async () => {
    http.post
      .mockResolvedValueOnce({ data: { data: { id: 5, matricule: 'MAT-1' } } })
      .mockResolvedValueOnce({ data: { data: { id: 5, etat: 'DESACTIVE' } } })
    http.patch.mockResolvedValue({ data: { data: { id: 5, nom: 'Nom modifié' } } })
    http.delete.mockResolvedValue({})

    await expect(creerCompte({ matricule: 'MAT-1' })).resolves.toEqual({
      id: 5,
      matricule: 'MAT-1',
    })
    expect(http.post).toHaveBeenNthCalledWith(1, '/admin/comptes-autorises', {
      matricule: 'MAT-1',
    })

    await expect(modifierCompte(5, { nom: 'Nom modifié' })).resolves.toMatchObject({
      nom: 'Nom modifié',
    })
    expect(http.patch).toHaveBeenCalledWith('/admin/comptes-autorises/5', {
      nom: 'Nom modifié',
    })

    await expect(desactiverCompte(5)).resolves.toMatchObject({ etat: 'DESACTIVE' })
    expect(http.post).toHaveBeenNthCalledWith(2, '/admin/comptes-autorises/5/desactiver')

    await supprimerCompte(5)
    expect(http.delete).toHaveBeenCalledWith('/admin/comptes-autorises/5')
  })

  it('récupère les classes de l’année active', async () => {
    http.get.mockResolvedValue({ data: { data: [{ id: 3, code: 'L2-INFO', libelle: 'L2 Info' }] } })

    await expect(recupererClasses()).resolves.toEqual([
      { id: 3, code: 'L2-INFO', libelle: 'L2 Info' },
    ])
    expect(http.get).toHaveBeenCalledWith('/admin/comptes-autorises/classes')
  })

  it('envoie le fichier Excel en multipart pour l’aperçu', async () => {
    http.post.mockResolvedValue({ data: { total: 2, valides: 1, erreurs: [], lignes: [] } })
    const fichier = new File(['contenu'], 'comptes.xlsx')

    await expect(analyserImport(fichier)).resolves.toMatchObject({ total: 2, valides: 1 })

    const [url, formulaire] = http.post.mock.calls[0]
    expect(url).toBe('/admin/comptes-autorises/import/apercu')
    expect(formulaire).toBeInstanceOf(FormData)
    expect(formulaire.get('fichier')).toBe(fichier)
  })

  it('valide les lignes retenues avec le nom du fichier', async () => {
    http.post.mockResolvedValue({ data: { importes: 1, ignores: 1, erreurs: 1, import_id: 8 } })

    await expect(
      validerImport([{ ligne: 2, matricule: 'MAT-1' }], 'comptes.xlsx'),
    ).resolves.toEqual({ importes: 1, ignores: 1, erreurs: 1, import_id: 8 })
    expect(http.post).toHaveBeenCalledWith('/admin/comptes-autorises/import/valider', {
      lignes: [{ ligne: 2, matricule: 'MAT-1' }],
      fichier_nom: 'comptes.xlsx',
    })
  })

  it('télécharge le modèle Excel et déclenche l’enregistrement du fichier', async () => {
    const blob = new Blob(['modele'], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    http.get.mockResolvedValue({ data: blob })

    const urlCreation = vi.fn(() => 'blob:modele')
    const urlRevocation = vi.fn()
    vi.stubGlobal('URL', { ...URL, createObjectURL: urlCreation, revokeObjectURL: urlRevocation })

    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

    await telechargerModele()

    expect(http.get).toHaveBeenCalledWith('/admin/comptes-autorises/import/modele', {
      responseType: 'blob',
    })
    expect(urlCreation).toHaveBeenCalledWith(blob)
    expect(click).toHaveBeenCalled()
    expect(urlRevocation).toHaveBeenCalledWith('blob:modele')

    click.mockRestore()
    vi.unstubAllGlobals()
  })
})
