import http from '@/api/http'

/*
 * SCRUM-73 : appels de l'écran A01 (années universitaires).
 * `GET /admin/annees` n'est pas paginé : la liste tient en une seule réponse.
 */

const BASE = '/admin/annees'

export async function recupererAnnees() {
  const { data } = await http.get(BASE)
  return data.data ?? []
}

export async function creerAnnee(payload) {
  const { data } = await http.post(BASE, payload)
  return data.data
}

export async function modifierAnnee(id, payload) {
  const { data } = await http.patch(`${BASE}/${id}`, payload)
  return data.data
}

/** Active une année ; `archiver_precedente` archive les groupes de l'ancienne. */
export async function activerAnnee(id, payload = {}) {
  const { data } = await http.post(`${BASE}/${id}/activer`, payload)
  return data.data
}
