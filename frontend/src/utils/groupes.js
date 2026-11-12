/*
 * SCRUM-83 : affichage de l'écran A06 (groupes organisationnels).
 * Fonctions pures pour rester faciles à tester.
 */

/** Accorde un mot selon le nombre (0 et 1 au singulier, au-delà au pluriel). */
export function pluriel(nombre, singulier, plurielMot) {
  return Number(nombre) > 1 ? plurielMot : singulier
}

/** « Classe » ou « Matière », tel que l'affiche la maquette A06. */
export function libelleTypeGroupe(groupe) {
  return groupe?.type === 'MATIERE' ? 'Matière' : 'Classe'
}

/**
 * Classes couvertes par le groupe : la classe elle-même pour un groupe de
 * classe, toutes les classes de l'année pour un groupe de matière.
 */
export function libelleClasses(groupe) {
  const classes = groupe?.classes ?? []
  if (!classes.length) return '—'

  return classes.map((classe) => classe.code ?? classe.libelle).join(', ')
}

/** Nom complet du propriétaire, ou « — » s'il n'y en a pas. */
export function libelleProprietaire(groupe) {
  return groupe?.proprietaire?.nom_complet || '—'
}

/** Chip d'état d'un groupe : vert si actif, neutre si archivé. */
export function detailEtatGroupe(groupe) {
  return groupe?.etat === 'ARCHIVE'
    ? { libelle: 'Archivé', color: null }
    : { libelle: 'Actif', color: 'success' }
}

/** Compteurs du dialogue de génération (valeurs par défaut sûres). */
export function resumeGeneration(apercu) {
  return {
    aCreer: apercu?.a_creer?.length ?? 0,
    existants: Number(apercu?.deja_existants ?? 0),
    membres: Number(apercu?.membres_a_ajouter ?? 0),
  }
}

/** Phrase du dialogue : « 3 groupes à créer, 6 existants ». */
export function texteGeneration(apercu) {
  const { aCreer, existants } = resumeGeneration(apercu)

  return `${aCreer} ${pluriel(aCreer, 'groupe', 'groupes')} à créer, ${existants} ${pluriel(
    existants,
    'existant',
    'existants',
  )}`
}

/** Libellé du bouton de confirmation : « Générer 3 groupes ». */
export function texteBoutonGeneration(apercu) {
  const { aCreer } = resumeGeneration(apercu)
  return `Générer ${aCreer} ${pluriel(aCreer, 'groupe', 'groupes')}`
}

/** Message de la snackbar après une génération réussie. */
export function messageGeneration(resultat) {
  const crees = Number(resultat?.crees ?? 0)
  const membres = Number(resultat?.membres_ajoutes ?? 0)
  const ajoutes = `${membres} ${pluriel(membres, 'membre ajouté', 'membres ajoutés')}`

  if (crees === 0) {
    return `Aucun groupe à créer. ${ajoutes}.`
  }

  return `${crees} ${pluriel(crees, 'groupe créé', 'groupes créés')} et ${ajoutes}.`
}

/** Message de la snackbar après une synchronisation de groupe. */
export function messageSynchronisation(resultat, nom) {
  const ajoutes = Number(resultat?.membres_ajoutes ?? 0)

  if (ajoutes === 0) {
    return `« ${nom} » est déjà à jour.`
  }

  return `${ajoutes} ${pluriel(ajoutes, 'membre ajouté', 'membres ajoutés')} à « ${nom} ».`
}
