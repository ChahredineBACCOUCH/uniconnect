import { createRouter, createWebHistory } from 'vue-router'

import AppLayout from '../layouts/AppLayout.vue'
import AuthLayout from '../layouts/AuthLayout.vue'
import HomeView from '../views/HomeView.vue'
import PlaceholderView from '../views/PlaceholderView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: AppLayout,
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
          meta: { title: 'Créer un espace' },
        },
        {
          path: 'espaces/:id/:onglet?',
          name: 'espace',
          component: PlaceholderView,
          meta: { title: 'Espace' },
        },
        {
          path: 'channels',
          name: 'channels',
          component: PlaceholderView,
          meta: { title: 'Channels' },
        },
        {
          path: 'notifications',
          name: 'notifications',
          component: PlaceholderView,
          meta: { title: 'Notifications' },
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
      ],
    },
    {
      path: '/',
      component: AuthLayout,
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
  ],
})

export default router
