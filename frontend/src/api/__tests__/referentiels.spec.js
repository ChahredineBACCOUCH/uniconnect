import { beforeEach, describe, expect, it, vi } from 'vitest'

import http from '@/api/http'
import {
  creerReferentiel,
  desactiverReferentiel,
  modifierReferentiel,
  recupererAnnees,
  recupererEnseignants,
  recupererOptions,
  recupererReferentiel,
  supprimerReferentiel,
} from '@/api/referentiels'

vi.mock('@/api/http', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}))

/*
 * SCRUM-80 : appels des cinq référentiels de l'écran A02.
 */
describe('API des référentiels académiques', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('récupère une liste paginée avec la recherche', async () => {
    const page = { data: [{ id: 1 }], meta: { page: 1, par_page: 10, total: 1 } }
    http.get.mockResolvedValue({ data: page })

    await expect(
      recupererReferentiel('/admin/departements', { page: 1, par_page: 10, q: 'info' }),
    ).resolves.toEqual(page)
    expect(http.get).toHaveBeenCalledWith('/admin/departements', {
      params: { page: 1, par_page: 10, q: 'info' },
    })
  })

  it('crée, modifie, désactive et supprime un élément', async () => {
    http.post.mockResolvedValue({ data: { data: { id: 5, code: 'INFO' } } })
    http.patch.mockResolvedValue({ data: { data: { id: 5, libelle: 'Informatique' } } })
    http.delete.mockResolvedValue({})

    await expect(creerReferentiel('/admin/departements', { code: 'INFO' })).resolves.toEqual({
      id: 5,
      code: 'INFO',
    })
    expect(http.post).toHaveBeenCalledWith('/admin/departements', { code: 'INFO' })

    await expect(
      modifierReferentiel('/admin/departements', 5, { libelle: 'Informatique' }),
    ).resolves.toMatchObject({ libelle: 'Informatique' })
    expect(http.patch).toHaveBeenCalledWith('/admin/departements/5', {
      libelle: 'Informatique',
    })

    await expect(desactiverReferentiel('/admin/departements', 5)).resolves.toMatchObject({
      id: 5,
    })
    expect(http.patch).toHaveBeenCalledWith('/admin/departements/5/desactiver')

    await supprimerReferentiel('/admin/departements', 5)
    expect(http.delete).toHaveBeenCalledWith('/admin/departements/5')
  })

  it('charge les options actives sans pagination visible', async () => {
    http.get.mockResolvedValue({ data: { data: [{ id: 1, code: 'INFO' }] } })

    await expect(recupererOptions('/admin/departements')).resolves.toEqual([
      { id: 1, code: 'INFO' },
    ])
    expect(http.get).toHaveBeenCalledWith('/admin/departements', {
      params: { actif: 1, par_page: 100 },
    })
  })

  it('récupère les années et les enseignants', async () => {
    http.get
      .mockResolvedValueOnce({ data: { data: [{ id: 1, libelle: '2026/2027', active: true }] } })
      .mockResolvedValueOnce({ data: { data: [{ id: 7, nom: 'Ben Ali', prenom: 'Sana' }] } })

    await expect(recupererAnnees()).resolves.toEqual([
      { id: 1, libelle: '2026/2027', active: true },
    ])
    expect(http.get).toHaveBeenNthCalledWith(1, '/admin/annees')

    await expect(recupererEnseignants()).resolves.toEqual([
      { id: 7, nom: 'Ben Ali', prenom: 'Sana' },
    ])
    expect(http.get).toHaveBeenNthCalledWith(2, '/admin/matieres/enseignants')
  })
})
