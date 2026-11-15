<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

/*
 * SCRUM-184 : écran affiché pendant le premier chargement (vérification de la
 * session avec GET /me), au lieu d'une page blanche. L'API gratuite (Render)
 * peut mettre près d'une minute à se réveiller : après quelques secondes, on
 * l'explique à l'utilisateur.
 */
const lent = ref(false)
let minuteur = null

onMounted(() => {
  minuteur = window.setTimeout(() => {
    lent.value = true
  }, 5000)
})
onBeforeUnmount(() => window.clearTimeout(minuteur))
</script>

<template>
  <div class="chargement-app" role="status" aria-live="polite">
    <span class="chargement-app__logo" aria-hidden="true">
      <v-icon icon="mdi-school" size="32" />
    </span>
    <p class="chargement-app__titre">UniConnect</p>
    <v-progress-circular indeterminate color="primary" size="28" width="3" />
    <p class="chargement-app__texte">Chargement de votre espace…</p>
    <p v-if="lent" class="chargement-app__texte">
      Le serveur se réveille, cela peut prendre jusqu'à une minute.
    </p>
  </div>
</template>

<style scoped>
.chargement-app {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  min-height: 100vh;
  padding: 24px;
  text-align: center;
  background: rgb(var(--v-theme-background));
}

.chargement-app__logo {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: rgb(var(--v-theme-primary));
  color: #fff;
}

.chargement-app__titre {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: rgb(var(--v-theme-primary));
}

.chargement-app__texte {
  margin: 0;
  max-width: 360px;
  color: rgb(var(--v-theme-on-surface-variant));
}
</style>
