import { onMounted, onUnmounted, ref } from 'vue'

/**
 * Appelle `callback` à intervalle régulier (5 s par défaut).
 *
 * - pause quand l'onglet est masqué (`document.hidden`) ;
 * - reprend immédiatement au retour sur l'onglet ;
 * - s'arrête au démontage du composant (onUnmounted).
 *
 * `executer` ignore un appel si le précédent n'est pas terminé : un serveur lent
 * ne peut donc pas accumuler les requêtes.
 */
export function usePolling(callback, { intervalle = 5000, onError } = {}) {
  const actif = ref(false)
  let minuteur = null
  let enCours = false

  async function executer() {
    if (enCours || document.hidden) return

    enCours = true

    try {
      await callback()
    } catch (error) {
      if (onError) onError(error)
    } finally {
      enCours = false
    }
  }

  function demarrer() {
    if (minuteur !== null) return

    actif.value = true
    minuteur = window.setInterval(executer, intervalle)
  }

  function arreter() {
    actif.value = false

    if (minuteur !== null) {
      window.clearInterval(minuteur)
      minuteur = null
    }
  }

  function surChangementVisibilite() {
    if (!document.hidden) executer()
  }

  onMounted(() => {
    document.addEventListener('visibilitychange', surChangementVisibilite)
    demarrer()
  })

  onUnmounted(() => {
    document.removeEventListener('visibilitychange', surChangementVisibilite)
    arreter()
  })

  return { actif, demarrer, arreter, executer }
}
