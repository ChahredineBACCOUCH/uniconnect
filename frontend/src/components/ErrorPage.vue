<script setup>
/*
 * Page d'erreur commune (maquette E00) : utilisée par la page 403 et la page 404.
 */
defineProps({
  code: { type: String, required: true },
  title: { type: String, required: true },
  text: { type: String, required: true },
  hint: { type: String, default: '' },
  icon: { type: String, required: true },
  tone: { type: String, default: 'primary' },
})
</script>

<template>
  <v-app>
    <v-main class="error-page">
      <div class="error-page__card">
        <span class="error-page__icon" :class="`error-page__icon--${tone}`">
          <v-icon :icon="icon" size="44" />
        </span>
        <p class="error-page__code" :class="`text-${tone}`">{{ code }}</p>
        <h1 class="error-page__title">{{ title }}</h1>
        <p class="error-page__text">{{ text }}</p>
        <p v-if="hint" class="error-page__hint">{{ hint }}</p>
        <v-btn color="primary" prepend-icon="mdi-home-outline" :to="{ name: 'home' }">
          Retour à l'accueil
        </v-btn>
      </div>
    </v-main>
  </v-app>
</template>

<style scoped>
.error-page {
  display: grid;
  place-items: center;
  min-height: 100vh;
  padding: 48px 16px;
  background: rgb(var(--v-theme-background));
}

.error-page__card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  max-width: 520px;
  text-align: center;
}

.error-page__icon {
  display: grid;
  place-items: center;
  width: 88px;
  height: 88px;
  border-radius: 999px;
}

.error-page__icon--warning {
  background: #fdebd9;
  color: #8a4100;
}

.error-page__icon--primary {
  background: rgb(var(--v-theme-primary-soft));
  color: rgb(var(--v-theme-primary));
}

.error-page__code {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.error-page__title {
  margin: 0;
  font-size: 26px;
  line-height: 32px;
  font-weight: 500;
}

.error-page__text,
.error-page__hint {
  margin: 0;
  color: rgb(var(--v-theme-on-surface-variant));
}

.error-page__hint {
  font-size: 13px;
}
</style>
