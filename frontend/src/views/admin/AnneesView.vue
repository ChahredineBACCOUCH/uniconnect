<script setup>
import { computed, ref } from 'vue'
import { useDisplay } from 'vuetify'

import { activerAnnee, creerAnnee, modifierAnnee, recupererAnnees } from '@/api/annees'
import { messageErreur } from '@/utils/espaces'
import { detailEtat, formaterDate, libelleGroupes } from '@/utils/annees'

/*
 * SCRUM-73 — A01 « Années universitaires ».
 *
 * Une seule année peut être active. La liste vient de GET /admin/annees ;
 * chaque action (ajout, modification, activation) recharge ensuite la liste
 * et affiche une confirmation dans une snackbar.
 */
const { xs } = useDisplay()

const chargement = ref(false)
const erreurChargement = ref('')
const annees = ref([])

const anneeActive = computed(() => annees.value.find((annee) => annee.active) ?? null)

async function charger() {
  chargement.value = true
  erreurChargement.value = ''

  try {
    annees.value = await recupererAnnees()
  } catch (error) {
    annees.value = []
    erreurChargement.value = messageErreur(
      error,
      'Les années universitaires n’ont pas pu être chargées.',
    )
  } finally {
    chargement.value = false
  }
}

charger()

/* ------------------------------------------------------------------ */
/* Notifications                                                       */
/* ------------------------------------------------------------------ */

const snackbar = ref({ visible: false, message: '', color: 'success' })

function notifier(message, color = 'success') {
  snackbar.value = { visible: true, message, color }
}

/* ------------------------------------------------------------------ */
/* Tableau                                                             */
/* ------------------------------------------------------------------ */

const entetes = [
  { title: 'Année', key: 'libelle', sortable: false },
  { title: 'Début', key: 'date_debut', sortable: false },
  { title: 'Fin', key: 'date_fin', sortable: false },
  { title: 'État', key: 'etat', sortable: false },
  { title: 'Groupes organisationnels', key: 'groupes', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 220 },
]

const couleurEtat = (annee) => detailEtat(annee).color ?? undefined

/* ------------------------------------------------------------------ */
/* Dialogue d'ajout / modification                                     */
/* ------------------------------------------------------------------ */

const dialogue = ref(false)
const anneeEditee = ref(null)
const formulaire = ref(null)
const enregistrement = ref(false)
const erreurFormulaire = ref('')
const erreursChamps = ref({})

const formulaireVide = () => ({ libelle: '', date_debut: '', date_fin: '' })
const champs = ref(formulaireVide())

const estModification = computed(() => anneeEditee.value !== null)
const titreDialogue = computed(() =>
  estModification.value ? "Modifier l'année universitaire" : 'Nouvelle année universitaire',
)

function ouvrirAjout() {
  anneeEditee.value = null
  champs.value = formulaireVide()
  erreurFormulaire.value = ''
  erreursChamps.value = {}
  dialogue.value = true
}

function ouvrirModification(annee) {
  anneeEditee.value = annee
  champs.value = {
    libelle: annee.libelle ?? '',
    date_debut: annee.date_debut ?? '',
    date_fin: annee.date_fin ?? '',
  }
  erreurFormulaire.value = ''
  erreursChamps.value = {}
  dialogue.value = true
}

const obligatoire = (libelle) => (valeur) =>
  Boolean(String(valeur ?? '').trim()) || `${libelle} est obligatoire.`

const regleLibelle = (valeur) =>
  /^\d{4}\/\d{4}$/.test(String(valeur ?? '').trim()) ||
  'Le libellé doit avoir la forme « 2026/2027 ».'

const regleDateFin = (valeur) => {
  if (!String(valeur ?? '').trim()) return 'La date de fin est obligatoire.'
  if (!champs.value.date_debut) return true

  return (
    String(valeur) > String(champs.value.date_debut) ||
    'La date de fin doit être postérieure à la date de début.'
  )
}

