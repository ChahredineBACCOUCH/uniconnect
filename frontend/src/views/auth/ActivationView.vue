<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import http from '@/api/http'
import { useAuthStore } from '@/stores/auth'

/*
 * SCRUM-67 · E03 « Activer mon compte » (maquette E03 sur PC, M-Activation sur mobile).
 *
 * La personne arrive ici en cliquant sur le lien reçu par e-mail : ce lien
 * contient l'identifiant du compte et les paramètres signés (expires,
 * signature). On les renvoie tels quels à POST /auth/activate pour activer le
 * compte et choisir son mot de passe.
 */

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const motDePasse = ref('')
const confirmation = ref('')
const afficher = ref(false)

const envoi = ref(false)
const erreursChamps = ref({})
const erreurGenerale = ref('')
const lienExpire = ref(false)

const emailRenvoi = ref('')
const renvoiEnCours = ref(false)
const lienRenvoye = ref(false)

/* Les quatre règles du mot de passe (maquette E03) : elles se coche en direct. */
const regles = computed(() => [
  { label: '8 caractères minimum', ok: motDePasse.value.length >= 8 },
  { label: 'Une majuscule', ok: /[A-Z]/.test(motDePasse.value) },
  { label: 'Une minuscule', ok: /[a-z]/.test(motDePasse.value) },
  { label: 'Un chiffre', ok: /[0-9]/.test(motDePasse.value) },
])

/* Nombre de règles satisfaites : il remplit la jauge et colore les barres. */
const force = computed(() => regles.value.filter((regle) => regle.ok).length)

const couleurForce = computed(() => {
  if (force.value === 4) return '#2E7D32'
  if (force.value >= 2) return '#ED6C02'
  return '#C62828'
})

/* Le bouton s'active quand les quatre règles sont remplies et la confirmation correcte. */
const formulaireValide = computed(
  () => force.value === 4 && motDePasse.value.length > 0 && confirmation.value === motDePasse.value,
)

/* Un paramètre de requête peut être une chaîne ou un tableau (query repeated). */
function premier(valeur) {
  return Array.isArray(valeur) ? valeur[0] : valeur
}

/* Paramètres signés du lien reçu par e-mail. */
const parametresSignes = computed(() => {
  const expires = premier(route.query.expires)
  const signature = premier(route.query.signature)

  return expires && signature ? { expires, signature } : null
})

/* URL d'activation : l'API attend les paramètres signés dans la chaîne de requête. */
const urlActivation = computed(() => {
  const identifiant = premier(route.params.id)
  if (!identifiant || !parametresSignes.value) return null

  const { expires, signature } = parametresSignes.value

  return `/auth/activate/${encodeURIComponent(identifiant)}?expires=${encodeURIComponent(expires)}&signature=${encodeURIComponent(signature)}`
})

async function activer() {
  if (!formulaireValide.value || !urlActivation.value) return

  envoi.value = true
  erreurGenerale.value = ''
  erreursChamps.value = {}

  try {
    const { data } = await http.post(urlActivation.value, {
      password: motDePasse.value,
      password_confirmation: confirmation.value,
    })

    // Le compte est actif : on ouvre la session puis on va à l'accueil.
    auth.connecter(data.token, data.user)
    router.push({ name: 'home' })
  } catch (erreur) {
    if (erreur.response?.status === 403) {
      // Lien invalide ou expiré : on propose d'en demander un nouveau.
      lienExpire.value = true
    } else {
      // 422 : chaque message est affiché sous le champ concerné.
      erreursChamps.value = erreur.errors ?? {}
      erreurGenerale.value =
        erreur.response?.data?.message ?? "L'activation n'a pas abouti. Réessayez."
    }
  } finally {
    envoi.value = false
  }
}

/* Demande un nouveau lien d'activation à l'adresse donnée. */
async function renvoyer() {
  const email = emailRenvoi.value.trim()
  if (!email) return

  renvoiEnCours.value = true
  erreurGenerale.value = ''

  try {
    await http.post('/auth/activate/renvoyer', { email })
    lienRenvoye.value = true
  } catch {
    erreurGenerale.value = "L'e-mail n'a pas pu être envoyé. Réessayez dans quelques instants."
  } finally {
    renvoiEnCours.value = false
  }
}
</script>

