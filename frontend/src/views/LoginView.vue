<template>
  <div class="auth-view">
    <div>
      <h2 class="text-h5 font-weight-medium">Connexion</h2>
      <p class="auth-view__lead">Connectez-vous avec votre adresse électronique institutionnelle.</p>
    </div>

    <v-alert type="info" variant="tonal" density="comfortable">
      Le formulaire de connexion sera développé dans le ticket SCRUM-70.
    </v-alert>

    <v-btn color="primary" variant="flat" block to="/">Retour à l'accueil</v-btn>
  </div>
</template>

<style scoped>
.auth-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.auth-view h2 {
  margin: 0;
}

.auth-view__lead {
  margin: 6px 0 0;
  color: rgb(var(--v-theme-on-surface-variant));
}
</style>
