import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import http, { ValidationError } from '@/api/http'
import { useNotify } from '@/composables/useNotify'
import { useAuthStore } from '@/stores/auth'

// Le router n'est chargé qu'en cas de 401 : on le simule pour vérifier la redirection.
// vi.hoisted : la fonction doit exister avant vi.mock, qui est remonté en haut du fichier.
const { push } = vi.hoisted(() => ({ push: vi.fn() }))
vi.mock('@/router', () => ({
  default: { push, currentRoute: { value: { fullPath: '/espaces/2' } } },
}))

// Les intercepteurs enregistrés dans http.js, appelés directement sans réseau.
const onRequest = http.interceptors.request.handlers[0].fulfilled
const onResponseError = http.interceptors.response.handlers[0].rejected

function erreurHttp(status, data = {}) {
  return { response: { status, data } }
}

describe('client http', () => {
  beforeEach(() => {
    localStorage.clear()
    push.mockClear()
    setActivePinia(createPinia())
  })

  it('ajoute le jeton Bearer quand l’utilisateur est connecté', () => {
    useAuthStore().token = 'jeton-123'

    const config = onRequest({ headers: {} })

    expect(config.headers.Authorization).toBe('Bearer jeton-123')
  })

  it('n’ajoute pas d’en-tête Authorization sans jeton', () => {
    const config = onRequest({ headers: {} })

    expect(config.headers.Authorization).toBeUndefined()
  })

  it('401 avec jeton : vide la session et redirige vers /connexion', async () => {
    const auth = useAuthStore()
    auth.token = 'jeton-expire'

    await expect(onResponseError(erreurHttp(401))).rejects.toBeTruthy()

    expect(auth.token).toBeNull()
    expect(push).toHaveBeenCalledWith({ name: 'connexion', query: { redirect: '/espaces/2' } })
  })

  it('401 sans jeton (mauvais mot de passe) : pas de redirection', async () => {
    await expect(onResponseError(erreurHttp(401))).rejects.toBeTruthy()

    expect(push).not.toHaveBeenCalled()
  })

  it('422 : rejette une ValidationError avec les erreurs par champ', async () => {
    const errors = { email: ['Cette adresse ne figure pas sur la liste des comptes autorisés.'] }

    const rejet = onResponseError(erreurHttp(422, { message: 'Données invalides.', errors }))

    await expect(rejet).rejects.toBeInstanceOf(ValidationError)
    await expect(rejet).rejects.toMatchObject({ message: 'Données invalides.', errors })
  })

  it('422 : garde le code de refus renvoyé par l’API', async () => {
    const rejet = onResponseError(
      erreurHttp(422, { message: 'Plateforme fermée.', code: 'PLATEFORME_FERMEE', errors: {} }),
    )

    await expect(rejet).rejects.toMatchObject({ code: 'PLATEFORME_FERMEE' })
  })
})

describe('client http : erreur réseau (SCRUM-184)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    useNotify().fermer()
  })

  it('affiche un seul message clair avec « Réessayer » quand le serveur ne répond pas', async () => {
    await expect(
      onResponseError({ message: 'Network Error', code: 'ERR_NETWORK' }),
    ).rejects.toBeTruthy()

    const { etat } = useNotify()
    expect(etat.value.visible).toBe(true)
    expect(etat.value.type).toBe('erreur')
    expect(etat.value.texte).toContain('Problème de connexion au serveur')
    expect(etat.value.actionLabel).toBe('Réessayer')
  })

  it('ne dit rien pour une requête annulée volontairement', async () => {
    await expect(
      onResponseError({ message: 'canceled', code: 'ERR_CANCELED' }),
    ).rejects.toBeTruthy()

    expect(useNotify().etat.value.visible).toBe(false)
  })

  it('ne dit rien quand le serveur a répondu (erreur gérée par l’écran)', async () => {
    await expect(onResponseError(erreurHttp(500, { message: 'Erreur' }))).rejects.toBeTruthy()

    expect(useNotify().etat.value.visible).toBe(false)
  })
})
