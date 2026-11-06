<script setup>
const features = [
  { icon: 'mdi-account-group-outline', label: 'Groupes de classe, groupes privés et channels' },
  { icon: 'mdi-timer-outline', label: 'Messages à durée de visibilité maîtrisée' },
  { icon: 'mdi-shield-check-outline', label: 'Accès limité selon votre profil et vos droits' },
]
</script>

<template>
  <v-app>
    <main class="auth-layout">
      <section class="auth-brand" aria-label="Présentation">
        <router-link to="/" class="auth-brand__logo">
          <span class="auth-brand__mark">
            <v-icon icon="mdi-school" size="28" />
          </span>
          <span class="auth-brand__name">UniConnect</span>
        </router-link>

        <div class="auth-brand__body">
          <h1 class="auth-brand__title">
            La communication de votre université, réunie au même endroit.
          </h1>
          <p class="auth-brand__lead hide-sm">
            Classes, matières, groupes privés et channels : échangez des messages et partagez vos
            documents en toute sécurité.
          </p>

          <ul class="auth-brand__features hide-sm">
            <li v-for="feature in features" :key="feature.label">
              <span class="auth-brand__feature-icon">
                <v-icon :icon="feature.icon" size="20" />
              </span>
              {{ feature.label }}
            </li>
          </ul>
        </div>

        <p class="auth-brand__footer hide-sm">Université · Année universitaire 2026/2027</p>
      </section>

      <section class="auth-form">
        <div class="auth-card">
          <router-view />
        </div>
      </section>
    </main>
  </v-app>
</template>

<style scoped>
.auth-layout {
  display: flex;
  flex-wrap: wrap;
  min-height: 100vh;
  background: rgb(var(--v-theme-background));
}

.auth-brand {
  flex: 0 0 50%;
  max-width: 50%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 40px;
  padding: 64px 72px;
  background: rgb(var(--v-theme-primary));
  color: #fff;
}

.auth-brand__logo {
  display: flex;
  align-items: center;
  gap: 12px;
  color: inherit;
  text-decoration: none;
}

.auth-brand__mark {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.16);
}

.auth-brand__name {
  font-size: 24px;
  font-weight: 700;
}

.auth-brand__body {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.auth-brand__title {
  max-width: 470px;
  margin: 0;
  font-size: 34px;
  line-height: 40px;
  font-weight: 500;
}

.auth-brand__lead {
  max-width: 470px;
  margin: 0;
  font-size: 16px;
  line-height: 24px;
  opacity: 0.88;
}

.auth-brand__features {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.auth-brand__features li {
  display: flex;
  align-items: center;
  gap: 14px;
  font-size: 16px;
}

.auth-brand__feature-icon {
  display: grid;
  place-items: center;
  flex: none;
  width: 40px;
  height: 40px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
}

.auth-brand__footer {
  margin: 0;
  font-size: 12px;
  opacity: 0.8;
}

.auth-form {
  flex: 0 0 50%;
  max-width: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 16px;
}

.auth-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  max-width: 480px;
  padding: 40px;
  border: 1px solid rgb(var(--v-theme-outline));
  border-radius: 16px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 8px 24px rgba(26, 38, 77, 0.08);
}

@media (max-width: 720px) {
  .auth-brand {
    flex-basis: 100%;
    max-width: none;
    gap: 16px;
    padding: 28px 20px;
  }

  .auth-form {
    flex-basis: 100%;
    max-width: none;
  }

  .auth-card {
    padding: 24px;
    border: 0;
    box-shadow: none;
  }

  .hide-sm {
    display: none;
  }
}
</style>
