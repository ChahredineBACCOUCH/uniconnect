<script setup>
import { computed, ref } from 'vue'

import http from '@/api/http'

/*
 * SCRUM-64 · E02 « Créer mon compte » (maquette E02 sur PC, M-Inscription sur mobile).
 *
 * POST /auth/register : l'API contrôle l'éligibilité (matricule + e-mail présents
 * sur la liste des comptes autorisés de l'année active), crée le compte en attente
 * d'activation puis envoie l'e-mail qui contient le lien de choix du mot de passe.
 * Aucun mot de passe n'est donc demandé sur cet écran.
 */

const formulaire = ref(null)

const matricule = ref('')
const nom = ref('')
const prenom = ref('')
const email = ref('')
const charte = ref(false)

const envoi = ref(false)
const erreursChamps = ref({})
const erreurGenerale = ref('')
const plateformeFermee = ref(false)
const envoye = ref(false)

/*
 * Étapes « 1 Éligibilité — 2 Activation par e-mail » (maquette E02).
 * Après l'envoi, l'étape 1 est cochée et l'étape 2 devient l'étape en cours.
 */
const etapes = computed(() =>
  envoye.value
    ? [
        { pastille: '✓', label: 'Éligibilité', active: true },
        { pastille: '2', label: 'Activation par e-mail', active: true },
      ]
    : [
        { pastille: '1', label: 'Éligibilité', active: true },
        { pastille: '2', label: 'Activation par e-mail', active: false },
      ],
)

/* Contrôles côté navigateur : l'API refait exactement la même vérification. */
const reglesMatricule = [(v) => Boolean((v ?? '').trim()) || 'Le matricule est obligatoire.']
const reglesEmail = [
  (v) => Boolean((v ?? '').trim()) || "L'adresse e-mail est obligatoire.",
  (v) => /.+@.+\..+/.test(v ?? '') || "Le format de l'adresse e-mail est invalide.",
]
const reglesCharte = [(v) => v === true || 'Vous devez accepter la charte pour créer un compte.']

/* Message lisible pour une erreur réseau ou une erreur serveur. */
function messageErreur(erreur) {
  // 422 : le message de l'API est déjà en français.
  if (erreur?.errors) return erreur.message
  if (erreur?.code === 'PLATEFORME_FERMEE') return erreur.message

  return (
    erreur?.response?.data?.message ??
    'La création du compte a échoué. Vérifiez votre connexion puis réessayez.'
  )
}

async function envoyer() {
  const { valid } = await formulaire.value.validate()
  if (!valid) return

  envoi.value = true
  erreurGenerale.value = ''
  erreursChamps.value = {}
  plateformeFermee.value = false

  try {
    await http.post('/auth/register', {
      matricule: matricule.value.trim(),
      nom: nom.value.trim() || null,
      prenom: prenom.value.trim() || null,
      email: email.value.trim(),
      charte: charte.value,
    })

    // Le compte existe : on remplace le formulaire par l'écran « e-mail envoyé ».
    envoye.value = true
  } catch (erreur) {
    // 422 : chaque message est affiché sous le champ concerné.
    erreursChamps.value = erreur.errors ?? {}

    // Aucune année universitaire active : la création de compte est impossible.
    plateformeFermee.value = erreur.code === 'PLATEFORME_FERMEE'

    // Message général seulement s'il n'y a pas d'erreur rattachée à un champ.
    if (!plateformeFermee.value && !Object.keys(erreursChamps.value).length) {
      erreurGenerale.value = messageErreur(erreur)
    }
  } finally {
    envoi.value = false
  }
}
</script>

