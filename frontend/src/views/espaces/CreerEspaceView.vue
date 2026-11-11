<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

import { creerEspace, inviter } from '@/api/espaces'
import RecherchePersonnes from '@/components/espaces/RecherchePersonnes.vue'
import { useAuthStore } from '@/stores/auth'
import { DUREES_CREATION, messageErreur } from '@/utils/espaces'

/*
 * SCRUM-89 · E06 « Créer un espace » : assistant en étapes.
 * POST /api/espaces, puis POST /api/espaces/{id}/invitations s'il y a des invités,
 * puis redirection vers le nouvel espace.
 */
const router = useRouter()
const auth = useAuthStore()
// Sur téléphone, les 5 libellés ne tiennent pas : numéros seuls + « Étape n sur 5 ».
const { smAndDown } = useDisplay()

const ETAPES = [
  { n: 1, label: 'Type' },
  { n: 2, label: 'Informations' },
  { n: 3, label: 'Visibilité' },
  { n: 4, label: 'Invitations' },
  { n: 5, label: 'Récapitulatif' },
]

// Seul le groupe privé est créable dans la v1.0 ; les autres types sont expliqués.
const TYPES = computed(() => [
  {
    valeur: 'PRIVE',
    titre: 'Groupe privé',
    description: 'Sur invitation uniquement. Invisible dans les recherches.',
    icone: 'mdi-lock-outline',
    disponible: auth.hasDroit('CREER_GROUPE_PRIVE'),
    raison: "Vous n'avez pas le droit de créer un groupe privé.",
  },
  {
    valeur: 'CHANNEL',
    titre: 'Channel communautaire',
    description: "Réservé aux enseignants et à l'administration.",
    icone: 'mdi-pound',
    disponible: false,
    raison: 'Disponible dans une prochaine version.',
  },
  {
    valeur: 'ORGANISATIONNEL',
    titre: 'Groupe organisationnel',
    description: "Créé par l'administration depuis les référentiels.",
    icone: 'mdi-account-group-outline',
    disponible: false,
    raison: "Généré automatiquement par l'administration.",
  },
])

const etape = ref(1)
const type = ref(TYPES.value[0].disponible ? 'PRIVE' : null)
const nom = ref('')
const description = ref('')
const modeAnnonce = ref(false)
const duree = ref(168)
const invites = ref([])

const envoi = ref(false)
const erreur = ref('')
const erreursChamps = ref({})
const formulaireInfos = ref(null)

const reglesNom = [
  (v) => (v ?? '').trim().length >= 3 || 'Le nom doit contenir au moins 3 caractères.',
  (v) => (v ?? '').trim().length <= 60 || 'Le nom ne doit pas dépasser 60 caractères.',
]
const reglesDescription = [
  (v) => (v ?? '').length <= 1000 || 'La description ne doit pas dépasser 1 000 caractères.',
]

const libelleDuree = computed(
  () => DUREES_CREATION.find((d) => d.valeur === duree.value)?.libelle ?? '',
)
const nomProprietaire = computed(() =>
  auth.user ? `Vous (${auth.user.prenom} ${auth.user.nom})` : 'Vous',
)

async function continuer() {
  erreur.value = ''

  if (etape.value === 1 && type.value !== 'PRIVE') {
    erreur.value = "Choisissez un type d'espace disponible."
    return
  }

  if (etape.value === 2) {
    const { valid } = await formulaireInfos.value.validate()
    if (!valid) return
  }

  etape.value = Math.min(etape.value + 1, ETAPES.length)
}

function retour() {
  erreur.value = ''
  etape.value = Math.max(etape.value - 1, 1)
}

async function creer() {
  envoi.value = true
  erreur.value = ''
  erreursChamps.value = {}

  let espace

  try {
    espace = await creerEspace({
      nom: nom.value.trim(),
      description: description.value.trim() || null,
      mode_annonce: modeAnnonce.value,
      duree_visibilite_heures: duree.value,
    })
  } catch (error) {
    envoi.value = false
    erreursChamps.value = error.errors ?? {}
    erreur.value = messageErreur(error, "L'espace n'a pas pu être créé.")
    // Une erreur sur le nom ou la description : retour à l'étape concernée.
    if (erreursChamps.value.nom || erreursChamps.value.description) etape.value = 2
    return
  }

  let avertissement = null

  if (invites.value.length > 0) {
    try {
      await inviter(
        espace.id,
        invites.value.map((p) => p.id),
      )
    } catch (error) {
      // L'espace existe déjà : on y va quand même, les invitations pourront être refaites.
      avertissement = `Espace créé, mais les invitations n'ont pas pu être envoyées : ${messageErreur(error)}`
    }
  }

  envoi.value = false
  router.push({
    name: 'espace',
    params: { id: espace.id, onglet: invites.value.length ? 'membres' : 'discussion' },
    query: avertissement ? { avertissement } : {},
  })
}
</script>

