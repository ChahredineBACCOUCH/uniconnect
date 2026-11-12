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

/* SCRUM-89 : création d'un groupe privé et invitations. */

export async function creerEspace(payload) {
  const { data } = await http.post('/espaces', payload)
  return data.data
}

/** Personnes à inviter (comptes actifs, 10 au maximum, `q` d'au moins 2 caractères). */
export async function rechercherUtilisateurs(q) {
  const { data } = await http.get('/utilisateurs', { params: { q } })
  return data.data
}

/** → `{ creees, deja_membres, deja_invites }` */
export async function inviter(espaceId, userIds) {
  const { data } = await http.post(`/espaces/${espaceId}/invitations`, { user_ids: userIds })
  return data
}

export async function recupererInvitationsEspace(espaceId) {
  const { data } = await http.get(`/espaces/${espaceId}/invitations`)
  return data.data
}

export async function annulerInvitation(invitationId) {
  await http.delete(`/invitations/${invitationId}`)
}

/* SCRUM-91 : membres d'un espace. */

export async function recupererMembres(espaceId) {
  const { data } = await http.get(`/espaces/${espaceId}/membres`)
  return data.data
}

export async function changerRole(espaceId, userId, role) {
  const { data } = await http.patch(`/espaces/${espaceId}/membres/${userId}`, { role })
  return data.data
}

export async function retirerMembre(espaceId, userId) {
  await http.delete(`/espaces/${espaceId}/membres/${userId}`)
}

export async function transfererPropriete(espaceId, userId) {
  const { data } = await http.post(`/espaces/${espaceId}/transferer`, { user_id: userId })
  return data.data
}

export async function quitterEspace(espaceId) {
  await http.post(`/espaces/${espaceId}/quitter`)
}
