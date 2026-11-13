import http from '@/api/http'

/*
 * SCRUM-122 : notifications de l'utilisateur connecté (API livrée par SCRUM-121).
 *
 * GET   /notifications              → { data: [...], meta: { non_lues, ... } }
 * PATCH /notifications/{id}/lue     → 204
 * POST  /notifications/tout-lire    → 204
 */
export const NOTIFICATION_ROUTES = {
  liste: '/notifications',
  lue: (id) => `/notifications/${id}/lue`,
  toutLire: '/notifications/tout-lire',
}

/**
 * Liste des notifications, les plus récentes d'abord.
 *
 * @param {{ non_lues?: boolean, par_page?: number, page?: number }} params
 * @returns {Promise<{ notifications: object[], nonLues: number }>}
 */
export async function recupererNotifications(params = {}) {
  const { data } = await http.get(NOTIFICATION_ROUTES.liste, { params })

  return {
    notifications: data.data ?? [],
    nonLues: data.meta?.non_lues ?? 0,
  }
}

export async function marquerNotificationLue(id) {
  await http.patch(NOTIFICATION_ROUTES.lue(id))
}

export async function toutMarquerLu() {
  await http.post(NOTIFICATION_ROUTES.toutLire)
}
