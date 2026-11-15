<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

import EmptyState from '@/components/EmptyState.vue'

/* Pages prévues dans la prochaine version (channels, recherche, profil). */
const route = useRoute()
const title = computed(() => route.meta.title ?? 'Page')
</script>

<template>
  <div class="placeholder-page">
    <h1 class="text-h5 font-weight-medium">{{ title }}</h1>

    <v-card variant="outlined">
      <EmptyState
        icone="mdi-progress-wrench"
        titre="Bientôt disponible"
        texte="Cette fonctionnalité arrive dans la prochaine version d'UniConnect."
      >
        <v-btn variant="text" color="primary" class="mt-2" :to="{ name: 'home' }">
          Retour à l'accueil
        </v-btn>
      </EmptyState>
    </v-card>
  </div>
</template>

<style scoped>
.placeholder-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.placeholder-page h1 {
  margin: 0;
}
</style>
