import http from '@/api/http'

/*
 * SCRUM-137 : pièces jointes de message ou d'annonce.
 *
 * Le jeton Sanctum est ajouté par l'intercepteur d'Axios : les fichiers passent
 * donc toujours par l'API, jamais par une URL publique du bucket. Les réponses
 * sont des Blob pour pouvoir afficher les images avec `URL.createObjectURL`.
 */

/** Télécharge la pièce jointe (réponse en pièce jointe). */
export async function telechargerFichier(pieceId) {
  const { data } = await http.get(`/fichiers/${pieceId}/telecharger`, {
    responseType: 'blob',
  })
  return data
}

/** Charge l'aperçu d'une image (réponse inline côté API). */
export async function apercuFichier(pieceId) {
  const { data } = await http.get(`/fichiers/${pieceId}/telecharger`, {
    params: { apercu: 1 },
    responseType: 'blob',
  })
  return data
}