<template>
  <div class="creer-espace">
    <div class="creer-espace__entete">
      <h1 class="text-h5 font-weight-medium">Créer un espace</h1>
      <v-btn variant="text" class="text-none" @click="router.back()">Annuler</v-btn>
    </div>

    <p v-if="smAndDown" class="text-body-2 text-medium-emphasis creer-espace__etape">
      Étape {{ etape }} sur {{ ETAPES.length }} · {{ ETAPES[etape - 1].label }}
    </p>

    <v-stepper
      v-model="etape"
      :items="ETAPES.map((e) => e.label)"
      :mobile="smAndDown"
      hide-actions
      alt-labels
      flat
    >
      <!-- Étape 1 : type -->
      <template #[`item.1`]>
        <h2 class="text-subtitle-1 font-weight-medium">Quel type d'espace ?</h2>
        <p class="text-body-2 text-medium-emphasis mb-4">
          Seuls les types autorisés par vos droits sont disponibles.
        </p>

        <v-radio-group v-model="type" hide-details>
          <v-card
            v-for="t in TYPES"
            :key="t.valeur"
            :disabled="!t.disponible"
            :variant="type === t.valeur ? 'tonal' : 'outlined'"
            :color="type === t.valeur ? 'primary' : undefined"
            class="creer-espace__type"
            @click="t.disponible && (type = t.valeur)"
          >
            <v-card-item>
              <template #prepend>
                <v-icon :icon="t.icone" size="28" />
              </template>
              <v-card-title class="text-subtitle-1">{{ t.titre }}</v-card-title>
              <v-card-subtitle class="creer-espace__sous-titre">
                {{ t.disponible ? t.description : t.raison }}
              </v-card-subtitle>
              <template #append>
                <v-radio :value="t.valeur" :disabled="!t.disponible" />
              </template>
            </v-card-item>
          </v-card>
        </v-radio-group>
      </template>

      <!-- Étape 2 : informations -->
      <template #[`item.2`]>
        <v-form ref="formulaireInfos" @submit.prevent="continuer">
          <v-text-field
            v-model="nom"
            label="Nom de l'espace"
            hint="3 à 60 caractères"
            counter="60"
            :rules="reglesNom"
            :error-messages="erreursChamps.nom"
            autofocus
          />
          <v-textarea
            v-model="description"
            label="Description (facultative)"
            rows="3"
            auto-grow
            counter="1000"
            :rules="reglesDescription"
            :error-messages="erreursChamps.description"
          />

          <h3 class="text-subtitle-2 mt-2">Droit de publication</h3>
          <v-radio-group v-model="modeAnnonce" inline hide-details>
            <v-radio label="Tous les membres" :value="false" />
            <v-radio label="Gestionnaires seulement" :value="true" />
          </v-radio-group>
        </v-form>
      </template>

      <!-- Étape 3 : visibilité des messages -->
      <template #[`item.3`]>
        <h2 class="text-subtitle-1 font-weight-medium">Visibilité des messages</h2>
        <v-radio-group v-model="duree" hide-details>
          <v-radio v-for="d in DUREES_CREATION" :key="String(d.valeur)" :value="d.valeur">
            <template #label>
              <span>{{ d.libelle }}</span>
              <span v-if="d.detail" class="text-medium-emphasis ml-2 text-body-2">
                ({{ d.detail }})
              </span>
            </template>
          </v-radio>
        </v-radio-group>
        <v-alert type="info" variant="tonal" density="compact" class="mt-4">
          La durée par défaut d'un groupe privé est fixée par l'administration. Les messages
          disparaissent automatiquement après cette durée.
        </v-alert>
      </template>

      <!-- Étape 4 : invitations -->
      <template #[`item.4`]>
        <h2 class="text-subtitle-1 font-weight-medium">Inviter des membres</h2>
        <p class="text-body-2 text-medium-emphasis mb-4">
          Facultatif : vous pourrez inviter d'autres personnes plus tard depuis l'onglet Membres.
        </p>
        <RecherchePersonnes v-model="invites" />
      </template>

      <!-- Étape 5 : récapitulatif -->
      <template #[`item.5`]>
        <h2 class="text-subtitle-1 font-weight-medium mb-2">Récapitulatif</h2>
        <v-table density="comfortable" class="creer-espace__recap">
          <tbody>
            <tr>
              <th>Type</th>
              <td>Groupe privé</td>
            </tr>
            <tr>
              <th>Nom</th>
              <td>{{ nom.trim() }}</td>
            </tr>
            <tr v-if="description.trim()">
              <th>Description</th>
              <td>{{ description.trim() }}</td>
            </tr>
            <tr>
              <th>Publication</th>
              <td>{{ modeAnnonce ? 'Gestionnaires seulement' : 'Tous les membres' }}</td>
            </tr>
            <tr>
              <th>Visibilité des messages</th>
              <td>{{ libelleDuree }}</td>
            </tr>
            <tr>
              <th>Invitations</th>
              <td>
                <template v-if="invites.length">
                  {{ invites.length }} ·
                  {{ invites.map((p) => `${p.prenom} ${p.nom}`).join(', ') }}
                </template>
                <template v-else>Aucune</template>
              </td>
            </tr>
            <tr>
              <th>Propriétaire</th>
              <td>{{ nomProprietaire }}</td>
            </tr>
          </tbody>
        </v-table>
      </template>
    </v-stepper>

    <v-alert v-if="erreur" type="error" variant="tonal" density="compact">{{ erreur }}</v-alert>

    <div class="creer-espace__actions">
      <v-btn v-if="etape > 1" variant="text" class="text-none" :disabled="envoi" @click="retour">
        Retour
      </v-btn>
      <v-spacer />
      <v-btn
        v-if="etape < ETAPES.length"
        color="primary"
        class="text-none"
        :disabled="etape === 1 && type !== 'PRIVE'"
        @click="continuer"
      >
        Continuer
      </v-btn>
      <v-btn v-else color="primary" class="text-none" :loading="envoi" @click="creer">
        Créer l'espace
      </v-btn>
    </div>
  </div>
</template>

<style scoped>
.creer-espace {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 820px;
}

.creer-espace__entete {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.creer-espace__etape {
  margin: -8px 0 0;
}

.creer-espace__type {
  margin-bottom: 10px;
  cursor: pointer;
}

.creer-espace__sous-titre {
  white-space: normal;
}

.creer-espace__recap th {
  width: 40%;
  font-weight: 500;
  color: rgb(var(--v-theme-on-surface-variant));
}

.creer-espace__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
</style>
