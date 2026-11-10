<script setup>
import { reactive, ref } from 'vue'

import http, { ValidationError } from '@/api/http'

/*
 * SCRUM-64 : écran E02 « Créer mon compte » (maquette docs/maquette/ecrans/E02-Inscription.dc.html).
 *
 * Le parcours est en deux temps (maquette « Étapes ») :
 *   1. Éligibilité  → on vérifie que la personne figure sur la liste des comptes autorisés ;
 *   2. Activation    → l'API envoie un e-mail signé, valable 48 h, pour choisir le mot de passe.
 *
 * L'API peut refuser l'inscription avec un code (422) :
 *   - PLATEFORME_FERMEE : aucune année universitaire active → on bloque le formulaire ;
 *   - NON_AUTORISE      : le couple matricule + e-mail est absent de la liste → l'erreur
 *                         s'affiche sous le champ e-mail ;
 *   - DEJA_INSCRIT      : un compte existe déjà pour ce matricule.
 */

const formulaire = reactive({
  matricule: '',
  nom: '',
  prenom: '',
  email: '',
  charte: false,
})

const formulaireEl = ref(null)
const envoi = ref(false)
const envoye = ref(false)
// Erreurs renvoyées par l'API, rangées par champ : { email: ['message'] }.
const erreurs = ref({})
// Message qui ne concerne aucun champ en particulier (ex. DEJA_INSCRIT, erreur réseau).
const erreurGlobale = ref('')
const plateformeFermee = ref(false)

// Contrôles côté navigateur : le serveur revérifie tout, mais on évite un aller-retour inutile.
const regles = {
  matricule: [(v) => Boolean(v?.trim()) || 'Le matricule est obligatoire.'],
  email: [
    (v) => Boolean(v?.trim()) || "L'adresse e-mail est obligatoire.",
    (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v ?? '') || "Le format de l'adresse e-mail est invalide.",
  ],
  charte: [(v) => v === true || 'Vous devez accepter la charte pour créer un compte.'],
}

// Dès que la personne corrige un champ, son message d'erreur n'est plus pertinent.
function viderErreur(champ) {
  erreurGlobale.value = ''
  if (!erreurs.value[champ]) return
  const reste = { ...erreurs.value }
  delete reste[champ]
  erreurs.value = reste
}

async function envoyer() {
  const { valid } = await formulaireEl.value.validate()
  if (!valid) return

  envoi.value = true
  erreurs.value = {}
  erreurGlobale.value = ''

  try {
    await http.post('/auth/register', {
      matricule: formulaire.matricule.trim(),
      nom: formulaire.nom.trim(),
      prenom: formulaire.prenom.trim(),
      email: formulaire.email.trim(),
      charte: formulaire.charte,
    })
    // Succès : l'e-mail d'activation part, on remplace le formulaire par l'écran de confirmation.
    envoye.value = true
  } catch (erreur) {
    if (erreur instanceof ValidationError) {
      erreurs.value = erreur.errors ?? {}
      plateformeFermee.value = erreur.code === 'PLATEFORME_FERMEE'
      // Un refus sans message rattaché à un champ (ex. DEJA_INSCRIT) s'affiche en global.
      if (!plateformeFermee.value && Object.keys(erreurs.value).length === 0) {
        erreurGlobale.value = erreur.message
      }
    } else {
      erreurGlobale.value =
        "Impossible de créer le compte pour le moment. Vérifie ta connexion puis réessaie."
    }
  } finally {
    envoi.value = false
  }
}
</script>

