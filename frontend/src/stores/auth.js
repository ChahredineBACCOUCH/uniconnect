import { defineStore } from 'pinia'

import http from '@/api/http'

const TOKEN_KEY = 'uniconnect.token'

/*
 * Routes de l'API d'authentification.
 * À aligner avec le LoginController du backend (SCRUM-69) si leurs noms changent.
 */
export const AUTH_ROUTES = {
  login: '/login',
  logout: '/logout',
  me: '/user',
}

// localStorage peut être indisponible (navigation privée, réglages du navigateur) :
// dans ce cas, on garde seulement le jeton en mémoire.
function readToken() {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

function writeToken(token) {
  try {
    if (token) localStorage.setItem(TOKEN_KEY, token)
    else localStorage.removeItem(TOKEN_KEY)
  } catch {
    // Rien à faire : le jeton reste en mémoire pour cette session.
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    // Utilisateur connecté : { id, nom, prenom, email, profil, droits: ['GERER_UTILISATEURS', ...] }
    user: null,
    // Jeton Sanctum, conservé dans localStorage pour rester connecté après un rechargement.
    token: readToken(),
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token),

    // Usage : auth.hasDroit('GERER_UTILISATEURS')
    hasDroit: (state) => (droit) => Boolean(state.user?.droits?.includes(droit)),
  },

  actions: {
    async login(email, motDePasse) {
      const { data } = await http.post(AUTH_ROUTES.login, { email, password: motDePasse })
      this.token = data.token
      this.user = data.user ?? null
      writeToken(this.token)
      return this.user
    },

    async fetchMe() {
      const { data } = await http.get(AUTH_ROUTES.me)
      this.user = data
      return this.user
    },

    async logout() {
      try {
        if (this.token) await http.post(AUTH_ROUTES.logout)
      } catch {
        // Même si le serveur ne répond pas, on déconnecte l'utilisateur côté navigateur.
      } finally {
        this.clearSession()
      }
    },

    // Vide la session sans appeler l'API (utilisé aussi par http.js en cas de 401).
    clearSession() {
      this.user = null
      this.token = null
      writeToken(null)
    },
  },
})
