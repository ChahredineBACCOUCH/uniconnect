import { libelleProfil } from '@/utils/espaces'

/*
 * Affichage de l'utilisateur connecté (objet `Utilisateur` de GET /me, docs/api.md).
 * Toutes les fonctions acceptent `null` (pendant le chargement de la session).
 */

export function nomComplet(user) {
  return [user?.prenom, user?.nom].filter(Boolean).join(' ')
}

export function initiales(user) {
  const lettres = [user?.prenom, user?.nom]
    .filter(Boolean)
    .map((partie) => partie.trim().charAt(0).toUpperCase())
    .join('')

  return lettres || '?'
}

// « Étudiant · L2 Info – Groupe A », « Enseignant », « Administration ».
export function sousTitreProfil(user) {
  if (!user?.profil) return ''

  return [libelleProfil(user.profil), user.classe?.libelle].filter(Boolean).join(' · ')
}
