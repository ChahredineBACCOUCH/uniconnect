<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

/*
 * SCRUM-70 · E01 « Connexion » (maquette E01 sur PC, M-Connexion sur mobile).
 *
 * La connexion passe par le store (SCRUM-38) : il appelle POST /auth/login,
 * garde le jeton et renvoie l'utilisateur. En cas d'échec, l'API renvoie un
 * message en français (422 identifiants incorrects, 423 compte bloqué,
 * 403 compte suspendu ou désactivé) que l'on affiche tel quel.
 */

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const motDePasse = ref('')
const afficher = ref(false)
const resterConnecte = ref(true)

const envoi = ref(false)
const erreur = ref(null)
const information = ref('')

/* Message affiché à l'utilisateur, selon la réponse de l'API. */
function messageErreur(e) {
  const message = e.response?.data?.message

  if (message) {
    const restantes = e.response?.data?.tentatives_restantes

    return restantes
      ? `${message} Il vous reste ${restantes} tentative${restantes > 1 ? 's' : ''}.`
      : message
  }

  return 'La connexion a échoué. Vérifiez votre connexion internet puis réessayez.'
}

async function seConnecter() {
  envoi.value = true
  erreur.value = null

  try {
    await auth.login(email.value.trim(), motDePasse.value, resterConnecte.value)

    // Retour à la page demandée (si la garde nous a redirigés) ou à l'accueil.
    // On n'accepte qu'un chemin interne : pas de redirection vers un site externe.
    const redirection = route.query.redirect
    const destination =
      typeof redirection === 'string' && redirection.startsWith('/') ? redirection : null

    router.push(destination || { name: 'home' })
  } catch (e) {
    erreur.value = {
      // Un compte bloqué est une situation temporaire : on l'affiche en avertissement.
      type: e.response?.status === 423 ? 'warning' : 'error',
      message: messageErreur(e),
    }
  } finally {
    envoi.value = false
  }
}
</script>

<template>
  <div class="connexion">
    <div>
      <h1 class="text-h5 font-weight-medium">Connexion</h1>
      <p class="connexion__sous-titre">
        Connectez-vous avec votre adresse électronique institutionnelle.
      </p>
    </div>

    <v-alert v-if="erreur" :type="erreur.type" variant="tonal" density="comfortable" role="alert">
      {{ erreur.message }}
    </v-alert>

    <v-form @submit.prevent="seConnecter">
      <v-text-field
        v-model="email"
        label="Adresse électronique institutionnelle"
        type="email"
        prepend-inner-icon="mdi-email-outline"
        autocomplete="username"
        required
      />

      <v-text-field
        v-model="motDePasse"
        label="Mot de passe"
        :type="afficher ? 'text' : 'password'"
        prepend-inner-icon="mdi-lock-outline"
        :append-inner-icon="afficher ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
        autocomplete="current-password"
        @click:append-inner="afficher = !afficher"
      />

      <div class="connexion__options">
        <v-checkbox
          v-model="resterConnecte"
          label="Rester connecté"
          color="primary"
          density="compact"
          hide-details
        />
        <v-btn
          variant="text"
          class="text-none"
          @click="
            information = 'La procédure de réinitialisation du mot de passe arrive prochainement.'
          "
        >
          Mot de passe oublié ?
        </v-btn>
      </div>

      <v-btn type="submit" color="primary" block class="text-none" :loading="envoi">
        Se connecter
      </v-btn>
    </v-form>

    <v-alert v-if="information" type="info" variant="tonal" density="comfortable" role="note">
      {{ information }}
    </v-alert>

    <v-divider />

    <div class="connexion__pied">
      <span class="text-body-2 text-medium-emphasis">Pas encore de compte ?</span>
      <v-btn variant="text" color="primary" class="text-none" to="/inscription">
        Créer un compte
      </v-btn>
    </div>

    <v-alert type="info" variant="tonal" density="comfortable" role="note">
      Après 5 tentatives échouées, le compte est bloqué pendant 15 minutes.
    </v-alert>
  </div>
</template>

<style scoped>
.connexion {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.connexion__sous-titre {
  margin: 6px 0 0;
  color: rgb(var(--v-theme-on-surface-variant));
}

.connexion__options {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: -8px;
}

.connexion__pied {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  flex-wrap: wrap;
}
</style>
