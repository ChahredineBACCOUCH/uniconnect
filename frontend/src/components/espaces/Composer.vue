<script setup>
import { computed, nextTick, ref, watch } from 'vue'

import {
  ACCEPT_FICHIERS,
  MAX_FICHIERS,
  TAILLE_MAX_MO,
  verifierFichiers,
} from '@/utils/fichiers'
import {
  estGestionnaire,
  extrait,
  libelleDuree,
  messageErreur,
  nomAuteur,
  optionsDuree,
  peutPublier,
} from '@/utils/espaces'
import { formaterTaille } from '@/utils/ressources'

const props = defineProps({
  espace: { type: Object, required: true },
  reponseA: { type: Object, default: null },
  envoyer: { type: Function, required: true },
  utilisateurId: { type: Number, default: null },
})

const emit = defineEmits(['annuler-reponse'])

const brouillon = ref('')
const duree = ref(null)
const envoiEnCours = ref(false)
const erreur = ref(null)
const zoneTexte = ref(null)

// SCRUM-139 : fichiers choisis par le trombone ou déposés sur le composer.
const fichiers = ref([])
const champFichiers = ref(null)
const progres = ref(0)
const survol = ref(false)
const profondeurSurvol = ref(0)

const autorise = computed(() => peutPublier(props.espace))
const gestionnaire = computed(() => estGestionnaire(props.espace.role))
const optionsDureeDisponibles = computed(() => optionsDuree(props.espace))
const afficherDuree = computed(() => autorise.value && optionsDureeDisponibles.value.length > 1)
const aDesFichiers = computed(() => fichiers.value.length > 0)
const peutEnvoyer = computed(
  () => autorise.value && !envoiEnCours.value && (brouillon.value.trim() !== '' || aDesFichiers.value),
)

const blocage = computed(() => {
  if (props.espace.etat === 'ARCHIVE') {
    return "Cet espace est archivé : la discussion est en lecture seule."
  }

  if (props.espace.mode_annonce && !gestionnaire.value) {
    return 'Cet espace est en mode annonce : seuls les gestionnaires peuvent publier.'
  }

  return null
})

const placeholder = computed(() => `Écrire un message à ${props.espace.nom}…`)

const infoVisibilite = computed(() => {
  if (
    props.espace.duree_visibilite_heures === null ||
    props.espace.duree_visibilite_heures === undefined
  ) {
    return 'Les messages de cet espace ne sont pas supprimés automatiquement.'
  }

  return `Les messages de cet espace sont supprimés définitivement ${libelleDuree(props.espace.duree_visibilite_heures)} après leur envoi.`
})

const infoFichiers = `${MAX_FICHIERS} fichiers maximum, ${TAILLE_MAX_MO} Mo par fichier.`

watch(
  () => props.reponseA,
  async (message) => {
    if (!message) return
    await nextTick()
    zoneTexte.value?.focus()
  },
)

function ouvrirSelecteur() {
  if (!autorise.value || envoiEnCours.value) return
  champFichiers.value?.click()
}

function ajouterFichiers(liste) {
  const nouveaux = Array.from(liste ?? [])
  if (nouveaux.length === 0) return

  const { acceptes, erreur: message } = verifierFichiers(nouveaux, fichiers.value)

  erreur.value = message
  fichiers.value = [...fichiers.value, ...acceptes]
}

function surSelection(event) {
  ajouterFichiers(event.target.files)
  // Permet de rechoisir le même fichier après l'avoir retiré.
  event.target.value = ''
}

function surEntreeGlisser() {
  profondeurSurvol.value += 1
  survol.value = true
}

function surSortieGlisser() {
  profondeurSurvol.value = Math.max(0, profondeurSurvol.value - 1)
  if (profondeurSurvol.value === 0) survol.value = false
}

function surDepot(event) {
  profondeurSurvol.value = 0
  survol.value = false
  if (!autorise.value || envoiEnCours.value) return

  ajouterFichiers(event.dataTransfer?.files)
}

function retirerFichier(index) {
  fichiers.value = fichiers.value.filter((_, position) => position !== index)
}

function surProgression(event) {
  if (!event.total) return
  progres.value = Math.round((event.loaded * 100) / event.total)
}

async function soumettre() {
  const contenu = brouillon.value.trim()

  if (!peutEnvoyer.value) return

  erreur.value = null
  envoiEnCours.value = true
  progres.value = 0

  try {
    const payload = {
      contenu,
      parent_id: props.reponseA?.id ?? null,
      duree_visibilite_heures: duree.value || null,
    }

    if (aDesFichiers.value) {
      payload.fichiers = fichiers.value
      await props.envoyer(payload, { onUploadProgress: surProgression })
    } else {
      await props.envoyer(payload)
    }

    brouillon.value = ''
    duree.value = null
    fichiers.value = []
    progres.value = 0
    emit('annuler-reponse')
  } catch (error) {
    erreur.value = messageErreur(error, "Le message n'a pas pu être envoyé.")
  } finally {
    envoiEnCours.value = false
  }
}

function annulerReponse() {
  emit('annuler-reponse')
}
</script>

