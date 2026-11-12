import { defineStore } from 'pinia'

import {
  accepterInvitation,
  recupererMesEspaces,
  recupererMesInvitations,
  refuserInvitation,
} from '@/api/espaces'

/*
 * SCRUM-86 : espaces de l'utilisateur connecté (menu latéral, accueil) et
 * invitations en attente. Données lues dans l'API (GET /me/espaces,
 * GET /me/invitations), jamais écrites en dur.
 */

export const RUBRIQUES = [
  { cle: 'organisationnels', titre: 'Mes classes et matières' },
  { cle: 'prives', titre: 'Mes groupes privés' },
  { cle: 'channels', titre: 'Mes channels' },
]

function listeVide() {
  return { organisationnels: [], prives: [], channels: [] }
}

export const useEspacesStore = defineStore('espaces', {
  state: () => ({
    liste: listeVide(),
    invitations: [],
    charge: false,
    chargement: false,
    erreur: null,
  }),

  getters: {
    tous: (state) => RUBRIQUES.flatMap(({ cle }) => state.liste[cle] ?? []),
    estVide() {
      return this.tous.length === 0
    },
    totalNonLus() {
      return this.tous.reduce((total, espace) => total + (espace.non_lus ?? 0), 0)
    },
  },

  actions: {
    /*
     * Recharge espaces et invitations. `discret` : pas d'état de chargement
     * (rafraîchissement des compteurs après une action, sans squelette).
     */
    async charger({ discret = false } = {}) {
      if (!discret) this.chargement = true
      this.erreur = null

      try {
        const [espaces, invitations] = await Promise.all([
          recupererMesEspaces(),
          recupererMesInvitations(),
        ])
        this.liste = { ...listeVide(), ...espaces }
        this.invitations = invitations ?? []
        this.charge = true
      } catch (error) {
        this.erreur = error?.response?.data?.message ?? 'Vos espaces n’ont pas pu être chargés.'
      } finally {
        this.chargement = false
      }
    },

    /** Accepte l'invitation, recharge le menu et renvoie l'espace rejoint. */
    async accepter(invitationId) {
      const espace = await accepterInvitation(invitationId)
      await this.charger({ discret: true })
      return espace
    },

    async refuser(invitationId) {
      await refuserInvitation(invitationId)
      this.invitations = this.invitations.filter((invitation) => invitation.id !== invitationId)
    },

    vider() {
      this.$reset()
    },
  },
})
