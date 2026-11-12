/*
 * SCRUM-73 : affichage des années universitaires de l'écran A01.
 */

/** Date ISO `AAAA-MM-JJ` → `JJ/MM/AAAA` (format de la maquette). */
export function formaterDate(date) {
  if (!date) return '—'

  const [annee, mois, jour] = String(date).split('-')
  if (!annee || !mois || !jour) return String(date)

  return `${jour}/${mois}/${annee}`
}

/**
 * Colonne « Groupes organisationnels » : « — » si l'année n'a aucun groupe,
 * sinon le total, complété par le nombre d'archivés s'il y en a.
 */
export function libelleGroupes(annee) {
  const total = Number(annee?.nombre_groupes ?? 0)
  if (!total) return '—'

  const archives = Number(annee?.nombre_groupes_archives ?? 0)
  if (!archives) return String(total)
  if (archives >= total) return `${total} (archivés)`

  return `${total} (dont ${archives} archivés)`
}

/** Chip d'état de l'année : verte si active, neutre sinon. */
export function detailEtat(annee) {
  return annee?.active
    ? { libelle: 'Active', color: 'success' }
    : { libelle: 'Inactive', color: null }
}
