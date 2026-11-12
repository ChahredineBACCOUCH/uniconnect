import axios from 'axios'

import { useAuthStore } from '@/stores/auth'

/*
 * Client unique pour appeler l'API Laravel.
 * Toutes les pages et tous les stores passent par lui : le jeton et la gestion
 * des erreurs sont donc traités au même endroit.
 */
const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { Accept: 'application/json' },
})

/*
 * Erreur 422 renvoyée par Laravel quand un formulaire est invalide.
 * `errors` a la forme { champ: ['message', ...] } : les formulaires l'utilisent
 * pour afficher chaque message sous le bon champ.
 * `code` est le motif du refus quand l'API en renvoie un (PLATEFORME_FERMEE…).
 */
export class ValidationError extends Error {
  constructor(message, errors, code = null) {
    super(message)
    this.name = 'ValidationError'
    this.errors = errors
    this.code = code
  }
}

// Avant chaque requête : ajoute le jeton Sanctum s'il existe.
http.interceptors.request.use((config) => {
  const auth = useAuthStore()
  if (auth.token) {
    config.headers.Authorization = `Bearer ${auth.token}`
  }
  return config
})

// Après chaque réponse en erreur : 401 → déconnexion, 422 → erreurs de champs.
http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error.response?.status

    if (status === 401) {
      const auth = useAuthStore()
      // Sans jeton (ex. mauvais mot de passe à la connexion), on laisse la page gérer l'erreur.
      if (auth.token) {
        auth.clearSession()
        // Import au moment de l'erreur pour éviter une boucle d'imports router ↔ store ↔ http.
        const { default: router } = await import('@/router')
        await router.push({
          name: 'connexion',
          query: { redirect: router.currentRoute.value.fullPath },
        })
      }
    }

    if (status === 422) {
      const data = error.response.data ?? {}
      return Promise.reject(
        new ValidationError(
          data.message ?? 'Le formulaire contient des erreurs.',
          data.errors ?? {},
          data.code ?? null,
        ),
      )
    }

    return Promise.reject(error)
  },
)

export default http
