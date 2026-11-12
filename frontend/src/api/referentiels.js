import http from '@/api/http'

/*
 * SCRUM-80 : appels des cinq référentiels académiques de l'écran A02.
 * Les listes Laravel sont enveloppées dans `{ data, meta }`.
 */

/** Liste paginée : `{ data, meta: { page, par_page, total } }`. */
export async function recupererReferentiel(endpoint, params = {}) {
  const { data } = await http.get(endpoint, { params })
  return data
}

export async function creerReferentiel(endpoint, payload) {
  const { data } = await http.post(endpoint, payload)
  return data.data
}

export async function modifierReferentiel(endpoint, id, payload) {
  const { data } = await http.patch(`${endpoint}/${id}`, payload)
  return data.data
}

export async function desactiverReferentiel(endpoint, id) {
  const { data } = await http.patch(`${endpoint}/${id}/desactiver`)
  return data.data
}

export async function supprimerReferentiel(endpoint, id) {
  await http.delete(`${endpoint}/${id}`)
}

/**
 * Éléments proposés dans les sélecteurs du formulaire : actifs uniquement,
 * sans pagination visible (100 lignes, la limite de l'API).
 */
export async function recupererOptions(endpoint) {
  const reponse = await recupererReferentiel(endpoint, { actif: 1, par_page: 100 })
  return reponse.data ?? []
}

/** Années universitaires : `GET /admin/annees` n'est pas paginé. */
export async function recupererAnnees() {
  const { data } = await http.get('/admin/annees')
  return data.data ?? []
}

/** Enseignants actifs proposés pour les matières. */
export async function recupererEnseignants() {
  const { data } = await http.get('/admin/matieres/enseignants')
  return data.data ?? []
}