async function enregistrer() {
  const { valid } = await formulaire.value.validate()
  if (!valid) return

  enregistrement.value = true
  erreurFormulaire.value = ''
  erreursChamps.value = {}

  const payload = {
    libelle: champs.value.libelle.trim(),
    date_debut: champs.value.date_debut,
    date_fin: champs.value.date_fin,
  }

  const modification = estModification.value

  try {
    if (modification) {
      await modifierAnnee(anneeEditee.value.id, payload)
    } else {
      await creerAnnee(payload)
    }

    dialogue.value = false
    notifier(modification ? 'Année universitaire modifiée.' : 'Année universitaire ajoutée.')
    await charger()
  } catch (error) {
    erreursChamps.value = error.errors ?? {}
    erreurFormulaire.value = messageErreur(error, "L'enregistrement a échoué.")
  } finally {
    enregistrement.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Activation                                                          */
/* ------------------------------------------------------------------ */

const anneeAActiver = ref(null)
const archiverPrecedente = ref(true)
const activationEnCours = ref(false)

const texteActivation = computed(() => {
  if (!anneeAActiver.value) return ''

  if (!anneeActive.value) {
    return `Les nouveaux comptes et les groupes organisationnels seront rattachés à ${anneeAActiver.value.libelle}.`
  }

  return `L'année ${anneeActive.value.libelle} deviendra inactive. Les nouveaux comptes et les groupes organisationnels seront rattachés à ${anneeAActiver.value.libelle}.`
})

function demanderActivation(annee) {
  anneeAActiver.value = annee
  archiverPrecedente.value = true
}

async function confirmerActivation() {
  if (!anneeAActiver.value) return

  activationEnCours.value = true

  try {
    await activerAnnee(anneeAActiver.value.id, {
      archiver_precedente: archiverPrecedente.value,
    })

    const libelle = anneeAActiver.value.libelle
    anneeAActiver.value = null
    notifier(`Année ${libelle} activée.`)
    await charger()
  } catch (error) {
    anneeAActiver.value = null
    notifier(messageErreur(error, "L'activation a échoué."), 'error')
  } finally {
    activationEnCours.value = false
  }
}
</script>

<template>
  <div class="annees">
    <header class="annees__entete">
      <div class="annees__titre">
        <h1 class="text-h5 font-weight-medium">Années universitaires</h1>
        <p class="text-body-2 text-medium-emphasis annees__sous-titre">
          Une seule année peut être active. Les groupes organisationnels et les inscriptions y sont
          rattachés.
        </p>
      </div>

      <v-btn color="primary" prepend-icon="mdi-plus" @click="ouvrirAjout"> Ajouter une année </v-btn>
    </header>

    <v-alert v-if="erreurChargement" type="error" variant="tonal" density="compact">
      {{ erreurChargement }}
      <template #append>
        <v-btn variant="text" size="small" @click="charger">Réessayer</v-btn>
      </template>
    </v-alert>

    <v-card variant="outlined" class="annees__carte">
      <v-card-text class="annees__hero">
        <span class="annees__hero-icone">
          <v-icon icon="mdi-calendar-range" size="30" />
        </span>

        <div class="annees__hero-texte">
          <span class="text-caption text-medium-emphasis">Année active</span>
          <span class="annees__hero-libelle">
            {{ anneeActive?.libelle ?? 'Aucune année active' }}
          </span>
        </div>

        <v-chip
          :color="anneeActive ? 'success' : undefined"
          variant="tonal"
          size="small"
          class="annees__hero-etat"
        >
          {{ anneeActive ? 'Plateforme ouverte' : 'Plateforme fermée' }}
        </v-chip>
      </v-card-text>

      <v-data-table
        :headers="entetes"
        :items="annees"
        :loading="chargement"
        :mobile="xs"
        :items-per-page="-1"
        item-value="id"
        density="comfortable"
        hide-default-footer
        no-data-text="Aucune année universitaire enregistrée."
        class="annees__table"
      >
        <template #[`item.libelle`]="{ item }">
          <span class="font-weight-medium">{{ item.libelle }}</span>
        </template>

        <template #[`item.date_debut`]="{ item }">
          <span class="text-medium-emphasis">{{ formaterDate(item.date_debut) }}</span>
        </template>

        <template #[`item.date_fin`]="{ item }">
          <span class="text-medium-emphasis">{{ formaterDate(item.date_fin) }}</span>
        </template>

        <template #[`item.etat`]="{ item }">
          <v-chip :color="couleurEtat(item)" variant="tonal" size="small">
            {{ detailEtat(item).libelle }}
          </v-chip>
        </template>

        <template #[`item.groupes`]="{ item }">
          {{ libelleGroupes(item) }}
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn variant="text" size="small" @click="ouvrirModification(item)"> Modifier </v-btn>

          <v-btn
            v-if="!item.active"
            variant="outlined"
            color="primary"
            size="small"
            class="ml-2"
            @click="demanderActivation(item)"
          >
            Activer
          </v-btn>
        </template>
      </v-data-table>
    </v-card>

    <v-alert type="warning" variant="tonal" density="compact" icon="mdi-alert-outline">
      Si aucune année n'est active, la création de compte est bloquée et la génération des groupes
      organisationnels est impossible.
    </v-alert>

    <!-- Dialogue d'ajout / modification -->
    <v-dialog v-model="dialogue" max-width="520" persistent>
      <v-card>
        <v-card-title class="text-h6 font-weight-medium">{{ titreDialogue }}</v-card-title>

        <v-card-text>
          <v-alert
            v-if="erreurFormulaire"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ erreurFormulaire }}
          </v-alert>

          <v-form ref="formulaire" @submit.prevent="enregistrer">
            <v-text-field
              v-model="champs.libelle"
              label="Libellé"
              placeholder="2028/2029"
              hint="Format attendu : 2028/2029"
              persistent-hint
              :rules="[obligatoire('Le libellé'), regleLibelle]"
              :error-messages="erreursChamps.libelle"
              autofocus
              class="mb-4"
            />

            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="champs.date_debut"
                  type="date"
                  label="Date de début"
                  :rules="[obligatoire('La date de début')]"
                  :error-messages="erreursChamps.date_debut"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="champs.date_fin"
                  type="date"
                  label="Date de fin"
                  :rules="[regleDateFin]"
                  :error-messages="erreursChamps.date_fin"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="enregistrement" @click="dialogue = false">
            Annuler
          </v-btn>
          <v-btn color="primary" :loading="enregistrement" @click="enregistrer">
            {{ estModification ? 'Enregistrer' : 'Ajouter' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirmation d'activation -->
    <v-dialog
      :model-value="anneeAActiver !== null"
      max-width="520"
      @update:model-value="(ouverte) => !ouverte && (anneeAActiver = null)"
    >
      <v-card v-if="anneeAActiver">
        <v-card-title class="text-h6 font-weight-medium">
          Activer l'année {{ anneeAActiver.libelle }} ?
        </v-card-title>

        <v-card-text>
          <p class="annees__confirmation-texte">{{ texteActivation }}</p>

          <v-checkbox
            v-if="anneeActive"
            v-model="archiverPrecedente"
            color="primary"
            density="compact"
            hide-details
            :label="`Archiver les groupes organisationnels de ${anneeActive.libelle} (lecture seule)`"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="activationEnCours" @click="anneeAActiver = null">
            Annuler
          </v-btn>
          <v-btn color="primary" :loading="activationEnCours" @click="confirmerActivation">
            Activer
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.visible" :color="snackbar.color" timeout="5000">
      {{ snackbar.message }}
      <template #actions>
        <v-btn variant="text" @click="snackbar.visible = false">Fermer</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<style scoped>
.annees {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.annees__entete {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.annees__titre {
  flex: 1;
  min-width: 240px;
}

.annees__titre h1 {
  margin: 0;
}

.annees__sous-titre {
  margin: 4px 0 0;
}

.annees__carte {
  overflow: hidden;
}

.annees__hero {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  border-bottom: 1px solid rgb(var(--v-theme-outline));
}

.annees__hero-icone {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 12px;
  background: rgb(var(--v-theme-primary-soft));
  color: rgb(var(--v-theme-primary));
}

.annees__hero-texte {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 180px;
}

.annees__hero-libelle {
  font-size: 24px;
  line-height: 32px;
  font-weight: 500;
}

.annees__table :deep(td),
.annees__table :deep(th) {
  white-space: nowrap;
}

.annees__confirmation-texte {
  margin: 0 0 12px;
}

@media (max-width: 599px) {
  .annees__entete :deep(.v-btn) {
    width: 100%;
  }
}
</style>
