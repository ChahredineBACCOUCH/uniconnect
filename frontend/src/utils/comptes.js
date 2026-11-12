/*
 * SCRUM-77 : libellés et calculs de l'écran A03 (comptes autorisés).
 * Fonctions pures, faciles à tester.
 */

export const PROFILS = [
  { title: 'Étudiant', value: 'ETUDIANT' },
  { title: 'Enseignant', value: 'ENSEIGNANT' },
  { title: 'Administration', value: 'ADMINISTRATION' },
]

export const ETATS = [
  { title: 'Actif', value: 'ACTIF' },
  { title: "En attente d'activation", value: 'EN_ATTENTE' },
  { title: 'Non créé', value: 'NON_CREE' },
  { title: 'Désactivé', value: 'DESACTIVE' },
  { title: 'Suspendu', value: 'SUSPENDU' },
]

const DETAILS_PROFIL = {
  ETUDIANT: { libelle: 'Étudiant', couleur: 'primary' },
  ENSEIGNANT: { libelle: 'Enseignant', couleur: 'secondary' },
  ADMINISTRATION: { libelle: 'Administration', couleur: null },
}

const DETAILS_ETAT = {
  NON_CREE: { libelle: 'Non créé', couleur: null },
  EN_ATTENTE: { libelle: "En attente d'activation", couleur: 'warning' },
  ACTIF: { libelle: 'Actif', couleur: 'success' },
  DESACTIVE: { libelle: 'Désactivé', couleur: 'error' },
  SUSPENDU: { libelle: 'Suspendu', couleur: 'error' },
}

export function detailProfil(profil) {
  return DETAILS_PROFIL[profil] ?? { libelle: 'Membre', couleur: null }
}

export function detailEtat(etat) {
  return DETAILS_ETAT[etat] ?? { libelle: etat ?? 'Inconnu', couleur: null }
}

/**
 * Une ligne est comptée comme doublon quand le motif renvoyé par l'API
 * signale un matricule ou un e-mail déjà présent (dans le fichier ou en base).
 */
export function estDoublon(motif) {
  return /doublon|déjà|plusieurs fois/i.test(motif ?? '')
}

/**
 * Résumé de l'aperçu : `{ total, valides, doublons, erreurs, ignores }`.
 *
 * L'API renvoie toutes les lignes refusées dans `erreurs`, avec leur motif.
 * On sépare les doublons des autres erreurs pour afficher les trois compteurs
 * de la maquette (valides / doublons / erreurs).
 */
export function resumerImport(apercu) {
  const total = apercu?.total ?? 0
  const valides = apercu?.valides ?? 0
  const erreurs = apercu?.erreurs ?? []
  const doublons = erreurs.filter((erreur) => estDoublon(erreur.motif)).length

  return {
    total,
    valides,
    doublons,
    erreurs: erreurs.length - doublons,
    ignores: Math.max(total - valides, 0),
  }
}
