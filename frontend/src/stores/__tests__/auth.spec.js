import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import http from '@/api/http'
import { AUTH_ROUTES, useAuthStore } from '@/stores/auth'

// On remplace le vrai client HTTP : aucun appel réseau pendant les tests.
vi.mock('@/api/http', () => ({
  default: { post: vi.fn(), get: vi.fn() },
}))

const ETUDIANT = {
  id: 1,
  nom: 'Ben Salah',
  prenom: 'Yassine',
  email: 'yassine.bensalah@univ.tn',
  droits: ['CREER_GROUPE_PRIVE'],
}

describe('store auth', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('remplit le jeton et l’utilisateur après login', async () => {
    http.post.mockResolvedValue({ data: { token: 'jeton-123', user: ETUDIANT } })
    const auth = useAuthStore()

    await auth.login('yassine.bensalah@univ.tn', 'Univ2026')

    expect(http.post).toHaveBeenCalledWith(AUTH_ROUTES.login, {
      email: 'yassine.bensalah@univ.tn',
      password: 'Univ2026',
    })
    expect(auth.token).toBe('jeton-123')
    expect(auth.user).toEqual(ETUDIANT)
    expect(auth.isAuthenticated).toBe(true)
    expect(localStorage.getItem('uniconnect.token')).toBe('jeton-123')
  })

  it('garde le jeton en sessionStorage quand la personne ne reste pas connectée', async () => {
    http.post.mockResolvedValue({ data: { token: 'jeton-123', user: ETUDIANT } })
    const auth = useAuthStore()

    await auth.login('yassine.bensalah@univ.tn', 'Univ2026', false)

    expect(sessionStorage.getItem('uniconnect.token')).toBe('jeton-123')
    expect(localStorage.getItem('uniconnect.token')).toBeNull()
  })

  it('retrouve le jeton conservé en sessionStorage après un rechargement', () => {
    sessionStorage.setItem('uniconnect.token', 'jeton-session')

    const auth = useAuthStore()

    expect(auth.token).toBe('jeton-session')
  })

  it('connecter ouvre une session avec le jeton renvoyé par l’API', () => {
    const auth = useAuthStore()

    auth.connecter('jeton-activation', ETUDIANT)

    expect(auth.token).toBe('jeton-activation')
    expect(auth.user).toEqual(ETUDIANT)
    expect(auth.isAuthenticated).toBe(true)
    expect(localStorage.getItem('uniconnect.token')).toBe('jeton-activation')
  })

  it('vide le jeton après logout', async () => {
    http.post.mockResolvedValue({ data: { token: 'jeton-123', user: ETUDIANT } })
    const auth = useAuthStore()
    await auth.login('yassine.bensalah@univ.tn', 'Univ2026')

    await auth.logout()

    expect(http.post).toHaveBeenLastCalledWith(AUTH_ROUTES.logout)
    expect(auth.token).toBeNull()
    expect(auth.user).toBeNull()
    expect(auth.isAuthenticated).toBe(false)
    expect(localStorage.getItem('uniconnect.token')).toBeNull()
  })

  it('déconnecte quand même si l’API de logout échoue', async () => {
    http.post
      .mockResolvedValueOnce({ data: { token: 'jeton-123', user: ETUDIANT } })
      .mockRejectedValueOnce(new Error('Réseau indisponible'))
    const auth = useAuthStore()
    await auth.login('yassine.bensalah@univ.tn', 'Univ2026')

    await auth.logout()

    expect(auth.token).toBeNull()
  })

  it('hasDroit renvoie vrai seulement pour les droits de l’utilisateur', async () => {
    http.post.mockResolvedValue({ data: { token: 'jeton-123', user: ETUDIANT } })
    const auth = useAuthStore()

    expect(auth.hasDroit('CREER_GROUPE_PRIVE')).toBe(false)

    await auth.login('yassine.bensalah@univ.tn', 'Univ2026')

    expect(auth.hasDroit('CREER_GROUPE_PRIVE')).toBe(true)
    expect(auth.hasDroit('GERER_UTILISATEURS')).toBe(false)
  })

  it('fetchMe charge l’utilisateur connecté', async () => {
    http.get.mockResolvedValue({ data: ETUDIANT })
    const auth = useAuthStore()

    await auth.fetchMe()

    expect(http.get).toHaveBeenCalledWith(AUTH_ROUTES.me)
    expect(auth.user).toEqual(ETUDIANT)
  })

  it('reprend le jeton enregistré au rechargement de la page', () => {
    localStorage.setItem('uniconnect.token', 'jeton-sauvegarde')

    const auth = useAuthStore()

    expect(auth.token).toBe('jeton-sauvegarde')
  })
})
