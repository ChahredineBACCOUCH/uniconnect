import { defineStore } from 'pinia'

import { marquerNotificationLue, recupererNotifications, toutMarquerLu } from '@/api/notifications'

/*
 * SCRUM-122 : notifications de l'utilisateur connecté.
 *
 * La liste et le compteur de non-lues viennent de GET /notifications
 * (SCRUM-121). Le rafraîchissement toutes les 30 s est branché dans AppLayout
 * avec le composable usePolling (SCRUM-95).
 */
export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    liste: [],
    nonLues: 0,
    charge: false,
    chargement: false,
    erreur: null,
  }),

  getters: {
    estVide: (state) => state.liste.length === 0,
  },

  actions: {
    /*
     * Recharge la liste et le compteur. `discret` : pas d'état de chargement
     * (rafraîchissement périodique, sans squelette).
     */
    async charger({ discret = false } = {}) {
      // AppLayout et la page Notifications peuvent demander le chargement en même temps.
      if (this.chargement) return

      if (!discret) this.chargement = true
      this.erreur = null

      try {
        const { notifications, nonLues } = await recupererNotifications()
        this.liste = notifications
        this.nonLues = nonLues
        this.charge = true
      } catch (error) {
        this.erreur =
          error?.response?.data?.message ?? 'Vos notifications n’ont pas pu être chargées.'
      } finally {
        this.chargement = false
      }
    },

    /**
     * Marque une notification comme lue. L'affichage est mis à jour tout de
     * suite et restauré si l'API refuse.
     */
    async marquerLue(id) {
      const notification = this.liste.find((element) => element.id === id)
      if (!notification || notification.lue) return

      notification.lue = true
      this.nonLues = Math.max(0, this.nonLues - 1)

      try {
        await marquerNotificationLue(id)
      } catch (error) {
        notification.lue = false
        this.nonLues += 1
        throw error
      }
    },

    /** Marque toutes les notifications comme lues, avec retour arrière si l'API refuse. */
    async toutLire() {
      // Le compteur peut dépasser la page courante : c'est lui qui fait foi.
      if (this.nonLues === 0) return

      const nonLues = this.liste.filter((notification) => !notification.lue)
      const compteurPrecedent = this.nonLues

      for (const notification of nonLues) notification.lue = true
      this.nonLues = 0

      try {
        await toutMarquerLu()
      } catch (error) {
        for (const notification of nonLues) notification.lue = false
        this.nonLues = compteurPrecedent
        throw error
      }
    },

    vider() {
      this.$reset()
    },
  },
})
