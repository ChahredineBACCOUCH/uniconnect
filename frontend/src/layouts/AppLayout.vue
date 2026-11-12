<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

import EspaceList from '@/components/EspaceList.vue'
import { useAuthStore } from '@/stores/auth'
import { useEspacesStore } from '@/stores/espaces'
import { initiales, nomComplet, sousTitreProfil } from '@/utils/utilisateur'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const espaces = useEspacesStore()
const { mdAndUp, xs } = useDisplay()

/*
 * Le menu latéral est permanent à partir de md (>= 960 px).
 * En dessous, il devient temporaire et s'ouvre avec le bouton menu.
 */
const drawer = ref(false)
watch(
  mdAndUp,
  (isDesktop) => {
    drawer.value = isDesktop
  },
  { immediate: true },
)

const search = ref('')
// Utilisateur connecté (GET /me), et non plus le personnage de la maquette.
const user = computed(() => ({
  initials: initiales(auth.user),
  name: nomComplet(auth.user),
  role: sousTitreProfil(auth.user),
}))
// Même droit que la route /espaces/nouveau et POST /espaces (sinon page 403).
const peutCreerEspace = computed(() => auth.hasDroit('CREER_GROUPE_PRIVE'))

// Invitations en attente (GET /me/invitations) : seules notifications disponibles
// tant que l'API des notifications (SCRUM-121) n'existe pas.
const nbInvitations = computed(() => espaces.invitations.length)

const navHaut = computed(() => [
  { title: 'Accueil', icon: 'mdi-home-outline', to: '/', exact: true },
  {
    title: 'Notifications',
    icon: 'mdi-bell-outline',
    to: '/notifications',
    badge: nbInvitations.value,
  },
])

const navBas = [
  { title: 'Explorer les channels', icon: 'mdi-compass-outline', to: '/channels' },
  { title: 'Recherche', icon: 'mdi-magnify', to: '/recherche' },
  { title: 'Mon profil', icon: 'mdi-account-circle-outline', to: '/profil' },
]

// Espaces du menu : chargés dès que la session est connue, vidés à la déconnexion.
watch(
  () => auth.user?.id,
  (id) => {
    if (id) espaces.charger()
    else espaces.vider()
  },
  { immediate: true },
)

// À chaque changement de page : compteurs de non-lus, espace créé, rejoint ou quitté.
watch(
  () => route.path,
  () => {
    if (auth.user?.id && espaces.charge) espaces.charger({ discret: true })
  },
)

const bottomNavItems = [
  { label: 'Accueil', icon: 'mdi-home-outline', to: '/' },
  { label: 'Espaces', icon: 'mdi-account-group-outline', to: '/espaces' },
  { label: 'Channels', icon: 'mdi-compass-outline', to: '/channels' },
  { label: 'Notifs', icon: 'mdi-bell-outline', to: '/notifications' },
  { label: 'Profil', icon: 'mdi-account-circle-outline', to: '/profil' },
]

const activeBottomNav = computed(() => {
  if (route.path === '/') return '/'
  return bottomNavItems.find((item) => item.to !== '/' && route.path.startsWith(item.to))?.to ?? ''
})

function submitSearch() {
  const query = search.value.trim()
  if (query.length < 2) return
  router.push({ path: '/recherche', query: { q: query } })
}

async function logout() {
  await auth.logout()
  router.push({ name: 'connexion' })
}

function onBottomNavChange(value) {
  if (typeof value === 'string' && value) {
    router.push(value)
  }
}
</script>

