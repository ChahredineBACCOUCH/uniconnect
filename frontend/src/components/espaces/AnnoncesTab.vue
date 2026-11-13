<script setup>
import { computed, onMounted, ref } from 'vue'

import { publierAnnonce, recupererAnnonces } from '@/api/espaces'
import {
  couleurAuteur,
  estGestionnaire,
  formaterHeure,
  formaterJour,
  initialesAuteur,
  libelleDuree,
  messageErreur,
  nomAuteur,
} from '@/utils/espaces'

const props = defineProps({
  espace: { type: Object, required: true },
})

const MAX_FICHIERS = 5
const DUREE_NOUVEAU = 3 * 24 * 60 * 60 * 1000

const annonces = ref([])
const chargement = ref(true)
const erreurChargement = ref(null)

const dialogueOuvert = ref(false)
const envoiEnCours = ref(false)
const erreurEnvoi = ref(null)
const formulaire = ref({ titre: '', contenu: '', notifier_email: true, fichiers: [] })
const snackbar = ref({ actif: false, texte: '' })

const peutPublier = computed(
  () => estGestionnaire(props.espace.role) && props.espace.etat !== 'ARCHIVE',
)

const intro = computed(() => {
  const base = 'Les annonces sont publiées par les gestionnaires et lues par tous les membres.'

  if (
    props.espace.duree_visibilite_heures === null ||
    props.espace.duree_visibilite_heures === undefined
  ) {
    return base
  }

  return `${base} Elles suivent la règle de visibilité de l'espace (${libelleDuree(
    props.espace.duree_visibilite_heures,
  )}).`
})

const brouillonValide = computed(
  () => formulaire.value.titre.trim() !== '' && formulaire.value.contenu.trim() !== '',
)

onMounted(chargerAnnonces)

async function chargerAnnonces() {
  chargement.value = true
  erreurChargement.value = null

  try {
    annonces.value = await recupererAnnonces(props.espace.id)
  } catch (error) {
    erreurChargement.value = messageErreur(error, "Les annonces n'ont pas pu être chargées.")
  } finally {
    chargement.value = false
  }
}

function estNouvelle(annonce) {
  if (!annonce.cree_le) return false

  const age = Date.now() - new Date(annonce.cree_le).getTime()
  return age >= 0 && age < DUREE_NOUVEAU
}

function formaterDateAnnonce(iso) {
  if (!iso) return ''

  const jour = formaterJour(iso)
  const heure = formaterHeure(iso)
  return heure ? `${jour}, ${heure}` : jour
}

function formaterTaille(octets) {
  if (octets === null || octets === undefined) return ''
  if (octets < 1024) return `${octets} o`
  if (octets < 1024 * 1024) return `${Math.round(octets / 1024)} Ko`

  return `${(octets / (1024 * 1024)).toFixed(1).replace('.', ',')} Mo`
}

function typeFichier(nom) {
  const extension = nom?.split('.').pop()
  return extension && extension !== nom ? extension.toUpperCase() : 'Fichier'
}

function regleFichiers(fichiers) {
  if (!fichiers || fichiers.length <= MAX_FICHIERS) return true
  return `${MAX_FICHIERS} fichiers maximum.`
}

function ouvrirDialogue() {
  erreurEnvoi.value = null
  dialogueOuvert.value = true
}

function fermerDialogue(valeur) {
  if (!valeur && !envoiEnCours.value) dialogueOuvert.value = false
}

async function publier() {
  if (!brouillonValide.value || envoiEnCours.value) return

  erreurEnvoi.value = null
  envoiEnCours.value = true

  try {
    const annonce = await publierAnnonce(props.espace.id, {
      titre: formulaire.value.titre.trim(),
      contenu: formulaire.value.contenu.trim(),
      notifier_email: formulaire.value.notifier_email,
      fichiers: formulaire.value.fichiers ?? [],
    })

    annonces.value = [annonce, ...annonces.value]
    dialogueOuvert.value = false
    formulaire.value = { titre: '', contenu: '', notifier_email: true, fichiers: [] }
    notifier('Annonce publiée.')
  } catch (error) {
    erreurEnvoi.value = messageErreur(error, "L'annonce n'a pas pu être publiée.")
  } finally {
    envoiEnCours.value = false
  }
}

function notifier(texte) {
  snackbar.value = { actif: true, texte }
}
</script>

