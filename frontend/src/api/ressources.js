import http from '@/api/http'

/*
 * SCRUM-127 : chapitres et ressources pédagogiques d'un espace.
 * Les réponses Laravel sont enveloppées dans `{ data: … }` par les Resources.
 * Le téléchargement passe toujours par la route protégée : aucun lien direct
 * vers le stockage n'est exposé au navigateur.
 */

export async function recupererChapitres(espaceId) {
  const { data } = await http.get(`/espaces/${espaceId}/chapitres`)
  return data.data
}

export async function creerChapitre(espaceId, payload) {
  const { data } = await http.post(`/espaces/${espaceId}/chapitres`, payload)
  return data.data
}

export async function modifierChapitre(chapitreId, payload) {
  const { data } = await http.patch(`/chapitres/${chapitreId}`, payload)
  return data.data
}

export async function supprimerChapitre(chapitreId) {
  await http.delete(`/chapitres/${chapitreId}`)
}

/**
 * Ajoute une ressource à un chapitre (fichier OU lien).
 * Le corps est un FormData : Laravel lit les fichiers uniquement en multipart.
 */
export async function creerRessource(chapitreId, payload) {
  const { data } = await http.post(
    `/chapitres/${chapitreId}/ressources`,
    construireFormData(payload),
  )
  return data.data
}

/**
 * Renomme une ressource ou modifie sa description. Fournir un fichier ou un
 * lien remplace le contenu : dans ce cas on passe par un POST multipart avec
 * `_method=PATCH`, PHP ne parsant pas les fichiers sur un vrai PATCH.
 */
export async function modifierRessource(ressourceId, payload) {
  if (payload.fichier || payload.url !== undefined) {
    const donnees = construireFormData(payload)
    donnees.append('_method', 'PATCH')
    const { data } = await http.post(`/ressources/${ressourceId}`, donnees)
    return data.data
  }

  const { data } = await http.patch(`/ressources/${ressourceId}`, payload)
  return data.data
}

export async function deplacerRessource(ressourceId, chapitreId) {
  const { data } = await http.patch(`/ressources/${ressourceId}/deplacer`, {
    chapitre_id: chapitreId,
  })
  return data.data
}

export async function supprimerRessource(ressourceId) {
  await http.delete(`/ressources/${ressourceId}`)
}

/** Renvoie le fichier en Blob ; le nom est dérivé du titre côté écran. */
export async function telechargerRessource(ressourceId) {
  const { data } = await http.get(`/ressources/${ressourceId}/telecharger`, {
    responseType: 'blob',
  })
  return data
}

function construireFormData(payload) {
  const donnees = new FormData()

  for (const [cle, valeur] of Object.entries(payload)) {
    if (valeur === undefined || valeur === null || valeur === '') continue
    donnees.append(cle, valeur)
  }

  return donnees
}
