<script setup>
const stats = [
  {
    value: 12,
    label: 'messages non lus',
    icon: 'mdi-message-text-outline',
    background: '#E3EBFB',
    color: '#1E4FB8',
  },
  {
    value: 2,
    label: 'invitations en attente',
    icon: 'mdi-account-group-outline',
    background: '#DDF2EF',
    color: '#00695C',
  },
  {
    value: 5,
    label: 'nouvelles ressources',
    icon: 'mdi-folder-outline',
    background: '#FEF3DC',
    color: '#8A4100',
  },
  {
    value: 3,
    label: 'mentions',
    icon: 'mdi-at',
    background: '#E1F2FB',
    color: '#01579B',
  },
]
</script>

<template>
  <div class="home">
    <header class="home-head">
      <div class="home-head__text">
        <h1 class="text-h5 font-weight-medium">Bonjour Yassine</h1>
        <p class="home-head__subtitle">
          Lundi 13 octobre 2026 · Semestre 1 · Année 2026/2027
        </p>
      </div>
      <v-btn variant="outlined" color="primary" prepend-icon="mdi-compass-outline" to="/channels" height="40">
        Explorer les channels
      </v-btn>
    </header>

    <section class="home-stats" aria-label="Résumé">
      <v-card v-for="stat in stats" :key="stat.label" class="stat-card" variant="outlined">
        <span class="stat-card__icon" :style="{ background: stat.background, color: stat.color }">
          <v-icon :icon="stat.icon" size="22" />
        </span>
        <div>
          <p class="stat-card__value">{{ stat.value }}</p>
          <p class="stat-card__label">{{ stat.label }}</p>
        </div>
      </v-card>
    </section>

    <v-card variant="outlined" class="home-card">
      <v-card-title class="d-flex align-center ga-2">
        <v-icon icon="mdi-bullhorn-outline" color="primary" size="22" />
        <span class="text-h6 font-weight-medium">Dernières annonces</span>
      </v-card-title>
      <v-card-text>
        <div class="home-empty">
          <v-icon icon="mdi-inbox-outline" size="32" />
          <p>Le contenu de l'accueil sera ajouté dans le ticket dédié (SCRUM-86).</p>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.home {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.home-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.home-head__text {
  flex: 1;
  min-width: 0;
}

.home-head__subtitle {
  margin: 4px 0 0;
  color: rgb(var(--v-theme-on-surface-variant));
}

.home-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
}

.stat-card__icon {
  display: grid;
  place-items: center;
  flex: none;
  width: 44px;
  height: 44px;
  border-radius: 10px;
}

.stat-card__value {
  margin: 0;
  font-size: 24px;
  line-height: 32px;
  font-weight: 500;
}

.stat-card__label {
  margin: 0;
  font-size: 12px;
  line-height: 16px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.home-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px 0;
  text-align: center;
  color: rgb(var(--v-theme-on-surface-variant));
}

.home-empty p {
  margin: 0;
}

@media (max-width: 1100px) {
  .home-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 560px) {
  .home-stats {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
