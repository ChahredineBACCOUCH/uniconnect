import http from '@/api/http'

/*
 * SCRUM-77 : appels de l'écran A03 (comptes autorisés).
 * Les listes Laravel sont enveloppées dans `{ data, meta }`.
 */

const BASE = '/admin/comptes-autorises'

/** Liste paginée : `{ data, meta: { page, par_page, total } }`. */
export async function recupererComptes(params = {}) {
  const { data } = await http.get(BASE, { params })
  return data
}

export async function creerCompte(payload) {
  const { data } = await http.post(BASE, payload)
  return data.data
}

export async function modifierCompte(id, payload) {
  const { data } = await http.patch(`${BASE}/${id}`, payload)
  return data.data
}

export async function desactiverCompte(id) {
  const { data } = await http.post(`${BASE}/${id}/desactiver`)
  return data.data
}

export async function supprimerCompte(id) {
  await http.delete(`${BASE}/${id}`)
}

/** Classes actives de l'année universitaire active (filtres et dialogue). */
export async function recupererClasses() {
  const { data } = await http.get(`${BASE}/classes`)
  return data.data
}

/** Analyse du fichier Excel sans rien enregistrer. */
export async function analyserImport(fichier) {
  const formulaire = new FormData()
  formulaire.append('fichier', fichier)

  const { data } = await http.post(`${BASE}/import/apercu`, formulaire)
  return data
}

/** Enregistre les lignes validées par l'aperçu. */
export async function validerImport(lignes, fichierNom = null) {
  const { data } = await http.post(`${BASE}/import/valider`, {
    lignes,
    fichier_nom: fichierNom,
  })
  return data
}

/** Télécharge le modèle Excel fourni par l'API. */
export async function telechargerModele() {
  const { data } = await http.get(`${BASE}/import/modele`, { responseType: 'blob' })

  const url = URL.createObjectURL(data)
  const lien = document.createElement('a')
  lien.href = url
  lien.download = 'modele-import-comptes.xlsx'
  document.body.appendChild(lien)
  lien.click()
  lien.remove()
  URL.revokeObjectURL(url)
}
