import { beforeEach, describe, expect, it, vi } from 'vitest'

import http from '@/api/http'
import { activerAnnee, creerAnnee, modifierAnnee, recupererAnnees } from '@/api/annees'

vi.mock('@/api/http', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
  },
}))

/*
 * SCRUM-73 : appels de l'écran A01 (années universitaires).
 */
describe('API des années universitaires', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('récupère la liste non paginée des années', async () => {
    http.get.mockResolvedValue({
      data: { data: [{ id: 1, libelle: '2026/2027', active: true }] },
    })

    await expect(recupererAnnees()).resolves.toEqual([
      { id: 1, libelle: '2026/2027', active: true },
    ])
    expect(http.get).toHaveBeenCalledWith('/admin/annees')
  })

  it('crée et modifie une année', async () => {
    http.post.mockResolvedValue({ data: { data: { id: 8, libelle: '2028/2029' } } })
    http.patch.mockResolvedValue({ data: { data: { id: 8, libelle: '2028/2029' } } })

    const payload = {
      libelle: '2028/2029',
      date_debut: '2028-09-01',
      date_fin: '2029-07-31',
    }

    await expect(creerAnnee(payload)).resolves.toMatchObject({ id: 8 })
    expect(http.post).toHaveBeenCalledWith('/admin/annees', payload)

    await expect(modifierAnnee(8, { date_fin: '2029-07-31' })).resolves.toMatchObject({ id: 8 })
    expect(http.patch).toHaveBeenCalledWith('/admin/annees/8', { date_fin: '2029-07-31' })
  })

  it("active une année et transmet l'option d'archivage", async () => {
    http.post.mockResolvedValue({ data: { data: { id: 8, active: true } } })

    await expect(activerAnnee(8, { archiver_precedente: true })).resolves.toMatchObject({
      active: true,
    })
    expect(http.post).toHaveBeenCalledWith('/admin/annees/8/activer', {
      archiver_precedente: true,
    })

    await activerAnnee(8)
    expect(http.post).toHaveBeenLastCalledWith('/admin/annees/8/activer', {})
  })
})
