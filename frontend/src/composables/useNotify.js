import { readonly, ref } from 'vue'

/*
 * SCRUM-184 : un seul message de retour (snackbar) pour toute l'application,
 * affiché par NotificationGlobale.vue (monté une fois dans App.vue).
 *
 *   const notify = useNotify()
 *   notify.succes('Invitation envoyée.')
 *   notify.erreur('Problème de connexion au serveur.', { actionLabel: 'Réessayer', action: recharger })
 *
 * L'état est partagé (module) : n'importe quel écran, store ou intercepteur peut
 * afficher un message sans dépendre du composant qui l'affiche.
 */

const DUREE = { succes: 3000, info: 4000, erreur: 6000 }

const etat = ref({
  visible: false,
  texte: '',
  type: 'succes',
  actionLabel: null,
  action: null,
  duree: DUREE.succes,
})

function afficher(type, texte, options = {}) {
  etat.value = {
    visible: true,
    texte,
    type,
    actionLabel: options.actionLabel ?? null,
    action: options.action ?? null,
    // Avec un bouton d'action, le message reste affiché jusqu'au clic ou à la fermeture.
    duree: options.duree ?? (options.action ? -1 : DUREE[type]),
  }
}

export function useNotify() {
  return {
    etat: readonly(etat),
    succes: (texte, options) => afficher('succes', texte, options),
    info: (texte, options) => afficher('info', texte, options),
    erreur: (texte, options) => afficher('erreur', texte, options),
    fermer: () => {
      etat.value = { ...etat.value, visible: false }
    },
    // Exécute l'action du message (ex. « Réessayer ») puis le ferme.
    agir: () => {
      const action = etat.value.action
      etat.value = { ...etat.value, visible: false }
      action?.()
    },
  }
}