<template>
  <form
    class="composer"
    :class="{ 'composer--survol': survol }"
    @submit.prevent="soumettre"
    @dragenter.prevent="surEntreeGlisser"
    @dragover.prevent
    @dragleave.prevent="surSortieGlisser"
    @drop.prevent="surDepot"
  >
    <input
      ref="champFichiers"
      type="file"
      multiple
      hidden
      :accept="ACCEPT_FICHIERS"
      aria-label="Choisir des fichiers à joindre"
      @change="surSelection"
    />

    <div v-if="survol" class="composer__depot">
      <v-icon icon="mdi-cloud-upload-outline" size="28" />
      <span>Déposez vos fichiers ici</span>
    </div>

    <v-alert v-if="blocage" type="info" variant="tonal" density="comfortable" class="mb-3">
      {{ blocage }}
    </v-alert>

    <v-alert v-if="erreur" type="error" variant="tonal" density="comfortable" class="mb-3">
      {{ erreur }}
    </v-alert>

    <div v-if="reponseA" class="composer__reponse">
      <v-icon icon="mdi-reply" size="18" color="primary" />
      <div class="composer__reponse-texte">
        <span class="composer__reponse-auteur">
          Réponse à {{ nomAuteur(reponseA.auteur, utilisateurId) }}
        </span>
        <span class="composer__reponse-extrait">{{ extrait(reponseA.contenu) }}</span>
      </div>
      <v-btn
        icon
        variant="text"
        size="small"
        aria-label="Annuler la réponse"
        @click="annulerReponse"
      >
        <v-icon icon="mdi-close" size="18" />
      </v-btn>
    </div>

    <div v-if="aDesFichiers" class="composer__fichiers">
      <v-chip
        v-for="(fichier, index) in fichiers"
        :key="`${fichier.name}-${fichier.size}-${index}`"
        size="small"
        variant="tonal"
        closable
        :disabled="envoiEnCours"
        @click:close="retirerFichier(index)"
      >
        <v-icon start icon="mdi-paperclip" size="14" />
        {{ fichier.name }} · {{ formaterTaille(fichier.size) }}
      </v-chip>
    </div>

    <div v-if="envoiEnCours && aDesFichiers" class="composer__progression">
      <v-progress-linear v-model="progres" :height="6" color="primary" rounded />
      <span class="composer__progression-texte">Téléversement : {{ progres }} %</span>
    </div>

    <div class="composer__ligne">
      <v-btn
        icon="mdi-paperclip"
        variant="text"
        size="small"
        height="44"
        class="composer__joindre"
        aria-label="Joindre des fichiers"
        :disabled="!autorise || envoiEnCours"
        @click="ouvrirSelecteur"
      />

      <v-textarea
        ref="zoneTexte"
        v-model="brouillon"
        class="composer__zone"
        :placeholder="placeholder"
        :disabled="!autorise"
        variant="solo-filled"
        density="comfortable"
        rows="1"
        max-rows="6"
        auto-grow
        maxlength="10000"
        hide-details
        flat
        rounded="lg"
        aria-label="Votre message"
        @keydown.enter.exact.prevent="soumettre"
      />

      <v-select
        v-if="afficherDuree"
        v-model="duree"
        class="composer__duree"
        :items="optionsDureeDisponibles"
        item-title="title"
        item-value="value"
        variant="outlined"
        density="compact"
        hide-details
        prepend-inner-icon="mdi-timer-outline"
        aria-label="Durée de visibilité du message"
      />

      <v-btn
        type="submit"
        color="primary"
        variant="flat"
        height="44"
        class="composer__envoyer"
        :loading="envoiEnCours"
        :disabled="!peutEnvoyer"
        prepend-icon="mdi-send"
      >
        Envoyer
      </v-btn>
    </div>

    <span class="composer__info">
      {{ infoVisibilite }} {{ infoFichiers }}
    </span>
  </form>
</template>

<style scoped>
.composer {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 14px 16px 12px;
  border-top: 1px solid rgb(var(--v-theme-outline));
  background: rgb(var(--v-theme-surface));
}

.composer--survol {
  outline: 2px dashed rgb(var(--v-theme-primary));
  outline-offset: -6px;
}

.composer__depot {
  position: absolute;
  inset: 6px;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  border-radius: 10px;
  background: rgba(var(--v-theme-surface), 0.92);
  color: rgb(var(--v-theme-primary));
  font-weight: 500;
  pointer-events: none;
}

.composer__reponse {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  padding: 8px 10px;
  border-left: 3px solid rgb(var(--v-theme-primary));
  border-radius: 6px;
  background: rgb(var(--v-theme-surface-variant));
}

.composer__reponse-texte {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: 2px;
}

.composer__reponse-auteur {
  font-size: 12px;
  font-weight: 500;
  color: rgb(var(--v-theme-primary));
}

.composer__reponse-extrait {
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.composer__fichiers {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 10px;
}

.composer__progression {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 10px;
}

.composer__progression-texte {
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.composer__ligne {
  display: flex;
  align-items: flex-end;
  gap: 10px;
}

.composer__joindre {
  flex: none;
}

.composer__zone {
  flex: 1;
  min-width: 0;
}

.composer__zone :deep(.v-field) {
  border-radius: 12px;
}

.composer__duree {
  flex: none;
  width: 190px;
}

.composer__envoyer {
  flex: none;
  text-transform: none;
}

.composer__info {
  margin-top: 8px;
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

@media (max-width: 760px) {
  .composer__ligne {
    flex-wrap: wrap;
  }

  .composer__zone {
    order: 1;
    flex-basis: 100%;
  }

  .composer__joindre {
    order: 2;
  }

  .composer__duree {
    order: 3;
    flex: 1;
    width: auto;
  }

  .composer__envoyer {
    order: 4;
    margin-left: auto;
  }
}
</style>
