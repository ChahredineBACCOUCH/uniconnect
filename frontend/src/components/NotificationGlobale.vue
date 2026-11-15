<script setup>
import { computed } from 'vue'

import { useNotify } from '@/composables/useNotify'

/* SCRUM-184 : le snackbar unique de l'application, piloté par useNotify(). */
const notify = useNotify()

const COULEURS = { succes: 'success', info: 'info', erreur: 'error' }
const ICONES = {
  succes: 'mdi-check-circle-outline',
  info: 'mdi-information-outline',
  erreur: 'mdi-alert-circle-outline',
}

const couleur = computed(() => COULEURS[notify.etat.value.type])
const icone = computed(() => ICONES[notify.etat.value.type])

function changerVisibilite(visible) {
  if (!visible) notify.fermer()
}
</script>

<template>
  <v-snackbar
    :model-value="notify.etat.value.visible"
    :color="couleur"
    :timeout="notify.etat.value.duree"
    location="bottom"
    multi-line
    :role="notify.etat.value.type === 'erreur' ? 'alert' : 'status'"
    @update:model-value="changerVisibilite"
  >
    <div class="d-flex align-center ga-3">
      <v-icon :icon="icone" />
      <span>{{ notify.etat.value.texte }}</span>
    </div>

    <template #actions>
      <v-btn v-if="notify.etat.value.actionLabel" variant="text" @click="notify.agir">
        {{ notify.etat.value.actionLabel }}
      </v-btn>
      <v-btn
        icon="mdi-close"
        variant="text"
        size="small"
        aria-label="Fermer le message"
        @click="notify.fermer"
      />
    </template>
  </v-snackbar>
</template>
