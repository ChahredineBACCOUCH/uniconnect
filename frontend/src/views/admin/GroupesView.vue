<script setup>
import { computed, ref } from 'vue'
import { useDisplay } from 'vuetify'

import {
  apercuGeneration,
  archiverGroupe,
  genererGroupes,
  recupererFiltresGroupes,
  recupererGroupes,
  synchroniserGroupe,
} from '@/api/groupes'
import { messageErreur } from '@/utils/espaces'
import {
  detailEtatGroupe,
  libelleClasses,
  libelleProprietaire,
  libelleTypeGroupe,
  messageGeneration,
  messageSynchronisation,
  resumeGeneration,
  texteBoutonGeneration,
  texteGeneration,
} from '@/utils/groupes'

/*
 * SCRUM-83 — A06 « Groupes organisationnels ».
 *
 * L'administration consulte les groupes de l'année active, les filtre, les
 * synchronise avec les référentiels et les archive. La génération est en deux
 * temps : aperçu (aucune écriture), puis confirmation.
 */
const { xs } = useDisplay()

const chargement = ref(false)
const erreurChargement = ref('')
const groupes = ref([])
const annee = ref(null)
const total = ref(0)

const options = ref({ filieres: [], niveaux: [], classes: [], enseignants: [] })

const filtres = ref({
  filiere_id: null,
  niveau_id: null,
  classe_id: null,
  enseignant_id: null,
})

const filtresActifs = computed(() => Object.values(filtres.value).some((valeur) => valeur !== null))

const texteVide = computed(() => {
  if (!annee.value) return 'Aucune année active : activez une année avant de générer les groupes.'
  if (filtresActifs.value) return 'Aucun groupe ne correspond aux filtres.'

  return 'Aucun groupe pour cette année. Utilisez « Générer les groupes ».'
})

async function charger() {
  chargement.value = true
  erreurChargement.value = ''

  try {
    const resultat = await recupererGroupes(filtres.value)
    annee.value = resultat.annee
    groupes.value = resultat.groupes
    total.value = resultat.total
  } catch (error) {
    groupes.value = []
    total.value = 0
    erreurChargement.value = messageErreur(error, 'Les groupes n’ont pas pu être chargés.')
  } finally {
    chargement.value = false
  }
}

async function chargerFiltres() {
  try {
    options.value = await recupererFiltresGroupes()
    if (options.value.annee) annee.value = options.value.annee
  } catch {
    // Les filtres sont un confort : la liste reste utilisable sans eux.
  }
}

async function chargerTout() {
  await Promise.all([charger(), chargerFiltres()])
}

chargerTout()

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
  { title: 'Groupe', key: 'nom', sortable: false },
  { title: 'Type', key: 'type', sortable: false },
  { title: 'Classe', key: 'classes', sortable: false },
  { title: 'Propriétaire', key: 'proprietaire', sortable: false },
  { title: 'Membres', key: 'membres', sortable: false, align: 'end' },
  { title: 'État', key: 'etat', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 230 },
]

/* ------------------------------------------------------------------ */
/* Génération                                                          */
/* ------------------------------------------------------------------ */

const dialogueGeneration = ref(false)
const apercu = ref(null)
const chargementApercu = ref(false)
const generationEnCours = ref(false)
const erreurGeneration = ref('')
const derniereGeneration = ref('')

const resume = computed(() => resumeGeneration(apercu.value))
const titreDialogue = computed(() =>
  annee.value ? `Générer les groupes de ${annee.value.libelle}` : 'Générer les groupes',
)

async function ouvrirGeneration() {
  dialogueGeneration.value = true
  apercu.value = null
  erreurGeneration.value = ''
  chargementApercu.value = true

  try {
    apercu.value = await apercuGeneration()
  } catch (error) {
    // 422 sans année active : le message de l'API s'affiche dans le dialogue.
    erreurGeneration.value = messageErreur(error, 'L’aperçu de la génération a échoué.')
  } finally {
    chargementApercu.value = false
  }
}