<template>
  <div class="inscription" :class="{ 'inscription--confirmation': envoye }">
    <ol class="etapes" aria-label="Étapes">
      <template v-for="(etape, index) in etapes" :key="etape.label">
        <li class="etape" :class="{ 'etape--active': etape.active }">
          <span class="etape__pastille" aria-hidden="true">{{ etape.pastille }}</span>
          <span>{{ etape.label }}</span>
        </li>
        <li v-if="index < etapes.length - 1" class="etape__trait" aria-hidden="true">—</li>
      </template>
    </ol>

    <!-- Écran « e-mail envoyé » : le formulaire est remplacé. -->
    <div v-if="envoye" class="inscription__confirmation">
      <span class="inscription__pastille" aria-hidden="true">
        <v-icon icon="mdi-email-outline" size="32" />
      </span>

      <h1 class="text-h6 font-weight-medium">Vérifie ta boîte mail</h1>

      <p class="text-body-2 text-medium-emphasis inscription__texte">
        Un e-mail d'activation a été envoyé à <strong>{{ email.trim() }}</strong
        >. Clique sur le lien qu'il contient pour choisir ton mot de passe. Le lien est valable 48
        heures.
      </p>

      <v-btn variant="text" color="primary" class="text-none" to="/connexion"> Se connecter </v-btn>
    </div>

    <v-form v-else ref="formulaire" @submit.prevent="envoyer">
      <h1 class="text-h5 font-weight-medium">Créer mon compte</h1>

      <!-- Aucune année universitaire active : la plateforme est fermée. -->
      <v-alert
        v-if="plateformeFermee"
        type="warning"
        variant="tonal"
        density="comfortable"
        role="alert"
      >
        <strong>La plateforme n'est pas encore ouverte pour cette année universitaire.</strong>
        Aucune année n'est active : la création de compte est temporairement impossible. Réessayez
        après l'ouverture annoncée par l'administration.
      </v-alert>

      <v-alert type="info" variant="tonal" density="comfortable" role="note">
        Seules les personnes figurant sur la liste des comptes autorisés de l'année active peuvent
        créer un compte.
      </v-alert>

      <v-alert v-if="erreurGenerale" type="error" variant="tonal" density="comfortable">
        {{ erreurGenerale }}
      </v-alert>

      <v-text-field
        v-model="matricule"
        label="Identifiant universitaire (matricule)"
        prepend-inner-icon="mdi-key-outline"
        :rules="reglesMatricule"
        :error="Boolean(erreursChamps.matricule)"
        :error-messages="erreursChamps.matricule"
        :aria-invalid="Boolean(erreursChamps.matricule)"
        autocomplete="off"
        required
      />

      <div class="inscription__deux">
        <v-text-field
          v-model="nom"
          label="Nom"
          :error="Boolean(erreursChamps.nom)"
          :error-messages="erreursChamps.nom"
          :aria-invalid="Boolean(erreursChamps.nom)"
          autocomplete="family-name"
        />
        <v-text-field
          v-model="prenom"
          label="Prénom"
          :error="Boolean(erreursChamps.prenom)"
          :error-messages="erreursChamps.prenom"
          :aria-invalid="Boolean(erreursChamps.prenom)"
          autocomplete="given-name"
        />
      </div>

      <v-text-field
        v-model="email"
        label="Adresse électronique institutionnelle"
        type="email"
        prepend-inner-icon="mdi-email-outline"
        :rules="reglesEmail"
        :error="Boolean(erreursChamps.email)"
        :error-messages="erreursChamps.email"
        :aria-invalid="Boolean(erreursChamps.email)"
        autocomplete="email"
        required
      />

      <v-checkbox
        v-model="charte"
        color="primary"
        :rules="reglesCharte"
        :error="Boolean(erreursChamps.charte)"
        :error-messages="erreursChamps.charte"
      >
        <template #label>
          <span>J'accepte la charte d'utilisation de la plateforme</span>
        </template>
      </v-checkbox>

      <v-btn
        type="submit"
        color="primary"
        block
        class="text-none"
        :loading="envoi"
        :disabled="plateformeFermee"
      >
        Créer mon compte
      </v-btn>

      <div class="inscription__pied">
        <span class="text-body-2 text-medium-emphasis">Déjà un compte ?</span>
        <v-btn variant="text" color="primary" class="text-none" to="/connexion">
          Se connecter
        </v-btn>
      </div>
    </v-form>
  </div>
</template>

<style scoped>
.inscription {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.inscription--confirmation {
  align-items: center;
  text-align: center;
}

.inscription__deux {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 16px;
}

.inscription__pied {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  flex-wrap: wrap;
}

.inscription__confirmation {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.inscription__texte {
  max-width: 380px;
  margin: 0;
}

.inscription__pastille {
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  border-radius: 999px;
  background: #e3f1e4;
  color: #1b5e20;
}

/* Étapes « 1 Éligibilité — 2 Activation par e-mail » (maquette E02). */
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

.inscription--confirmation .etapes {
  justify-content: center;
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

.etape__trait {
  margin: 0 4px;
}

@media (max-width: 600px) {
  .inscription__deux {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
