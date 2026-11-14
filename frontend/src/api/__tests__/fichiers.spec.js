import { beforeEach, describe, expect, it, vi } from 'vitest'

import { apercuFichier, telechargerFichier } from '@/api/fichiers'
import http from '@/api/http'

vi.mock('@/api/http', () => ({
  default: {
    get: vi.fn(),
  },
}))

describe('API des fichiers (SCRUM-137)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('télécharge la pièce jointe en Blob via la route protégée', async () => {
    const blob = new Blob(['contenu'])
    http.get.mockResolvedValue({ data: blob })

    await expect(telechargerFichier(12)).resolves.toBe(blob)
    expect(http.get).toHaveBeenCalledWith('/fichiers/12/telecharger', {
      responseType: 'blob',
    })
  })

  it("charge l'aperçu d'une image en Blob avec ?apercu=1", async () => {
    const blob = new Blob(['image'], { type: 'image/png' })
    http.get.mockResolvedValue({ data: blob })

    await expect(apercuFichier(12)).resolves.toBe(blob)
    expect(http.get).toHaveBeenCalledWith('/fichiers/12/telecharger', {
      params: { apercu: 1 },
      responseType: 'blob',
    })
  })
})
