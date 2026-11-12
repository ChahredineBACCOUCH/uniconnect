import http from '@/api/http'

/*
 * SCRUM-83 : appels de l'écran A06 (groupes organisationnels).
 * Les listes ne sont pas paginées : l'année active compte quelques dizaines
 * de groupes.
 */

const BASE = '/admin/groupes'

/** Retire les filtres vides pour que l'API ne reçoive que les choix réels. */
function parametres(filtres = {}) {
  return Object.fromEntries(
    Object.entries(filtres).filter(
      ([, valeur]) => valeur !== null && valeur !== undefined && valeur !== '',
    ),
  )
}

/** Liste des groupes de l'année active : `{ annee, groupes, total }`. */
export async function recupererGroupes(filtres = {}) {
  const { data } = await http.get(BASE, { params: parametres(filtres) })

  return {
    annee: data.annee ?? null,
    groupes: data.data ?? [],
    total: data.total ?? 0,
  }
}

/** Options des filtres de l'année active (filières, niveaux, classes, enseignants). */
export async function recupererFiltresGroupes() {
  const { data } = await http.get(`${BASE}/filtres`)

  return {
    annee: data.annee ?? null,
    filieres: data.filieres ?? [],
    niveaux: data.niveaux ?? [],
    classes: data.classes ?? [],
    enseignants: data.enseignants ?? [],
  }
}

/** Aperçu de la génération, sans rien écrire (422 sans année active). */
export async function apercuGeneration() {
  const { data } = await http.get(`${BASE}/apercu`)
  return data
}

/** Crée les groupes manquants et complète leurs membres (idempotent). */
export async function genererGroupes() {
  const { data } = await http.post(`${BASE}/generer`)
  return data
}

/** Relance le rattachement des membres d'un groupe. */
export async function synchroniserGroupe(id) {
  const { data } = await http.post(`${BASE}/${id}/synchroniser`)
  return data
}

/** Archive un groupe (idempotent). */
export async function archiverGroupe(id) {
  const { data } = await http.post(`${BASE}/${id}/archiver`)
  return data
}
