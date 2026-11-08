import { defineStore } from 'pinia'

import http from '@/api/http'
import { simulerQuestion } from '@/api/iaSimulation'

/*
 * Routes de l'Assistant IA (docs/api.md, section 7).
 * POST /espaces/{id}/ia/questions : { question, conversation_id? }
 *   → { conversation_id, echange_id, reponse, statut, sources: [{ n, fichier, page, ressource_id }], quota_restant }
 */
export const IA_ROUTES = {
  questions: (espaceId) => `/espaces/${espaceId}/ia/questions`,
}

export const QUOTA_JOUR = 20
export const QUESTION_MIN = 3
export const QUESTION_MAX = 1000

// Développement uniquement : réponses simulées tant que l'API n'existe pas (voir src/api/iaSimulation.js).
const SIMULATION = import.meta.env.DEV && import.meta.env.VITE_IA_SIMULATION === 'true'

/*
 * Un échange = une question et sa réponse :
 * { question, reponse, sources, statut, echangeId, erreur }
 *   statut : EN_COURS (on attend l'IA), OK, SANS_SOURCE (rien dans le cours), ERREUR
 *   erreur : { code, message } quand statut = ERREUR (429 quota, 503 IA indisponible, réseau…)
 */
export const useIaStore = defineStore('ia', {
  state: () => ({
    espaceId: null,
    conversationId: null,
    echanges: [],
    enCours: false,
    // Questions restantes aujourd'hui, connu après la première réponse de l'API.
    quotaRestant: null,
  }),

  getters: {
    estVide: (state) => state.echanges.length === 0,
    quotaAtteint: (state) => state.quotaRestant === 0,
  },

  actions: {
    // Change d'espace : la conversation précédente appartient à un autre cours.
    ouvrir(espaceId) {
      if (String(this.espaceId) !== String(espaceId)) {
        this.espaceId = espaceId
        this.nouvelleConversation()
      }
    },

    nouvelleConversation() {
      this.conversationId = null
      this.echanges = []
    },

    // Renvoie false si la question est refusée sans appel (vide, trop courte ou longue, envoi en cours).
    async poser(espaceId, question) {
      const texte = question.trim()
      if (this.enCours || texte.length < QUESTION_MIN || texte.length > QUESTION_MAX) return false

      this.ouvrir(espaceId)
      this.echanges.push({
        question: texte,
        reponse: '',
        sources: [],
        statut: 'EN_COURS',
        echangeId: null,
        erreur: null,
      })
      await this.envoyer(this.echanges.length - 1)
      return true
    },

    // Repose une question qui a échoué (bouton « Réessayer »).
    async reessayer(index) {
      if (this.enCours || this.echanges[index]?.statut !== 'ERREUR') return
      await this.envoyer(index)
    },

    async envoyer(index) {
      const echange = this.echanges[index]
      echange.statut = 'EN_COURS'
      echange.erreur = null
      this.enCours = true

      const corps = { question: echange.question }
      if (this.conversationId) corps.conversation_id = this.conversationId

      try {
        const data = SIMULATION
          ? await simulerQuestion(this.espaceId, corps)
          : (await http.post(IA_ROUTES.questions(this.espaceId), corps)).data

        this.conversationId = data.conversation_id ?? this.conversationId
        echange.echangeId = data.echange_id ?? null
        echange.reponse = data.reponse ?? ''
        echange.sources = data.sources ?? []
        echange.statut = data.statut === 'SANS_SOURCE' ? 'SANS_SOURCE' : 'OK'
        if (typeof data.quota_restant === 'number') this.quotaRestant = data.quota_restant
      } catch (erreur) {
        const code = erreur.response?.status ?? null
        echange.statut = 'ERREUR'
        echange.erreur = {
          code,
          message: erreur.response?.data?.message ?? erreur.message ?? 'Erreur inconnue',
        }
        if (code === 429) this.quotaRestant = 0
      } finally {
        this.enCours = false
      }
    },
  },
})