<template>
  <section class="annonces">
    <div class="annonces__entete">
      <p class="annonces__intro">{{ intro }}</p>

      <v-btn
        v-if="peutPublier"
        color="primary"
        variant="flat"
        prepend-icon="mdi-bullhorn-outline"
        @click="ouvrirDialogue"
      >
        Nouvelle annonce
      </v-btn>
    </div>

    <v-alert v-if="erreurChargement" type="error" variant="tonal">
      {{ erreurChargement }}
      <template #append>
        <v-btn variant="text" size="small" @click="chargerAnnonces">Réessayer</v-btn>
      </template>
    </v-alert>

    <v-progress-linear v-if="chargement" indeterminate color="primary" rounded />

    <div v-else-if="!erreurChargement && annonces.length === 0" class="annonces__vide">
      <v-icon icon="mdi-bullhorn-outline" size="40" />
      <p>Aucune annonce pour le moment.</p>
      <span v-if="peutPublier">Publiez la première annonce de l'espace.</span>
    </div>

    <div v-else class="annonces__liste">
      <v-card
        v-for="annonce in annonces"
        :key="annonce.id"
        variant="outlined"
        class="annonce"
        :class="{ 'annonce--nouvelle': estNouvelle(annonce) }"
      >
        <v-card-text class="annonce__corps">
          <div class="annonce__entete">
            <span
              class="annonce__avatar"
              :style="{ backgroundColor: couleurAuteur(annonce.auteur) }"
              aria-hidden="true"
            >
              {{ initialesAuteur(annonce.auteur) }}
            </span>

            <div class="annonce__auteur">
              <span class="annonce__nom">{{ nomAuteur(annonce.auteur, null) }}</span>
              <span class="annonce__date">{{ formaterDateAnnonce(annonce.cree_le) }}</span>
            </div>

            <v-chip v-if="estNouvelle(annonce)" size="small" color="primary" variant="tonal" label>
              Nouveau
            </v-chip>

            <v-chip
              v-if="annonce.notifier_email"
              size="small"
              color="success"
              variant="tonal"
              label
              prepend-icon="mdi-email-check-outline"
            >
              Envoyée aussi par e-mail
            </v-chip>
          </div>

          <h2 class="annonce__titre">{{ annonce.titre }}</h2>
          <p class="annonce__contenu">{{ annonce.contenu }}</p>

          <div v-if="annonce.pieces_jointes?.length" class="annonce__pieces">
            <div v-for="piece in annonce.pieces_jointes" :key="piece.id" class="annonce__piece">
              <v-icon icon="mdi-file-document-outline" color="error" size="26" />
              <span class="annonce__piece-texte">
                <span class="annonce__piece-nom">{{ piece.nom }}</span>
                <span class="annonce__piece-meta">
                  {{ typeFichier(piece.nom) }}
                  <template v-if="piece.taille_octets">
                    · {{ formaterTaille(piece.taille_octets) }}</template
                  >
                </span>
              </span>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </div>

    <v-dialog v-model="dialogueOuvert" max-width="600" @update:model-value="fermerDialogue">
      <v-card>
        <form @submit.prevent="publier">
          <v-card-title class="text-h6">Nouvelle annonce</v-card-title>

          <v-card-text class="annonce-formulaire">
            <v-alert v-if="erreurEnvoi" type="error" variant="tonal" density="compact">
              {{ erreurEnvoi }}
            </v-alert>

            <v-text-field
              v-model="formulaire.titre"
              label="Titre"
              maxlength="160"
              counter
              variant="outlined"
              autofocus
            />

            <v-textarea
              v-model="formulaire.contenu"
              label="Contenu"
              rows="4"
              maxlength="10000"
              counter
              variant="outlined"
            />

            <v-file-input
              v-model="formulaire.fichiers"
              label="Pièces jointes"
              multiple
              chips
              show-size
              clearable
              prepend-icon="mdi-paperclip"
              :rules="[regleFichiers]"
              hint="5 fichiers maximum, 25 Mo par fichier."
              persistent-hint
            />

            <v-checkbox
              v-model="formulaire.notifier_email"
              label="Notifier aussi les membres par e-mail"
              hide-details
            />
          </v-card-text>

          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" :disabled="envoiEnCours" @click="fermerDialogue(false)">
              Annuler
            </v-btn>
            <v-btn
              type="submit"
              color="primary"
              variant="flat"
              :loading="envoiEnCours"
              :disabled="!brouillonValide"
            >
              Publier
            </v-btn>
          </v-card-actions>
        </form>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.actif" :timeout="4000" location="bottom">
      {{ snackbar.texte }}
    </v-snackbar>
  </section>
</template>

<style scoped>
.annonces {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.annonces__entete {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

.annonces__intro {
  flex: 1;
  min-width: 240px;
  margin: 0;
  font-size: 13px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.annonces__liste {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.annonces__vide {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 48px 20px;
  border: 1px dashed rgb(var(--v-theme-outline));
  border-radius: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
  text-align: center;
}

.annonces__vide p {
  margin: 0;
  font-size: 16px;
  font-weight: 500;
  color: rgb(var(--v-theme-on-surface));
}

.annonces__vide span {
  font-size: 13px;
}

.annonce {
  border-radius: 12px;
  border-left: 4px solid transparent;
}

.annonce--nouvelle {
  border-left-color: rgb(var(--v-theme-primary));
}

.annonce__corps {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.annonce__entete {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.annonce__avatar {
  display: grid;
  place-items: center;
  flex: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  color: #fff;
  font-size: 13px;
  font-weight: 500;
}

.annonce__auteur {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
}

.annonce__nom {
  font-weight: 500;
}

.annonce__date {
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.annonce__titre {
  margin: 0;
  font-size: 18px;
  font-weight: 500;
  overflow-wrap: anywhere;
}

.annonce__contenu {
  margin: 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.annonce__pieces {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 4px;
}

.annonce__piece {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border: 1px solid rgb(var(--v-theme-outline));
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
}

.annonce__piece-texte {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.annonce__piece-nom {
  font-weight: 500;
  overflow-wrap: anywhere;
}

.annonce__piece-meta {
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.annonce-formulaire {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
</style>