<template>
  <div class="auth-view">
    <!-- Étapes du parcours : l'étape 1 est faite quand l'e-mail est parti. -->
    <div class="etapes" aria-label="Étapes">
      <span class="etape" :class="{ 'etape--active': !envoye, 'etape--faite': envoye }">
        <span class="etape__puce">
          <v-icon v-if="envoye" icon="mdi-check" size="13" />
          <template v-else>1</template>
        </span>
        Éligibilité
      </span>
      <span class="etapes__trait" aria-hidden="true">—</span>
      <span class="etape" :class="{ 'etape--active': envoye }">
        <span class="etape__puce">2</span>
        Activation par e-mail
      </span>
    </div>

    <!-- Écran de confirmation : le formulaire laisse la place à l'e-mail d'activation. -->
    <div v-if="envoye" class="confirmation">
      <span class="confirmation__icone">
        <v-icon icon="mdi-email-outline" size="32" />
      </span>
      <h2 class="confirmation__titre">Vérifie ta boîte mail</h2>
      <p class="confirmation__texte">
        Un e-mail d'activation a été envoyé à <strong>{{ formulaire.email }}</strong>. Clique sur le
        lien qu'il contient pour choisir ton mot de passe. Le lien est valable 48 heures.
      </p>
    </div>

    <template v-else>
      <div>
        <h2 class="text-h5 font-weight-medium">Créer mon compte</h2>
      </div>

      <!-- Plateforme fermée : le bandeur bloque la création de compte. -->
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

      <v-alert v-if="erreurGlobale" type="error" variant="tonal" density="comfortable" role="alert">
        {{ erreurGlobale }}
      </v-alert>

      <v-alert type="info" variant="tonal" density="comfortable">
        Seules les personnes figurant sur la liste des comptes autorisés de l'année active peuvent
        créer un compte.
      </v-alert>

      <v-form ref="formulaireEl" @submit.prevent="envoyer">
        <v-text-field
          id="matricule"
          v-model="formulaire.matricule"
          label="Identifiant universitaire (matricule)"
          prepend-inner-icon="mdi-key-outline"
          autocomplete="off"
          :rules="regles.matricule"
          :error-messages="erreurs.matricule ?? []"
          @update:model-value="viderErreur('matricule')"
        />

        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field
              id="nom"
              v-model="formulaire.nom"
              label="Nom"
              autocomplete="family-name"
              :error-messages="erreurs.nom ?? []"
              @update:model-value="viderErreur('nom')"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              id="prenom"
              v-model="formulaire.prenom"
              label="Prénom"
              autocomplete="given-name"
              :error-messages="erreurs.prenom ?? []"
              @update:model-value="viderErreur('prenom')"
            />
          </v-col>
        </v-row>

        <v-text-field
          id="email"
          v-model="formulaire.email"
          label="Adresse électronique institutionnelle"
          type="email"
          prepend-inner-icon="mdi-email-outline"
          autocomplete="email"
          :rules="regles.email"
          :error-messages="erreurs.email ?? []"
          @update:model-value="viderErreur('email')"
        />

        <v-checkbox
          id="charte"
          v-model="formulaire.charte"
          color="primary"
          :rules="regles.charte"
          :error-messages="erreurs.charte ?? []"
          class="mt-0"
          @update:model-value="viderErreur('charte')"
        >
          <template #label>
            <span class="text-body-2">J'accepte la charte d'utilisation de la plateforme</span>
          </template>
        </v-checkbox>

        <v-btn
          type="submit"
          color="primary"
          variant="flat"
          block
          class="mt-2"
          :loading="envoi"
          :disabled="plateformeFermee"
        >
          Créer mon compte
        </v-btn>
      </v-form>

      <div class="deja-compte">
        <span class="text-body-2">Déjà un compte ?</span>
        <v-btn variant="text" color="primary" to="/connexion">Se connecter</v-btn>
      </div>
    </template>
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

/* Étapes « 1 Éligibilité — 2 Activation par e-mail » (maquette E02). */
.etapes {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.etapes__trait {
  color: rgb(var(--v-theme-outline));
}

.etape {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.etape__puce {
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  background: rgb(var(--v-theme-surface-variant));
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 12px;
}

.etape--active {
  color: rgb(var(--v-theme-primary));
  font-weight: 500;
}

.etape--active .etape__puce {
  background: rgb(var(--v-theme-primary));
  color: #fff;
}

.etape--faite .etape__puce {
  background: rgb(var(--v-theme-primary));
  color: #fff;
}

/* Écran « Vérifie ta boîte mail ». */
.confirmation {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.confirmation__icone {
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  border-radius: 999px;
  background: #e3f1e4;
  color: #1b5e20;
}

.confirmation__titre {
  margin: 0;
  font-size: 22px;
  font-weight: 500;
}

.confirmation__texte {
  margin: 0;
  color: rgb(var(--v-theme-on-surface-variant));
}

/* Lien « Déjà un compte ? Se connecter ». */
.deja-compte {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  flex-wrap: wrap;
}
</style>
