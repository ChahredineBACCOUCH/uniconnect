import { createRouter, createWebHistory } from 'vue-router'

import AppLayout from '../layouts/AppLayout.vue'
import AuthLayout from '../layouts/AuthLayout.vue'
import HomeView from '../views/HomeView.vue'
import EspaceView from '../views/espaces/EspaceView.vue'
import PlaceholderView from '../views/PlaceholderView.vue'
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
          component: PlaceholderView,
          meta: { title: 'Espaces' },
        },
        {
          path: 'espaces/nouveau',
          name: 'espace-nouveau',
          component: PlaceholderView,
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
          component: PlaceholderView,
          meta: { title: 'Notifications' },
        },
        // Pages v1.1 (après le 19/11) : gardées pour que les liens du menu ne cassent pas.
        {
          path: 'channels',
          name: 'channels',
          component: PlaceholderView,
          meta: { title: 'Channels' },
        },
        {
          path: 'recherche',
          name: 'recherche',
          component: PlaceholderView,
          meta: { title: 'Recherche' },
        },
        {
          path: 'profil',
          name: 'profil',
          component: PlaceholderView,
          meta: { title: 'Mon profil' },
        },
        // Administration : profil ADMINISTRATION + droit demandé (voir docs/api.md, section 9).
        {
          path: 'admin/annees',
          name: 'admin-annees',
          component: PlaceholderView,
          meta: {
            title: 'Années universitaires',
            profil: 'ADMINISTRATION',
            droit: 'GERER_PARAMETRAGE',
          },
        },
        {
          path: 'admin/referentiels',
          name: 'admin-referentiels',
          component: PlaceholderView,
          meta: { title: 'Référentiels', profil: 'ADMINISTRATION', droit: 'GERER_PARAMETRAGE' },
        },
        {
          path: 'admin/comptes',
          name: 'admin-comptes',
          component: PlaceholderView,
          meta: {
            title: 'Comptes autorisés',
            profil: 'ADMINISTRATION',
            droit: 'GERER_UTILISATEURS',
          },
        },
        {
          path: 'admin/groupes',
          name: 'admin-groupes',
          component: PlaceholderView,
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
          component: () => import('../views/LoginView.vue'),
        },
        {
          path: 'inscription',
          name: 'inscription',
          component: () => import('../views/RegisterView.vue'),
        },
        {
          path: 'activation/:token',
          name: 'activation',
          component: () => import('../views/ActivationView.vue'),
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
