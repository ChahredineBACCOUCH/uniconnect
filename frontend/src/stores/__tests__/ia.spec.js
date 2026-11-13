import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import http from '@/api/http'
import { IA_ROUTES, useIaStore } from '@/stores/ia'

// On remplace le vrai client HTTP : aucun appel réseau pendant les tests.
vi.mock('@/api/http', () => ({
  default: { post: vi.fn(), patch: vi.fn() },
}))

const REPONSE_OK = {
  conversation_id: 7,
  echange_id: 42,
  reponse: 'Les attributs sont %FOUND et %NOTFOUND [1].',
  statut: 'OK',
  sources: [{ n: 1, fichier: 'chap03.pdf', page: 3, ressource_id: 5 }],
  quota_restant: 19,
}

function erreurHttp(status, message) {
  return Object.assign(new Error(message), { response: { status, data: { message } } })
}

describe('store ia', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('pose une question et range la réponse avec ses sources', async () => {
    http.post.mockResolvedValue({ data: REPONSE_OK })
    const ia = useIaStore()

    const envoyee = await ia.poser(3, '  Quels sont les attributs ?  ')

    expect(envoyee).toBe(true)
    expect(http.post).toHaveBeenCalledWith(IA_ROUTES.questions(3), {
      question: 'Quels sont les attributs ?',
    })
    expect(ia.conversationId).toBe(7)
    expect(ia.quotaRestant).toBe(19)
    expect(ia.enCours).toBe(false)
    expect(ia.echanges).toHaveLength(1)
    expect(ia.echanges[0]).toMatchObject({
      question: 'Quels sont les attributs ?',
      statut: 'OK',
      echangeId: 42,
      sources: REPONSE_OK.sources,
    })
  })

  it('renvoie conversation_id pour les questions suivantes', async () => {
    http.post.mockResolvedValue({ data: REPONSE_OK })
    const ia = useIaStore()

    await ia.poser(3, 'Première question')
    await ia.poser(3, 'Deuxième question')

    expect(http.post).toHaveBeenLastCalledWith(IA_ROUTES.questions(3), {
      question: 'Deuxième question',
      conversation_id: 7,
    })
  })

  it('marque SANS_SOURCE quand le cours ne contient pas la réponse', async () => {
    http.post.mockResolvedValue({
      data: { ...REPONSE_OK, reponse: '', statut: 'SANS_SOURCE', sources: [] },
    })
    const ia = useIaStore()

    await ia.poser(3, 'Quel temps fera-t-il demain ?')

    expect(ia.echanges[0].statut).toBe('SANS_SOURCE')
  })

  it('refuse sans appel une question trop courte ou trop longue', async () => {
    const ia = useIaStore()

    expect(await ia.poser(3, 'ok')).toBe(false)
    expect(await ia.poser(3, 'x'.repeat(1001))).toBe(false)
    expect(http.post).not.toHaveBeenCalled()
    expect(ia.echanges).toHaveLength(0)
  })

  it('garde l’erreur 503 et permet de réessayer', async () => {
    http.post.mockRejectedValueOnce(erreurHttp(503, 'IA indisponible'))
    const ia = useIaStore()

    await ia.poser(3, 'Quels sont les attributs ?')
    expect(ia.echanges[0]).toMatchObject({ statut: 'ERREUR', erreur: { code: 503 } })

    http.post.mockResolvedValueOnce({ data: REPONSE_OK })
    await ia.reessayer(0)

    expect(http.post).toHaveBeenCalledTimes(2)
    expect(ia.echanges).toHaveLength(1)
    expect(ia.echanges[0]).toMatchObject({ statut: 'OK', erreur: null })
  })

  it('met le quota à 0 après une erreur 429', async () => {
    http.post.mockRejectedValue(erreurHttp(429, 'Vous avez atteint vos 20 questions du jour.'))
    const ia = useIaStore()

    await ia.poser(3, 'Encore une question')

    expect(ia.quotaAtteint).toBe(true)
    expect(ia.echanges[0].erreur).toEqual({
      code: 429,
      message: 'Vous avez atteint vos 20 questions du jour.',
    })
  })

  it('repart d’une conversation vide quand on change d’espace', async () => {
    http.post.mockResolvedValue({ data: REPONSE_OK })
    const ia = useIaStore()
    await ia.poser(3, 'Question dans le cours 3')

    ia.ouvrir(4)

    expect(ia.echanges).toHaveLength(0)
    expect(ia.conversationId).toBeNull()
  })
  // SCRUM-146 : avis 👍/👎.
  it("envoie l'avis sur la réponse et peut le changer", async () => {
    http.post.mockResolvedValue({ data: REPONSE_OK })
    http.patch.mockResolvedValue({ status: 204 })
    const ia = useIaStore()
    await ia.poser(3, 'Quels sont les attributs ?')

    expect(await ia.donnerAvis(0, true)).toBe(true)
    expect(http.patch).toHaveBeenLastCalledWith(IA_ROUTES.avis(42), { utile: true })
    expect(ia.echanges[0].avis).toBe(true)

    // Même avis : rien n'est renvoyé.
    await ia.donnerAvis(0, true)
    expect(http.patch).toHaveBeenCalledTimes(1)

    await ia.donnerAvis(0, false)
    expect(http.patch).toHaveBeenLastCalledWith(IA_ROUTES.avis(42), { utile: false })
    expect(ia.echanges[0].avis).toBe(false)
  })

  it("annule l'avis si l'API le refuse", async () => {
    http.post.mockResolvedValue({ data: REPONSE_OK })
    http.patch.mockRejectedValue(new Error('Network Error'))
    const ia = useIaStore()
    await ia.poser(3, 'Quels sont les attributs ?')

    expect(await ia.donnerAvis(0, true)).toBe(false)
    expect(ia.echanges[0].avis).toBeNull()
  })
})
