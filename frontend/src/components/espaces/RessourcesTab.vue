<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

import {
  creerChapitre,
  creerRessource,
  deplacerRessource,
  modifierRessource,
  recupererChapitres,
  supprimerRessource,
  telechargerRessource,
} from '@/api/ressources'
import { estGestionnaire, formaterDate, messageErreur } from '@/utils/espaces'
import {
  FILTRES_RESSOURCES,
  categorieRessource,
  couleurRessource,
  estPdf,
  filtrerRessources,
  formaterTaille,
  iconeRessource,
  libelleTypeRessource,
  nomTelechargement,
} from '@/utils/ressources'

/*
 * SCRUM-127 · E05 « Ressources » : chapitres du cours et leurs supports.
 * Lecture ouverte à tous les membres ; création et gestion réservées aux
 * enseignants (propriétaire ou co-gestionnaire) et bloquées sur un espace
 * archivé. Les règles sont appliquées par l'API : l'écran masque seulement les
 * actions inutiles.
 */
const props = defineProps({
  espace: { type: Object, required: true },
})
const emit = defineEmits(['actualiser'])

const chapitres = ref([])
const chargement = ref(true)
const erreur = ref('')
const recherche = ref('')
const filtre = ref('TOUT')
const panneaux = ref([])

/*
 * SCRUM-143 : ressource ouverte depuis une source citée par l'Assistant IA
 * (`?ressource=<id>`) : son chapitre est ouvert, la ligne est surlignée et
 * ramenée à l'écran.
 */
const route = useRoute()
const ressourceEnEvidence = ref(null)

const gestionnaire = computed(() => estGestionnaire(props.espace.role))
const archive = computed(() => props.espace.etat === 'ARCHIVE')
const peutGerer = computed(() => gestionnaire.value && !archive.value)

const chapitresAffiches = computed(() =>
  chapitres.value.map((chapitre) => ({
    ...chapitre,
    ressourcesFiltrees: filtrerRessources(chapitre.ressources ?? [], recherche.value, filtre.value),
  })),
)

const totalRessources = computed(() =>
  chapitres.value.reduce((total, chapitre) => total + (chapitre.ressources?.length ?? 0), 0),
)

const optionsChapitres = computed(() =>
  chapitres.value.map((chapitre) => ({ title: chapitre.titre, value: chapitre.id })),
)

const snackbar = ref({ actif: false, texte: '' })

/* Dialogue « Nouveau chapitre ». */
const dialogueChapitre = ref(false)
const nouveauChapitre = ref('')
const erreursChapitre = ref({})
const enregistrementChapitre = ref(false)

/* Dialogue « Ajouter une ressource ». */
const dialogueRessource = ref(false)
const nouvelleRessource = ref({ titre: '', description: '', mode: 'fichier', url: '' })
const fichier = ref(null)
const chapitreRessource = ref(null)
const erreursRessource = ref({})
const enregistrementRessource = ref(false)

/* Dialogue « Renommer la ressource ». */
const dialogueRenommer = ref(false)
const ressourceRenommee = ref(null)
const nouveauTitre = ref('')
const erreursRenommage = ref({})
const enregistrementRenommage = ref(false)

/* Dialogue « Déplacer vers un chapitre ». */
const dialogueDeplacer = ref(false)
const ressourceADeplacer = ref(null)
const chapitreDestination = ref(null)
const deplacementEnCours = ref(false)

/* Dialogue « Supprimer la ressource ». */
const dialogueSupprimer = ref(false)
const ressourceASupprimer = ref(null)
const suppressionEnCours = ref(false)

const chapitresDestination = computed(() =>
  chapitres.value
    .filter((chapitre) => chapitre.id !== ressourceADeplacer.value?.chapitre_id)
    .map((chapitre) => ({ title: chapitre.titre, value: chapitre.id })),
)

onMounted(charger)