<template>
  <v-app>
    <v-app-bar flat height="64" color="surface" class="app-bar">
      <v-app-bar-nav-icon v-if="!mdAndUp" aria-label="Ouvrir le menu" @click="drawer = !drawer" />

      <router-link to="/" class="brand" aria-label="UniConnect, accueil">
        <span class="brand-mark">
          <v-icon icon="mdi-school" size="22" />
        </span>
        <span class="brand-name">UniConnect</span>
      </router-link>

      <v-text-field
        v-if="!xs"
        v-model="search"
        class="app-bar-search"
        variant="solo-filled"
        density="compact"
        flat
        rounded="pill"
        hide-details
        bg-color="surface-variant"
        prepend-inner-icon="mdi-magnify"
        placeholder="Rechercher un espace, une personne, un fichier…"
        aria-label="Rechercher un espace, une personne, un fichier"
        @keyup.enter="submitSearch"
      />

      <v-spacer />

      <v-btn
        icon
        variant="text"
        color="on-surface-variant"
        :aria-label="
          nbInvitations
            ? `Notifications, ${nbInvitations} invitation${nbInvitations > 1 ? 's' : ''} en attente`
            : 'Notifications'
        "
        to="/notifications"
      >
        <v-badge
          :model-value="nbInvitations > 0"
          :content="nbInvitations"
          color="error"
          offset-x="2"
          offset-y="2"
        >
          <v-icon icon="mdi-bell-outline" size="22" />
        </v-badge>
      </v-btn>

      <v-btn
        variant="text"
        color="on-surface"
        class="app-bar-user text-none"
        height="48"
        rounded="pill"
        to="/profil"
      >
        <v-avatar color="primary" size="40">
          <span class="text-caption font-weight-bold">{{ user.initials }}</span>
        </v-avatar>
        <span v-if="mdAndUp" class="user-text">
          <span class="user-name">{{ user.name }}</span>
          <span class="user-role">{{ user.role }}</span>
        </span>
      </v-btn>

      <v-btn
        v-if="!xs"
        icon
        variant="text"
        color="on-surface-variant"
        aria-label="Se déconnecter"
        @click="logout"
      >
        <v-icon icon="mdi-logout" size="22" />
      </v-btn>
    </v-app-bar>

    <v-navigation-drawer
      v-model="drawer"
      :permanent="mdAndUp"
      :temporary="!mdAndUp"
      width="288"
      class="app-drawer"
    >
      <div class="drawer-content">
        <v-btn
          v-if="peutCreerEspace"
          block
          color="primary"
          variant="flat"
          prepend-icon="mdi-plus"
          to="/espaces/nouveau"
          height="40"
          class="mb-2"
        >
          Créer un espace
        </v-btn>

        <v-list nav density="comfortable" class="drawer-nav">
          <v-list-item
            v-for="item in navHaut"
            :key="item.to"
            :to="item.to"
            :exact="item.exact"
            :prepend-icon="item.icon"
            :title="item.title"
          >
            <template v-if="item.badge" #append>
              <span class="nav-badge">{{ item.badge }}</span>
            </template>
          </v-list-item>

          <EspaceList />

          <v-divider class="my-2" />
          <v-list-item
            v-for="item in navBas"
            :key="item.to"
            :to="item.to"
            :exact="item.exact"
            :prepend-icon="item.icon"
            :title="item.title"
          >
            <template v-if="item.badge" #append>
              <span class="nav-badge">{{ item.badge }}</span>
            </template>
          </v-list-item>
        </v-list>
      </div>
    </v-navigation-drawer>

    <v-main>
      <div class="app-content">
        <router-view />
      </div>
    </v-main>

    <v-bottom-navigation
      v-if="xs"
      :model-value="activeBottomNav"
      grow
      height="64"
      color="primary"
      class="app-bottom-nav"
      @update:model-value="onBottomNavChange"
    >
      <v-btn
        v-for="item in bottomNavItems"
        :key="item.to"
        :value="item.to"
        variant="text"
        class="bottom-nav-btn"
      >
        <v-icon :icon="item.icon" size="22" />
        <span>{{ item.label }}</span>
      </v-btn>
    </v-bottom-navigation>
  </v-app>
</template>

<style scoped>
.app-bar {
  border-bottom: 1px solid rgb(var(--v-theme-outline));
}

.app-bar :deep(.v-toolbar__content) {
  gap: 8px;
  padding-inline: 12px 16px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: none;
  color: rgb(var(--v-theme-primary));
  text-decoration: none;
}

.brand-name {
  font-size: 20px;
  font-weight: 700;
  white-space: nowrap;
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgb(var(--v-theme-primary));
  color: #fff;
}

.app-bar-search {
  flex: 0 1 520px;
  max-width: 520px;
  margin-inline-start: 16px;
}

.app-bar-search :deep(.v-field) {
  border-radius: 999px;
}

.user-text {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.2;
  margin-inline-start: 10px;
}

.user-name {
  font-size: 14px;
  font-weight: 500;
}

.user-role {
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.app-drawer {
  border-right: 1px solid rgb(var(--v-theme-outline));
}

.drawer-content {
  display: flex;
  flex-direction: column;
  padding: 16px 12px;
}

.drawer-nav :deep(.v-list-item) {
  min-height: 44px;
  color: rgb(var(--v-theme-on-surface));
}

.drawer-nav :deep(.v-list-item .v-icon) {
  color: rgb(var(--v-theme-on-surface-variant));
}

.drawer-nav :deep(.v-list-item--active) {
  background: rgb(var(--v-theme-primary-soft));
  color: rgb(var(--v-theme-primary));
  font-weight: 500;
}

.drawer-nav :deep(.v-list-item--active .v-icon) {
  color: rgb(var(--v-theme-primary));
}

.drawer-subheader {
  padding-inline: 12px;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.125em;
  text-transform: uppercase;
  color: rgb(var(--v-theme-on-surface-variant));
}

.nav-badge {
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgb(var(--v-theme-primary));
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  line-height: 20px;
  text-align: center;
}

.app-content {
  padding: 28px 32px 40px;
}

.app-bottom-nav {
  border-top: 1px solid rgb(var(--v-theme-outline));
  background: rgb(var(--v-theme-surface));
}

.bottom-nav-btn {
  min-width: 0;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
  text-transform: none;
  color: rgb(var(--v-theme-on-surface-variant));
}

.bottom-nav-btn.v-btn--active,
.bottom-nav-btn.v-btn--selected {
  color: rgb(var(--v-theme-primary));
  font-weight: 500;
}

@media (max-width: 959px) {
  .app-content {
    padding: 20px 16px 24px;
  }
}
</style>
