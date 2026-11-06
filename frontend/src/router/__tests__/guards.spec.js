import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import http from '@/api/http'
import { authGuard } from '@/router/guards'
import { useAuthStore } from '@/stores/auth'

// Aucun appel réseau : on simule le client HTTP utilisé par le store.
vi.mock('@/api/http', () => ({
  default: { post: vi.fn(), get: vi.fn() },
}))

const ETUDIANT = { id: 1, profil: 'ETUDIANT', droits: ['CREER_GROUPE_PRIVE'] }
const ADMIN = {
  id: 2,
  profil: 'ADMINISTRATION',
  droits: ['GERER_PARAMETRAGE', 'GERER_UTILISATEURS'],
}

// Imite l'objet `to` de Vue Router (meta déjà fusionné du parent vers l'enfant).
const route = (meta, fullPath = '/page') => ({ meta, fullPath })
const pageConnectee = route({ auth: true }, '/espaces/2')
const pageAdmin = route(
  { auth: true, profil: 'ADMINISTRATION', droit: 'GERER_PARAMETRAGE' },
  '/admin/annees',
)

function connecter(user) {
  const auth = useAuthStore()
  auth.token = 'jeton-123'
  auth.user = user
  return auth
}

describe('garde du router', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('déconnecté : une page protégée redirige vers /connexion en gardant la page demandée', async () => {
    expect(await authGuard(pageConnectee)).toEqual({
      name: 'connexion',
      query: { redirect: '/espaces/2' },
    })
  })

  it('déconnecté : une page publique est accessible', async () => {
    expect(await authGuard(route({ invite: true }, '/connexion'))).toBe(true)
  })

  it('connecté : la page de connexion renvoie vers l’accueil', async () => {
    connecter(ETUDIANT)
    expect(await authGuard(route({ invite: true }, '/connexion'))).toEqual({ name: 'home' })
  })

  it('étudiant : une page admin affiche la page 403', async () => {
    connecter(ETUDIANT)
    expect(await authGuard(pageAdmin)).toEqual({ name: 'forbidden' })
  })

  it('administration avec le droit : la page admin est accessible', async () => {
    connecter(ADMIN)
    expect(await authGuard(pageAdmin)).toBe(true)
  })

  it('administration sans le droit demandé : page 403', async () => {
    connecter({ ...ADMIN, droits: ['GERER_UTILISATEURS'] })
    expect(await authGuard(pageAdmin)).toEqual({ name: 'forbidden' })
  })

  it('droit seul (sans profil) : vérifié pour tout le monde', async () => {
    connecter({ ...ETUDIANT, droits: [] })
    expect(await authGuard(route({ auth: true, droit: 'CREER_GROUPE_PRIVE' }))).toEqual({
      name: 'forbidden',
    })
  })

  it('après un rechargement : charge l’utilisateur avant de décider', async () => {
    http.get.mockResolvedValue({ data: ADMIN })
    const auth = useAuthStore()
    auth.token = 'jeton-123'

    expect(await authGuard(pageAdmin)).toBe(true)
    expect(http.get).toHaveBeenCalledTimes(1)
    expect(auth.user).toEqual(ADMIN)
  })

  it('jeton expiré (401) : session vidée puis redirection vers /connexion', async () => {
    http.get.mockRejectedValue({ response: { status: 401 } })
    const auth = useAuthStore()
    auth.token = 'jeton-expire'

    expect(await authGuard(pageConnectee)).toEqual({
      name: 'connexion',
      query: { redirect: '/espaces/2' },
    })
    expect(auth.token).toBeNull()
  })

  it('API injoignable : le jeton est conservé', async () => {
    http.get.mockRejectedValue(new Error('Network Error'))
    const auth = useAuthStore()
    auth.token = 'jeton-123'

    expect(await authGuard(pageConnectee)).toBe(true)
    expect(auth.token).toBe('jeton-123')
  })
})
