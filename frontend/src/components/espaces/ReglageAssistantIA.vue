<script setup>
import { computed, ref } from 'vue'

import { configurerAssistant } from '@/api/espaces'
import { messageErreur } from '@/utils/espaces'

/*
 * SCRUM-147 : l'enseignant active l'Assistant IA de son espace et lui donne une consigne.
 * PATCH /espaces/{id}/assistant (SCRUM-145). Après l'enregistrement, l'écran de l'espace
 * est rechargé : l'onglet « Assistant IA » apparaît ou disparaît.
 */
const props = defineProps({
  espace: { type: Object, required: true },
})
const emit = defineEmits(['actualiser'])

const CONSIGNE_MAX = 1000

const initial = ref({
  actif: props.espace.assistant?.actif === true,
  instructions: props.espace.assistant?.instructions ?? '',
})
const actif = ref(initial.value.actif)
const instructions = ref(initial.value.instructions)
const nbDocuments = ref(props.espace.assistant?.nb_documents_indexes ?? 0)
const enregistrement = ref(false)
const erreur = ref('')
const snackbar = ref({ actif: false, texte: '' })

const consigneTropLongue = computed(() => instructions.value.length > CONSIGNE_MAX)
const modifie = computed(
  () =>
    actif.value !== initial.value.actif ||
    instructions.value.trim() !== (initial.value.instructions ?? '').trim(),
)
const peutEnregistrer = computed(
  () => modifie.value && !consigneTropLongue.value && !enregistrement.value,
)

async function enregistrer() {
  if (!peutEnregistrer.value) return

  enregistrement.value = true
  erreur.value = ''
  try {
    const reponse = await configurerAssistant(props.espace.id, {
      actif: actif.value,
      instructions: instructions.value.trim(),
    })
    initial.value = { actif: reponse.actif, instructions: reponse.instructions ?? '' }
    actif.value = reponse.actif
    instructions.value = reponse.instructions ?? ''
    nbDocuments.value = reponse.nb_documents_indexes ?? nbDocuments.value
    snackbar.value = {
      actif: true,
      texte: reponse.actif ? 'Assistant IA activé.' : 'Assistant IA désactivé.',
    }
    emit('actualiser')
  } catch (error) {
    // Le message 403 par défaut de Laravel est en anglais (« This action is unauthorized. »).
    erreur.value =
      error?.response?.status === 403
        ? "Seul un enseignant propriétaire ou co-gestionnaire peut régler l'assistant de cet espace."
        : messageErreur(error, "Le réglage de l'assistant n'a pas pu être enregistré.")
  } finally {
    enregistrement.value = false
  }
}
</script>

<template>
  <v-card variant="outlined" class="reglage">
    <v-card-item>
      <template #prepend>
        <span class="reglage__icone">
          <v-icon icon="mdi-creation-outline" size="22" />
        </span>
      </template>
      <v-card-title class="text-subtitle-1 font-weight-medium">Assistant IA</v-card-title>
      <v-card-subtitle class="reglage__sous-titre">
        Répond aux questions des étudiants à partir des supports de cours, en citant ses sources.
      </v-card-subtitle>
    </v-card-item>

    <v-card-text class="reglage__contenu">
      <v-switch
        v-model="actif"
        color="primary"
        inset
        hide-details
        :disabled="enregistrement"
        :label="actif ? 'Assistant activé pour les membres' : 'Assistant désactivé'"
      />

      <v-alert
        v-if="actif && nbDocuments === 0"
        type="info"
        variant="tonal"
        density="compact"
        icon="mdi-file-document-outline"
      >
        Aucun support de cours n'est encore indexé. Déposez des PDF dans l'onglet Ressources :
        l'assistant ne répondra qu'à partir de ces documents.
      </v-alert>
      <p v-else-if="nbDocuments > 0" class="reglage__documents">
        {{ nbDocuments }} document{{ nbDocuments > 1 ? 's' : '' }} indexé{{
          nbDocuments > 1 ? 's' : ''
        }}
      </p>

      <v-textarea
        v-model="instructions"
        label="Consigne pour l'assistant (facultatif)"
        placeholder="Ex. : Ne donne pas les corrigés des exercices, guide l'étudiant vers la méthode."
        variant="outlined"
        rows="3"
        auto-grow
        counter
        :maxlength="CONSIGNE_MAX + 100"
        :error-messages="
          consigneTropLongue ? 'La consigne ne doit pas dépasser 1 000 caractères.' : []
        "
        :disabled="enregistrement"
        persistent-hint
        hint="Ajoutée aux règles de l'assistant pour toutes les questions de cet espace."
      />

      <v-alert v-if="erreur" type="error" variant="tonal" density="compact">{{ erreur }}</v-alert>
    </v-card-text>

    <v-card-actions class="reglage__actions">
      <v-btn
        color="primary"
        variant="flat"
        :loading="enregistrement"
        :disabled="!peutEnregistrer"
        @click="enregistrer"
      >
        Enregistrer
      </v-btn>
    </v-card-actions>

    <v-snackbar v-model="snackbar.actif" :timeout="4000" location="bottom">
      {{ snackbar.texte }}
    </v-snackbar>
  </v-card>
</template>

<style scoped>
.reglage {
  border-radius: 12px;
}

.reglage__icone {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: rgb(var(--v-theme-primary-soft));
  color: rgb(var(--v-theme-primary));
}

.reglage__sous-titre {
  white-space: normal;
}

.reglage__contenu {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.reglage__documents {
  margin: 0;
  font-size: 13px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.reglage__actions {
  justify-content: flex-end;
  padding: 0 16px 16px;
}
</style>
