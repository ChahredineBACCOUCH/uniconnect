import { useAuthStore } from '@/stores/auth'

/*
 * Garde globale appelée avant chaque changement de page.
 * Les règles viennent du champ `meta` des routes (fusionné du parent vers l'enfant) :
 *   - meta.auth   : page réservée aux utilisateurs connectés
 *   - meta.invite : page réservée aux visiteurs (connexion, inscription…)
 *   - meta.profil : profil exigé (ex. 'ADMINISTRATION')
 *   - meta.droit  : droit exigé (ex. 'GERER_PARAMETRAGE')
 * La vraie sécurité reste côté serveur (policies Laravel) : la garde évite seulement
 * d'afficher une page que l'utilisateur ne pourra pas utiliser.
 */
export async function authGuard(to) {
  // En développement uniquement, tant que la connexion (SCRUM-69/70) n'existe pas :
  // VITE_SANS_CONNEXION=true dans frontend/.env désactive la garde. Ignoré en production.
  if (import.meta.env.DEV && import.meta.env.VITE_SANS_CONNEXION === 'true') return true

  const auth = useAuthStore()

  // Après un rechargement de page, on a le jeton (localStorage) mais pas encore l'utilisateur.
  if (auth.token && !auth.user) {
    try {
      await auth.fetchMe()
    } catch (error) {
      // 401 : jeton expiré, http.js a déjà vidé la session.
      // Autre erreur (API injoignable) : on garde le jeton, l'utilisateur pourra réessayer.
      if (error?.response?.status === 401) auth.clearSession()
    }
  }

  if (to.meta.auth && !auth.isAuthenticated) {
    return { name: 'connexion', query: { redirect: to.fullPath } }
  }

  if (to.meta.invite && auth.isAuthenticated) {
    return { name: 'home' }
  }

  if (to.meta.profil && auth.user?.profil !== to.meta.profil) {
    return { name: 'forbidden' }
  }

  if (to.meta.droit && !auth.hasDroit(to.meta.droit)) {
    return { name: 'forbidden' }
  }

  return true
}
