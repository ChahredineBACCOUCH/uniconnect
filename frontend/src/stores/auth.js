import { defineStore } from 'pinia'

import http from '@/api/http'

const TOKEN_KEY = 'uniconnect.token'

/*
 * Routes de l'API d'authentification (SCRUM-69 : LoginController).
 */
export const AUTH_ROUTES = {
  login: '/auth/login',
  logout: '/auth/logout',
  me: '/me',
}

/*
 * Le jeton est conservé dans localStorage quand la personne a coché
 * « Rester connecté » (le compte reste ouvert même après la fermeture du
 * navigateur), dans sessionStorage sinon (la session s'arrête avec l'onglet).
 *
 * localStorage peut être indisponible (navigation privée, réglages du
 * navigateur) : dans ce cas, on garde seulement le jeton en mémoire.
 */
function readToken() {
  try {
    return localStorage.getItem(TOKEN_KEY) ?? sessionStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

function writeToken(token, resterConnecte = true) {
  try {
    // Le jeton ne doit pas rester dans l'autre emplacement.
    localStorage.removeItem(TOKEN_KEY)
    sessionStorage.removeItem(TOKEN_KEY)

    if (token) {
      const stockage = resterConnecte ? localStorage : sessionStorage
      stockage.setItem(TOKEN_KEY, token)
    }
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
    async login(email, motDePasse, resterConnecte = true) {
      const { data } = await http.post(AUTH_ROUTES.login, { email, password: motDePasse })
      this.token = data.token
      this.user = data.user ?? null
      writeToken(this.token, resterConnecte)
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

    /*
     * Ouvre une session avec le jeton renvoyé par l'API : c'est le cas après
     * l'activation du compte (SCRUM-66), qui renvoie le jeton et l'utilisateur.
     */
    connecter(token, utilisateur, resterConnecte = true) {
      this.token = token ?? null
      this.user = utilisateur ?? null
      writeToken(this.token, resterConnecte)
    },

    // Vide la session sans appeler l'API (utilisé aussi par http.js en cas de 401).
    clearSession() {
      this.user = null
      this.token = null
      writeToken(null)
    },
  },
})