async function charger() {
  chargement.value = true
  erreur.value = ''

  try {
    chapitres.value = await recupererChapitres(props.espace.id)
    // Tous les chapitres sont ouverts : la recherche porte sur l'ensemble.
    panneaux.value = chapitres.value.map((chapitre) => chapitre.id)
  } catch (error) {
    erreur.value = messageErreur(error, "Les ressources n'ont pas pu être chargées.")
  } finally {
    chargement.value = false
  }

  await mettreEnEvidence(route?.query?.ressource)
}

async function mettreEnEvidence(valeur) {
  const id = Number(valeur)
  if (!id) return

  const chapitre = chapitres.value.find((element) =>
    (element.ressources ?? []).some((ressource) => ressource.id === id),
  )
  if (!chapitre) {
    if (!erreur.value) afficher("Cette ressource n'existe plus dans l'espace.")
    return
  }

  // La recherche ou le filtre en cours ne doivent pas cacher la ressource demandée.
  recherche.value = ''
  filtre.value = 'TOUT'
  if (!panneaux.value.includes(chapitre.id)) panneaux.value.push(chapitre.id)
  ressourceEnEvidence.value = id

  await nextTick()
  document
    .getElementById(`ressource-${id}`)
    ?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

// Autre source cliquée alors que l'onglet est déjà ouvert.
watch(
  () => route?.query?.ressource,
  (valeur) => {
    if (!chargement.value) mettreEnEvidence(valeur)
  },
)

function afficher(texte) {
  snackbar.value = { actif: true, texte }
}

function libelleNombre(ressources) {
  const nombre = ressources.length
  return `${nombre} ressource${nombre === 1 ? '' : 's'}`
}

function messageVideChapitre(chapitre) {
  if (!chapitre.ressources?.length) return 'Aucune ressource dans ce chapitre.'
  return 'Aucune ressource ne correspond à la recherche ou au filtre.'
}

/** « PDF · 1,8 Mo » ; la taille n'a pas de sens pour un lien. */
function detailType(ressource) {
  const libelle = libelleTypeRessource(ressource)
  if (categorieRessource(ressource) === 'LIENS') return libelle
  return `${libelle} · ${formaterTaille(ressource.taille)}`
}

function ouvrirChapitre() {
  nouveauChapitre.value = ''
  erreursChapitre.value = {}
  dialogueChapitre.value = true
}

async function enregistrerChapitre() {
  const titre = nouveauChapitre.value.trim()
  erreursChapitre.value = {}

  if (!titre) {
    erreursChapitre.value = { titre: ['Le titre du chapitre est obligatoire.'] }
    return
  }

  enregistrementChapitre.value = true

  try {
    const chapitre = await creerChapitre(props.espace.id, { titre })
    chapitres.value.push(chapitre)
    panneaux.value.push(chapitre.id)
    dialogueChapitre.value = false
    afficher('Chapitre créé.')
    emit('actualiser')
  } catch (error) {
    erreursChapitre.value = error.errors ?? {}
    afficher(messageErreur(error, "Le chapitre n'a pas pu être créé."))
  } finally {
    enregistrementChapitre.value = false
  }
}

function ouvrirRessource(chapitreId = null) {
  nouvelleRessource.value = { titre: '', description: '', mode: 'fichier', url: '' }
  fichier.value = null
  erreursRessource.value = {}
  chapitreRessource.value = chapitreId ?? chapitres.value[0]?.id ?? null
  dialogueRessource.value = true
}

function fichierChoisi(valeur) {
  return Array.isArray(valeur) ? (valeur[0] ?? null) : valeur
}

async function enregistrerRessource() {
  const saisie = nouvelleRessource.value
  const titre = saisie.titre.trim()
  const fichierSelectionne = fichierChoisi(fichier.value)
  const erreurs = {}

  if (!titre) erreurs.titre = ['Le titre de la ressource est obligatoire.']
  if (!chapitreRessource.value) erreurs.chapitre_id = ['Choisissez un chapitre.']
  if (saisie.mode === 'fichier' && !fichierSelectionne) {
    erreurs.fichier = ['Ajoutez un fichier ou choisissez un lien.']
  }
  if (saisie.mode === 'lien' && !saisie.url.trim()) {
    erreurs.url = ['Indiquez l’adresse du lien.']
  }

  erreursRessource.value = erreurs
  if (Object.keys(erreurs).length) return

  enregistrementRessource.value = true

  try {
    await creerRessource(chapitreRessource.value, {
      titre,
      description: saisie.description.trim() || null,
      fichier: saisie.mode === 'fichier' ? fichierSelectionne : null,
      url: saisie.mode === 'lien' ? saisie.url.trim() : null,
    })
    dialogueRessource.value = false
    afficher('Ressource ajoutée.')
    await charger()
    emit('actualiser')
  } catch (error) {
    erreursRessource.value = error.errors ?? {}
    afficher(messageErreur(error, "La ressource n'a pas pu être ajoutée."))
  } finally {
    enregistrementRessource.value = false
  }
}

function ouvrirRenommage(ressource) {
  ressourceRenommee.value = ressource
  nouveauTitre.value = ressource.titre
  erreursRenommage.value = {}
  dialogueRenommer.value = true
}

async function enregistrerRenommage() {
  const titre = nouveauTitre.value.trim()
  erreursRenommage.value = {}

  if (!titre) {
    erreursRenommage.value = { titre: ['Le titre de la ressource est obligatoire.'] }
    return
  }

  enregistrementRenommage.value = true

  try {
    await modifierRessource(ressourceRenommee.value.id, { titre })
    dialogueRenommer.value = false
    afficher('Ressource renommée.')
    await charger()
  } catch (error) {
    erreursRenommage.value = error.errors ?? {}
    afficher(messageErreur(error, 'La ressource n’a pas pu être renommée.'))
  } finally {
    enregistrementRenommage.value = false
  }
}

function ouvrirDeplacement(ressource) {
  ressourceADeplacer.value = ressource
  chapitreDestination.value = null
  dialogueDeplacer.value = true
}

async function deplacer() {
  if (!chapitreDestination.value) return

  deplacementEnCours.value = true

  try {
    await deplacerRessource(ressourceADeplacer.value.id, chapitreDestination.value)
    dialogueDeplacer.value = false
    afficher('Ressource déplacée.')
    await charger()
  } catch (error) {
    afficher(messageErreur(error, 'La ressource n’a pas pu être déplacée.'))
  } finally {
    deplacementEnCours.value = false
  }
}

function ouvrirSuppression(ressource) {
  ressourceASupprimer.value = ressource
  dialogueSupprimer.value = true
}

async function supprimer() {
  suppressionEnCours.value = true

  try {
    await supprimerRessource(ressourceASupprimer.value.id)
    dialogueSupprimer.value = false
    afficher('Ressource supprimée.')
    await charger()
  } catch (error) {
    afficher(messageErreur(error, 'La ressource n’a pas pu être supprimée.'))
  } finally {
    suppressionEnCours.value = false
  }
}

async function telecharger(ressource) {
  try {
    const blob = await telechargerRessource(ressource.id)
    const url = URL.createObjectURL(blob)
    const lien = document.createElement('a')

    lien.href = url
    lien.download = nomTelechargement(ressource)
    document.body.appendChild(lien)
    lien.click()
    lien.remove()
    URL.revokeObjectURL(url)
  } catch (error) {
    afficher(messageErreur(error, 'Le téléchargement a échoué.'))
  }
}
</script>

<template>
  <div class="ressources">
    <div class="ressources__barre">
      <v-text-field
        v-model="recherche"
        label="Rechercher une ressource"
        prepend-inner-icon="mdi-magnify"
        density="compact"
        hide-details
        clearable
        class="ressources__recherche"
      />

      <div class="ressources__filtres" role="group" aria-label="Filtrer par type">
        <v-chip
          v-for="option in FILTRES_RESSOURCES"
          :key="option.value"
          :color="filtre === option.value ? 'primary' : undefined"
          :variant="filtre === option.value ? 'flat' : 'outlined'"
          class="text-none"
          @click="filtre = option.value"
        >
          {{ option.label }}
        </v-chip>
      </div>

      <v-spacer />

      <template v-if="peutGerer">
        <v-btn
          variant="outlined"
          color="primary"
          prepend-icon="mdi-folder-plus-outline"
          class="text-none"
          @click="ouvrirChapitre"
        >
          Nouveau chapitre
        </v-btn>
        <v-btn
          color="primary"
          prepend-icon="mdi-upload-outline"
          class="text-none"
          @click="ouvrirRessource()"
        >
          Ajouter une ressource
        </v-btn>
      </template>
    </div>

    <p class="text-caption text-medium-emphasis">
      Les ressources ne sont pas soumises à l'expiration des messages. Les étudiants les consultent
      en lecture seule.
    </p>

    <v-alert v-if="erreur" type="error" variant="tonal" density="compact">
      {{ erreur }}
      <template #append>
        <v-btn variant="text" size="small" class="text-none" @click="charger">Réessayer</v-btn>
      </template>
    </v-alert>

    <v-skeleton-loader v-else-if="chargement" type="list-item-two-line@3" />

    <template v-else>
      <div v-if="!chapitres.length" class="ressources__vide">
        <v-icon icon="mdi-folder-open-outline" size="48" class="mb-2" />
        <p class="text-body-1">Aucun chapitre pour le moment.</p>
        <p v-if="peutGerer" class="text-caption text-medium-emphasis">
          Créez un premier chapitre, puis ajoutez-y vos supports de cours.
        </p>
        <v-btn
          v-if="peutGerer"
          color="primary"
          prepend-icon="mdi-folder-plus-outline"
          class="text-none mt-4"
          @click="ouvrirChapitre"
        >
          Nouveau chapitre
        </v-btn>
      </div>

      <v-expansion-panels v-else v-model="panneaux" multiple class="ressources__panneaux">
        <v-expansion-panel
          v-for="chapitre in chapitresAffiches"
          :key="chapitre.id"
          :value="chapitre.id"
          eager
        >
          <v-expansion-panel-title>
            <div class="ressources__chapitre">
              <v-icon icon="mdi-folder-outline" color="primary" size="24" class="mr-3" />
              <div>
                <div class="text-subtitle-1 font-weight-medium">{{ chapitre.titre }}</div>
                <div class="text-caption text-medium-emphasis">
                  {{ libelleNombre(chapitre.ressourcesFiltrees) }}
                  <template
                    v-if="chapitre.ressourcesFiltrees.length !== chapitre.ressources.length"
                  >
                    sur {{ libelleNombre(chapitre.ressources) }}
                  </template>
                </div>
              </div>
            </div>
          </v-expansion-panel-title>

          <v-expansion-panel-text>
            <div v-if="!chapitre.ressourcesFiltrees.length" class="ressources__vide-chapitre">
              {{ messageVideChapitre(chapitre) }}
            </div>

            <v-table v-else density="comfortable" class="ressources__table">
              <thead>
                <tr>
                  <th scope="col">Ressource</th>
                  <th scope="col">Type</th>
                  <th scope="col">Ajoutée le</th>
                  <th scope="col" class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="ressource in chapitre.ressourcesFiltrees"
                  :id="`ressource-${ressource.id}`"
                  :key="ressource.id"
                  :class="{ 'ressources__ligne--evidence': ressource.id === ressourceEnEvidence }"
                  :aria-current="ressource.id === ressourceEnEvidence ? 'true' : undefined"
                >
                  <td>
                    <div class="ressources__ligne">
                      <v-icon
                        :icon="iconeRessource(ressource)"
                        :style="{ color: couleurRessource(ressource) }"
                        size="28"
                      />
                      <div class="ressources__texte">
                        <div class="font-weight-medium">
                          {{ ressource.titre }}
                          <v-chip
                            v-if="gestionnaire && estPdf(ressource) && ressource.indexee_ia"
                            color="success"
                            size="x-small"
                            variant="tonal"
                            class="ml-2"
                          >
                            Indexé pour l'IA
                          </v-chip>
                        </div>
                        <div v-if="ressource.description" class="text-caption text-medium-emphasis">
                          {{ ressource.description }}
                        </div>
                      </div>
                    </div>
                  </td>

                  <td class="text-medium-emphasis">
                    {{ detailType(ressource) }}
                  </td>

                  <td class="text-medium-emphasis">{{ formaterDate(ressource.cree_le) }}</td>

                  <td class="text-right ressources__actions">
                    <v-btn
                      v-if="categorieRessource(ressource) === 'LIENS'"
                      :href="ressource.url"
                      target="_blank"
                      rel="noopener"
                      icon="mdi-open-in-new"
                      variant="text"
                      size="small"
                      :aria-label="`Ouvrir ${ressource.titre}`"
                    />
                    <v-btn
                      v-else
                      icon="mdi-download"
                      variant="text"
                      size="small"
                      :aria-label="`Télécharger ${ressource.titre}`"
                      @click="telecharger(ressource)"
                    />

                    <v-menu v-if="peutGerer" location="bottom end">
                      <template #activator="{ props: menuProps }">
                        <v-btn
                          v-bind="menuProps"
                          icon="mdi-dots-vertical"
                          variant="text"
                          size="small"
                          :aria-label="`Actions pour ${ressource.titre}`"
                        />
                      </template>
                      <v-list density="compact">
                        <v-list-item
                          prepend-icon="mdi-pencil-outline"
                          title="Renommer"
                          @click="ouvrirRenommage(ressource)"
                        />
                        <v-list-item
                          prepend-icon="mdi-folder-move-outline"
                          title="Déplacer vers un chapitre"
                          @click="ouvrirDeplacement(ressource)"
                        />
                        <v-list-item
                          prepend-icon="mdi-delete-outline"
                          title="Supprimer"
                          class="text-error"
                          @click="ouvrirSuppression(ressource)"
                        />
                      </v-list>
                    </v-menu>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>

      <p v-if="chapitres.length" class="text-caption text-medium-emphasis">
        {{ totalRessources }} ressource{{ totalRessources === 1 ? '' : 's' }} au total.
      </p>
    </template>

    <!-- Nouveau chapitre -->
    <v-dialog v-model="dialogueChapitre" max-width="460">
      <v-card title="Nouveau chapitre">
        <v-card-text>
          <v-text-field
            v-model="nouveauChapitre"
            label="Titre du chapitre"
            :error-messages="erreursChapitre.titre"
            autofocus
            @keyup.enter="enregistrerChapitre"
          />
          <p class="text-caption text-medium-emphasis mt-2">
            Les chapitres s'affichent dans leur ordre de création.
          </p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" class="text-none" @click="dialogueChapitre = false">Annuler</v-btn>
          <v-btn
            color="primary"
            class="text-none"
            :loading="enregistrementChapitre"
            @click="enregistrerChapitre"
          >
            Créer
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Ajouter une ressource -->
    <v-dialog v-model="dialogueRessource" max-width="620">
      <v-card title="Ajouter une ressource">
        <v-card-text>
          <v-text-field
            v-model="nouvelleRessource.titre"
            label="Titre"
            :error-messages="erreursRessource.titre"
            class="mb-2"
          />
          <v-textarea
            v-model="nouvelleRessource.description"
            label="Description (facultative)"
            rows="2"
            auto-grow
            :error-messages="erreursRessource.description"
            class="mb-2"
          />
          <v-select
            v-model="chapitreRessource"
            :items="optionsChapitres"
            label="Chapitre"
            :error-messages="erreursRessource.chapitre_id"
            class="mb-2"
          />

          <v-btn-toggle
            v-model="nouvelleRessource.mode"
            mandatory
            divided
            variant="outlined"
            color="primary"
            class="mb-4"
          >
            <v-btn value="fichier" prepend-icon="mdi-paperclip" class="text-none">Fichier</v-btn>
            <v-btn value="lien" prepend-icon="mdi-link-variant" class="text-none">Lien</v-btn>
          </v-btn-toggle>

          <v-file-input
            v-if="nouvelleRessource.mode === 'fichier'"
            v-model="fichier"
            label="Fichier (PDF, Word…)"
            prepend-icon=""
            prepend-inner-icon="mdi-paperclip"
            :error-messages="erreursRessource.fichier"
            show-size
          />
          <v-text-field
            v-else
            v-model="nouvelleRessource.url"
            label="Adresse du lien"
            prepend-inner-icon="mdi-link-variant"
            :error-messages="erreursRessource.url"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" class="text-none" @click="dialogueRessource = false">Annuler</v-btn>
          <v-btn
            color="primary"
            class="text-none"
            :loading="enregistrementRessource"
            @click="enregistrerRessource"
          >
            Ajouter
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Renommer une ressource -->
    <v-dialog v-model="dialogueRenommer" max-width="460">
      <v-card title="Renommer la ressource">
        <v-card-text>
          <v-text-field
            v-model="nouveauTitre"
            label="Nouveau nom"
            :error-messages="erreursRenommage.titre"
            autofocus
            @keyup.enter="enregistrerRenommage"
          />
          <p class="text-caption text-medium-emphasis mt-2">
            Le fichier n'est pas modifié : seul son nom affiché change pour les membres.
          </p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" class="text-none" @click="dialogueRenommer = false">Annuler</v-btn>
          <v-btn
            color="primary"
            class="text-none"
            :loading="enregistrementRenommage"
            @click="enregistrerRenommage"
          >
            Enregistrer
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Déplacer une ressource -->
    <v-dialog v-model="dialogueDeplacer" max-width="520">
      <v-card :title="`Déplacer « ${ressourceADeplacer?.titre ?? ''} »`">
        <v-card-text>
          <p v-if="!chapitresDestination.length" class="text-body-2 text-medium-emphasis">
            Créez un autre chapitre pour pouvoir déplacer cette ressource.
          </p>
          <v-radio-group v-else v-model="chapitreDestination">
            <v-radio
              v-for="destination in chapitresDestination"
              :key="destination.value"
              :label="destination.title"
              :value="destination.value"
            />
          </v-radio-group>
          <p class="text-caption text-medium-emphasis">
            Chapitre actuel :
            <strong>{{
              chapitres.find((c) => c.id === ressourceADeplacer?.chapitre_id)?.titre
            }}</strong>
          </p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" class="text-none" @click="dialogueDeplacer = false">Annuler</v-btn>
          <v-btn
            color="primary"
            class="text-none"
            :disabled="!chapitreDestination"
            :loading="deplacementEnCours"
            @click="deplacer"
          >
            Déplacer
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Supprimer une ressource -->
    <v-dialog v-model="dialogueSupprimer" max-width="460">
      <v-card :title="`Supprimer « ${ressourceASupprimer?.titre ?? ''} » ?`">
        <v-card-text>
          Les étudiants ne pourront plus consulter ni télécharger cette ressource. Les réponses de
          l'Assistant IA ne la citeront plus.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" class="text-none" @click="dialogueSupprimer = false">Annuler</v-btn>
          <v-btn color="error" class="text-none" :loading="suppressionEnCours" @click="supprimer">
            Supprimer
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.actif" :timeout="4000" location="bottom">
      {{ snackbar.texte }}
    </v-snackbar>
  </div>
</template>

<style scoped>
.ressources {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ressources__barre {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.ressources__recherche {
  flex: 1 1 240px;
  max-width: 420px;
}

.ressources__filtres {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.ressources__panneaux {
  background: transparent;
}

.ressources__chapitre {
  display: flex;
  align-items: center;
  min-width: 0;
}

.ressources__ligne--evidence {
  background: rgb(var(--v-theme-primary-soft));
  box-shadow: inset 4px 0 0 rgb(var(--v-theme-primary));
}

.ressources__table {
  background: transparent;
}

.ressources__ligne {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  min-width: 0;
}

.ressources__texte {
  min-width: 0;
}

.ressources__actions {
  white-space: nowrap;
}

.ressources__vide,
.ressources__vide-chapitre {
  padding: 24px 0;
  text-align: center;
  color: rgb(var(--v-theme-on-surface-variant));
}
</style>