<template>
  <div class="activation">
    <!-- Lien expiré : on demande un nouveau lien d'activation. -->
    <div v-if="lienExpire" class="activation__expire">
      <span class="activation__pastille" aria-hidden="true">
        <v-icon icon="mdi-timer-sand" size="32" />
      </span>

      <h1 class="text-h6 font-weight-medium">Ce lien d'activation a expiré</h1>

      <p class="text-body-2 text-medium-emphasis activation__texte">
        Les liens d'activation sont valables 48 heures. Demandez un nouveau lien : il sera envoyé à
        votre adresse institutionnelle.
      </p>

      <v-text-field
        v-model="emailRenvoi"
        label="Adresse électronique institutionnelle"
        type="email"
        prepend-inner-icon="mdi-email-outline"
        autocomplete="email"
      />

      <v-alert v-if="erreurGenerale" type="error" variant="tonal" density="comfortable">
        {{ erreurGenerale }}
      </v-alert>

      <v-alert
        v-if="lienRenvoye"
        type="success"
        variant="tonal"
        density="comfortable"
        role="status"
      >
        Un nouvel e-mail d'activation a été envoyé à
        <strong>{{ emailRenvoi.trim() }}</strong
        >.
      </v-alert>

      <v-btn
        color="primary"
        block
        class="text-none"
        :loading="renvoiEnCours"
        :disabled="!emailRenvoi.trim()"
        @click="renvoyer"
      >
        Renvoyer un e-mail d'activation
      </v-btn>
    </div>

    <v-form v-else @submit.prevent="activer">
      <ol class="etapes" aria-label="Étapes">
        <li class="etape etape--faite">
          <span class="etape__pastille" aria-hidden="true">✓</span>
          <span>Éligibilité</span>
        </li>
        <li class="etape__trait" aria-hidden="true">—</li>
        <li class="etape etape--active">
          <span class="etape__pastille" aria-hidden="true">2</span>
          <span>Activation</span>
        </li>
      </ol>

      <h1 class="text-h5 font-weight-medium">Activer mon compte</h1>

      <v-alert
        v-if="!parametresSignes"
        type="warning"
        variant="tonal"
        density="comfortable"
        role="alert"
      >
        Ce lien d'activation est incomplet : demandez un nouveau lien par e-mail.
      </v-alert>

      <v-alert v-if="erreurGenerale" type="error" variant="tonal" density="comfortable">
        {{ erreurGenerale }}
      </v-alert>

      <v-text-field
        v-model="motDePasse"
        label="Nouveau mot de passe"
        :type="afficher ? 'text' : 'password'"
        prepend-inner-icon="mdi-lock-outline"
        :append-inner-icon="afficher ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
        :error="Boolean(erreursChamps.password)"
        :error-messages="erreursChamps.password"
        autocomplete="new-password"
        @click:append-inner="afficher = !afficher"
      />

      <!-- Jauge de force : une barre par règle satisfaite. -->
      <div class="jauge" aria-hidden="true">
        <span
          v-for="index in 4"
          :key="index"
          class="jauge__barre"
          :style="{ background: index <= force ? couleurForce : undefined }"
        />
      </div>

      <ul class="regles" aria-label="Règles du mot de passe">
        <li
          v-for="regle in regles"
          :key="regle.label"
          class="regle"
          :class="{ 'regle--ok': regle.ok }"
        >
          <v-icon :icon="regle.ok ? 'mdi-check' : 'mdi-close'" size="16" />
          {{ regle.label }}
        </li>
      </ul>

      <v-text-field
        v-model="confirmation"
        label="Confirmer le mot de passe"
        :type="afficher ? 'text' : 'password'"
        prepend-inner-icon="mdi-lock-outline"
        :append-inner-icon="afficher ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
        :error="Boolean(erreursChamps.password_confirmation)"
        :error-messages="erreursChamps.password_confirmation"
        autocomplete="new-password"
        @click:append-inner="afficher = !afficher"
      />

      <v-btn
        type="submit"
        color="primary"
        block
        class="text-none"
        :loading="envoi"
        :disabled="!formulaireValide"
      >
        Activer mon compte
      </v-btn>
    </v-form>
  </div>
</template>

<style scoped>
.activation {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.activation__expire {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.activation__texte {
  max-width: 380px;
  margin: 0;
}

.activation__pastille {
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  border-radius: 999px;
  background: #fff4e5;
  color: #b45309;
}

/* Jauge de force : quatre barres (maquette E03). */
.jauge {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 4px;
}

.jauge__barre {
  height: 4px;
  border-radius: 2px;
  background: #d5dbe7;
}

/* Les quatre règles du mot de passe. */
.regles {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: -8px 0 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.regle {
  display: flex;
  align-items: center;
  gap: 8px;
}

.regle--ok {
  color: #1b5e20;
}

/* Étapes « ✓ Éligibilité — 2 Activation » (maquette E03). */
.etapes {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.etape {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.etape--active {
  color: rgb(var(--v-theme-primary));
  font-weight: 500;
}

.etape__pastille {
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  font-size: 12px;
  background: rgb(var(--v-theme-surface-variant));
  color: #4a5366;
}

.etape--active .etape__pastille {
  background: rgb(var(--v-theme-primary));
  color: #fff;
}

.etape--faite .etape__pastille {
  background: #2e7d32;
  color: #fff;
}

.etape__trait {
  margin: 0 4px;
}
</style>
