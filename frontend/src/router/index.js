import { createRouter, createWebHistory } from 'vue-router'

import AppLayout from '../layouts/AppLayout.vue'
import AuthLayout from '../layouts/AuthLayout.vue'
import HomeView from '../views/HomeView.vue'
import EspaceView from '../views/espaces/EspaceView.vue'
import { authGuard } from './guards'

/*
 * Règles d'accès (lues par la garde de ./guards.js) :
 *   meta.auth   → connecté obligatoire (posé sur AppLayout, donc hérité par toutes ses pages)
 *   meta.invite → réservé aux visiteurs (pages de AuthLayout)
 *   meta.profil / meta.droit → pages d'administration
 */
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: AppLayout,
      meta: { auth: true },
      children: [
        {
          path: '',
          name: 'home',
          component: HomeView,
        },
        {
          path: 'espaces',
          name: 'espaces',
          component: () => import('../views/EspacesView.vue'),
          meta: { title: 'Mes espaces' },
        },
        {
          path: 'espaces/nouveau',
          name: 'espace-nouveau',
          component: () => import('../views/espaces/CreerEspaceView.vue'),
          meta: { title: 'Créer un groupe privé', droit: 'CREER_GROUPE_PRIVE' },
        },
        {
          path: 'espaces/:id/:onglet?',
          name: 'espace',
          component: EspaceView,
          meta: { title: 'Espace' },
        },
        {
          path: 'notifications',
          name: 'notifications',
          component: () => import('../views/NotificationsView.vue'),
          meta: { title: 'Notifications' },
        },
        // Annuaire des channels, recherche et profil arrivent en v1.1 : ils ne sont pas
        // affichés en v1.0. Un ancien lien renvoie simplement à l'accueil.
        { path: 'channels', redirect: { name: 'home' } },
        { path: 'recherche', redirect: { name: 'home' } },
        { path: 'profil', redirect: { name: 'home' } },
        // Administration : profil ADMINISTRATION + droit demandé (voir docs/api.md, section 9).
        {
          path: 'admin/annees',
          name: 'admin-annees',
          component: () => import('../views/admin/AnneesView.vue'),
          meta: {
            title: 'Années universitaires',
            profil: 'ADMINISTRATION',
            droit: 'GERER_PARAMETRAGE',
          },
        },
        {
          path: 'admin/referentiels',
          name: 'admin-referentiels',
          component: () => import('../views/admin/ReferentielsView.vue'),
          meta: { title: 'Référentiels', profil: 'ADMINISTRATION', droit: 'GERER_PARAMETRAGE' },
        },
        {
          path: 'admin/comptes',
          name: 'admin-comptes',
          component: () => import('../views/admin/ComptesView.vue'),
          meta: {
            title: 'Comptes autorisés',
            profil: 'ADMINISTRATION',
            droit: 'GERER_UTILISATEURS',
          },
        },
        {
          path: 'admin/groupes',
          name: 'admin-groupes',
          component: () => import('../views/admin/GroupesView.vue'),
          meta: {
            title: 'Groupes organisationnels',
            profil: 'ADMINISTRATION',
            droit: 'CREER_GROUPE_ORGA',
          },
        },
      ],
    },
    {
      path: '/',
      component: AuthLayout,
      meta: { invite: true },
      children: [
        {
          path: 'connexion',
          name: 'connexion',
          component: () => import('../views/auth/ConnexionView.vue'),
        },
        {
          path: 'inscription',
          name: 'inscription',
          component: () => import('../views/auth/InscriptionView.vue'),
        },
        {
          path: 'activation/:id',
          name: 'activation',
          component: () => import('../views/auth/ActivationView.vue'),
        },
      ],
    },
    {
      path: '/403',
      name: 'forbidden',
      component: () => import('../views/ForbiddenView.vue'),
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach(authGuard)

export default router
