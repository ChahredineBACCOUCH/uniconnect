import http from '@/api/http'

/*
 * SCRUM-95 : appels de l'écran d'un espace et de sa discussion.
 * Les réponses Laravel sont enveloppées dans `{ data: … }` par les Resources.
 */

export async function recupererEspace(id) {
  const { data } = await http.get(`/espaces/${id}`)
  return data.data
}

export async function recupererMessages(espaceId, params = {}) {
  const { data } = await http.get(`/espaces/${espaceId}/messages`, { params })
  return data
}

export async function envoyerMessage(espaceId, payload) {
  const { data } = await http.post(`/espaces/${espaceId}/messages`, payload)
  return data.data
}

export async function modifierMessage(messageId, contenu) {
  const { data } = await http.patch(`/messages/${messageId}`, { contenu })
  return data.data
}

export async function supprimerMessage(messageId) {
  await http.delete(`/messages/${messageId}`)
}
