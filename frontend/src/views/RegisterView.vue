<template>
  <div class="auth-view">
    <div>
      <h2 class="text-h5 font-weight-medium">Création de compte</h2>
      <p class="auth-view__lead">
        Créez votre compte avec votre adresse électronique institutionnelle.
      </p>
    </div>

    <v-alert type="info" variant="tonal" density="comfortable">
      Le formulaire d'inscription sera développé dans le ticket SCRUM-64.
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