async function confirmerGeneration() {
  generationEnCours.value = true
  erreurGeneration.value = ''

  try {
    const resultat = await genererGroupes()
    const message = messageGeneration(resultat)

    dialogueGeneration.value = false
    derniereGeneration.value = message
    notifier(message)
    await chargerTout()
  } catch (error) {
    erreurGeneration.value = messageErreur(error, 'La génération a échoué.')
  } finally {
    generationEnCours.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Actions par ligne                                                   */
/* ------------------------------------------------------------------ */

const actionEnCours = ref(null)

async function synchroniser(groupe) {
  actionEnCours.value = groupe.id

  try {
    const resultat = await synchroniserGroupe(groupe.id)
    notifier(messageSynchronisation(resultat, groupe.nom))
    await charger()
  } catch (error) {
    notifier(messageErreur(error, 'La synchronisation a échoué.'), 'error')
  } finally {
    actionEnCours.value = null
  }
}

const groupeAArchiver = ref(null)
const archivageEnCours = ref(false)

function demanderArchivage(groupe) {
  groupeAArchiver.value = groupe
}

async function confirmerArchivage() {
  const groupe = groupeAArchiver.value
  if (!groupe) return

  archivageEnCours.value = true

  try {
    await archiverGroupe(groupe.id)
    groupeAArchiver.value = null
    notifier(`« ${groupe.nom} » archivé.`)
    await charger()
  } catch (error) {
    notifier(messageErreur(error, 'L’archivage a échoué.'), 'error')
  } finally {
    archivageEnCours.value = false
  }
}
</script>

<template>
  <div class="groupes">
    <header class="groupes__entete">
      <div class="groupes__titre">
        <h1 class="text-h5 font-weight-medium">Groupes organisationnels</h1>
        <p class="text-body-2 text-medium-emphasis groupes__sous-titre">
          <template v-if="annee">
            Année {{ annee.libelle }} · {{ total }} groupes générés depuis les référentiels
          </template>
          <template v-else> Aucune année active · la génération est impossible </template>
        </p>
      </div>

      <v-tooltip text="La création manuelle sera disponible dans une prochaine version.">
        <template #activator="{ props }">
          <span v-bind="props">
            <v-btn variant="outlined" prepend-icon="mdi-plus" disabled>Créer manuellement</v-btn>
          </span>
        </template>
      </v-tooltip>

      <v-btn color="primary" prepend-icon="mdi-sync" @click="ouvrirGeneration">
        Générer les groupes
      </v-btn>
    </header>

    <v-alert v-if="erreurChargement" type="error" variant="tonal" density="compact">
      {{ erreurChargement }}
      <template #append>
        <v-btn variant="text" size="small" @click="charger">Réessayer</v-btn>
      </template>
    </v-alert>

    <v-alert
      v-if="derniereGeneration"
      type="success"
      variant="tonal"
      density="compact"
      closable
      @click:close="derniereGeneration = ''"
    >
      {{ derniereGeneration }} Les enseignants responsables en sont propriétaires.
    </v-alert>

    <v-alert
      v-if="!annee && !chargement"
      type="warning"
      variant="tonal"
      density="compact"
      icon="mdi-alert-outline"
    >
      Aucune année universitaire n'est active : la liste est vide et la génération répondra par une
      erreur. <router-link :to="{ name: 'admin-annees' }">Activer une année</router-link>.
    </v-alert>

    <v-card variant="outlined" class="groupes__carte">
      <v-card-text class="groupes__filtres">
        <v-select
          v-model="filtres.filiere_id"
          :items="options.filieres"
          item-title="libelle"
          item-value="id"
          label="Filière"
          placeholder="Toutes les filières"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          @update:model-value="charger"
        />

        <v-select
          v-model="filtres.niveau_id"
          :items="options.niveaux"
          item-title="libelle"
          item-value="id"
          label="Niveau"
          placeholder="Tous les niveaux"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          @update:model-value="charger"
        />

        <v-select
          v-model="filtres.classe_id"
          :items="options.classes"
          item-title="code"
          item-value="id"
          label="Classe"
          placeholder="Toutes les classes"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          @update:model-value="charger"
        />

        <v-select
          v-model="filtres.enseignant_id"
          :items="options.enseignants"
          item-title="nom_complet"
          item-value="id"
          label="Enseignant"
          placeholder="Tous les enseignants"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          @update:model-value="charger"
        />
      </v-card-text>

      <v-data-table
        :headers="entetes"
        :items="groupes"
        :loading="chargement"
        :mobile="xs"
        :items-per-page="-1"
        item-value="id"
        density="comfortable"
        hide-default-footer
        :no-data-text="texteVide"
        class="groupes__table"
      >
        <template #[`item.nom`]="{ item }">
          <span class="font-weight-medium">{{ item.nom }}</span>
        </template>

        <template #[`item.type`]="{ item }">
          <v-chip
            :color="item.type === 'CLASSE' ? 'primary' : undefined"
            variant="tonal"
            size="small"
          >
            {{ libelleTypeGroupe(item) }}
          </v-chip>
        </template>

        <template #[`item.classes`]="{ item }">
          <span class="text-medium-emphasis">{{ libelleClasses(item) }}</span>
        </template>

        <template #[`item.proprietaire`]="{ item }">
          {{ libelleProprietaire(item) }}
        </template>

        <template #[`item.membres`]="{ item }">
          {{ item.nombre_membres }}
        </template>

        <template #[`item.etat`]="{ item }">
          <v-chip
            :color="detailEtatGroupe(item).color ?? undefined"
            variant="tonal"
            size="small"
          >
            {{ detailEtatGroupe(item).libelle }}
          </v-chip>
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn
            variant="text"
            size="small"
            :loading="actionEnCours === item.id"
            :disabled="item.etat === 'ARCHIVE'"
            @click="synchroniser(item)"
          >
            Synchroniser
          </v-btn>

          <v-btn
            variant="text"
            size="small"
            color="medium-emphasis"
            :disabled="item.etat === 'ARCHIVE'"
            @click="demanderArchivage(item)"
          >
            Archiver
          </v-btn>
        </template>
      </v-data-table>
    </v-card>

    <!-- Génération en deux temps : aperçu puis confirmation -->
    <v-dialog v-model="dialogueGeneration" max-width="560" persistent>
      <v-card>
        <v-card-title class="text-h6 font-weight-medium">{{ titreDialogue }}</v-card-title>

        <v-card-text>
          <p class="groupes__dialogue-texte">
            Un groupe par classe et un groupe par matière, à partir des référentiels académiques.
            Les groupes existants ne sont pas dupliqués.
          </p>

          <v-alert
            v-if="erreurGeneration"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ erreurGeneration }}
          </v-alert>

          <v-skeleton-loader v-else-if="chargementApercu" type="article" />

          <template v-else-if="apercu">
            <p class="groupes__dialogue-resume">{{ texteGeneration(apercu) }}</p>

            <div class="groupes__resume">
              <div>
                <b>{{ resume.aCreer }}</b>
                <span class="text-medium-emphasis">à créer</span>
              </div>
              <div>
                <b>{{ resume.existants }}</b>
                <span class="text-medium-emphasis">déjà existants</span>
              </div>
              <div>
                <b>{{ resume.membres }}</b>
                <span class="text-medium-emphasis">membres à ajouter</span>
              </div>
            </div>
          </template>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="generationEnCours" @click="dialogueGeneration = false">
            Annuler
          </v-btn>

          <v-btn
            v-if="apercu"
            color="primary"
            :loading="generationEnCours"
            @click="confirmerGeneration"
          >
            {{ texteBoutonGeneration(apercu) }}
          </v-btn>

          <v-btn v-else-if="!chargementApercu && erreurGeneration" color="primary" @click="ouvrirGeneration">
            Réessayer
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirmation d'archivage -->
    <v-dialog
      :model-value="groupeAArchiver !== null"
      max-width="480"
      @update:model-value="(ouverte) => !ouverte && (groupeAArchiver = null)"
    >
      <v-card v-if="groupeAArchiver">
        <v-card-title class="text-h6 font-weight-medium">
          Archiver « {{ groupeAArchiver.nom }} » ?
        </v-card-title>

        <v-card-text>
          Le groupe restera consultable mais ne recevra plus de nouveaux membres. Cette action est
          définitive pour l'année en cours.
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="archivageEnCours" @click="groupeAArchiver = null">
            Annuler
          </v-btn>
          <v-btn color="error" :loading="archivageEnCours" @click="confirmerArchivage">
            Archiver
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
.groupes {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.groupes__entete {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.groupes__titre {
  flex: 1;
  min-width: 240px;
}

.groupes__titre h1 {
  margin: 0;
}

.groupes__sous-titre {
  margin: 4px 0 0;
}

.groupes__carte {
  overflow: hidden;
}

.groupes__filtres {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  border-bottom: 1px solid rgb(var(--v-theme-outline));
}

.groupes__table :deep(td),
.groupes__table :deep(th) {
  white-space: nowrap;
}

.groupes__dialogue-texte {
  margin: 0 0 16px;
}

.groupes__dialogue-resume {
  margin: 0 0 12px;
  font-weight: 500;
}

.groupes__resume {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.groupes__resume div {
  display: flex;
  flex-direction: column;
  border-radius: 8px;
  padding: 12px;
  background: rgb(var(--v-theme-surface-variant));
}

.groupes__resume b {
  font-size: 24px;
  font-weight: 500;
  line-height: 32px;
}

@media (max-width: 959px) {
  .groupes__filtres {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 599px) {
  .groupes__entete :deep(.v-btn) {
    width: 100%;
  }

  .groupes__filtres,
  .groupes__resume {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
