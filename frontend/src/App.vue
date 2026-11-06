<script setup>
import { ref } from 'vue'

const email = ref('')
</script>

<template>
  <v-app>
    <v-main class="test-shell">
      <v-container class="d-flex align-center justify-center" style="min-height: 100vh">
        <div class="test-panel">
          <div class="text-center mb-8">
            <v-avatar color="primary" size="56" rounded="lg" class="mb-4">
              <v-icon icon="mdi-school" size="30" color="on-primary" />
            </v-avatar>
            <h1 class="text-h4 font-weight-bold text-primary">UniConnect</h1>
            <p class="text-body-1 text-medium-emphasis mt-2">
              Page de test du thème Vuetify — <code>uniconnectLight</code>
            </p>
          </div>

          <v-card>
            <v-card-item class="pt-6 px-6">
              <v-card-title class="text-h6 font-weight-bold">Connexion</v-card-title>
              <v-card-subtitle class="mt-1">
                Vérification des couleurs et styles par défaut
              </v-card-subtitle>
            </v-card-item>

            <v-card-text class="px-6 pb-6">
              <v-text-field
                v-model="email"
                label="Adresse e-mail"
                placeholder="prenom.nom@univ.fr"
                prepend-inner-icon="mdi-email-outline"
                class="mb-2"
              />

              <v-btn block size="large" color="primary" prepend-icon="mdi-login">
                Se connecter
              </v-btn>
            </v-card-text>
          </v-card>

          <div class="palette mt-8">
            <p class="text-caption text-uppercase font-weight-bold text-medium-emphasis mb-3">
              Palette uniconnectLight
            </p>
            <div class="d-flex flex-wrap ga-3">
              <v-chip color="primary" size="small">Primary</v-chip>
              <v-chip color="secondary" size="small">Secondary</v-chip>
              <v-chip color="accent" size="small">Accent</v-chip>
              <v-chip color="success" size="small">Success</v-chip>
              <v-chip color="warning" size="small">Warning</v-chip>
              <v-chip color="error" size="small">Error</v-chip>
              <v-chip color="info" size="small">Info</v-chip>
            </div>
          </div>
        </div>
      </v-container>
    </v-main>
  </v-app>
</template>

<style scoped>
.test-shell {
  background:
    radial-gradient(circle at top left, rgba(30, 79, 184, 0.12), transparent 36rem),
    radial-gradient(circle at bottom right, rgba(0, 121, 107, 0.1), transparent 30rem),
    rgb(var(--v-theme-background));
}

.test-panel {
  width: 100%;
  max-width: 460px;
}

code {
  color: rgb(var(--v-theme-primary));
  font-size: 0.85em;
}
</style>
