<script setup>
import { computed, nextTick, ref, watch } from 'vue'

import {
  estGestionnaire,
  extrait,
  libelleDuree,
  messageErreur,
  nomAuteur,
  optionsDuree,
  peutPublier,
} from '@/utils/espaces'

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

const autorise = computed(() => peutPublier(props.espace))
const gestionnaire = computed(() => estGestionnaire(props.espace.role))
const optionsDureeDisponibles = computed(() => optionsDuree(props.espace))
const afficherDuree = computed(() => autorise.value && optionsDureeDisponibles.value.length > 1)

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

watch(
  () => props.reponseA,
  async (message) => {
    if (!message) return
    await nextTick()
    zoneTexte.value?.focus()
  },
)

async function soumettre() {
  const contenu = brouillon.value.trim()

  if (!contenu || !autorise.value || envoiEnCours.value) return

  erreur.value = null
  envoiEnCours.value = true

  try {
    await props.envoyer({
      contenu,
      parent_id: props.reponseA?.id ?? null,
      duree_visibilite_heures: duree.value || null,
    })

    brouillon.value = ''
    duree.value = null
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
  <form class="composer" @submit.prevent="soumettre">
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

    <div class="composer__ligne">
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
        :disabled="!autorise || !brouillon.trim()"
        prepend-icon="mdi-send"
      >
        Envoyer
      </v-btn>
    </div>

    <span class="composer__info">{{ infoVisibilite }}</span>
  </form>
</template>

<style scoped>
.composer {
  display: flex;
  flex-direction: column;
  padding: 14px 16px 12px;
  border-top: 1px solid rgb(var(--v-theme-outline));
  background: rgb(var(--v-theme-surface));
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

.composer__ligne {
  display: flex;
  align-items: flex-end;
  gap: 10px;
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
    flex-basis: 100%;
  }

  .composer__duree {
    flex: 1;
    width: auto;
  }
}
</style>
