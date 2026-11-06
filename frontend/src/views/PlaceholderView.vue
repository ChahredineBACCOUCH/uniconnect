<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const title = computed(() => route.meta.title ?? 'Page')
</script>

<template>
  <div class="placeholder-page">
    <h1 class="text-h5 font-weight-medium">{{ title }}</h1>

    <v-card variant="outlined">
      <v-card-text>
        <div class="placeholder-page__empty">
          <v-icon icon="mdi-progress-wrench" size="36" />
          <p>Le contenu de cette page sera développé dans son ticket dédié.</p>
        </div>
      </v-card-text>
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

.placeholder-page__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 32px 0;
  text-align: center;
  color: rgb(var(--v-theme-on-surface-variant));
}

.placeholder-page__empty p {
  margin: 0;
}
</style>
