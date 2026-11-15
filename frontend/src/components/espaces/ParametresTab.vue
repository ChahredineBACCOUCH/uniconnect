<script setup>
import { computed } from 'vue'

import { useAuthStore } from '@/stores/auth'
import { estGestionnaire } from '@/utils/espaces'

import ReglageAssistantIA from './ReglageAssistantIA.vue'

const props = defineProps({
  espace: { type: Object, required: true },
})
const emit = defineEmits(['actualiser'])

const auth = useAuthStore()

// SCRUM-147 : même règle que l'API (SCRUM-145) : enseignant propriétaire ou co-gestionnaire.
const peutReglerAssistant = computed(
  () =>
    auth.user?.profil === 'ENSEIGNANT' &&
    estGestionnaire(props.espace.role) &&
    props.espace.etat !== 'ARCHIVE',
)
</script>

<template>
  <div class="parametres">
    <ReglageAssistantIA
      v-if="peutReglerAssistant"
      :espace="espace"
      @actualiser="emit('actualiser')"
    />
  </div>
</template>

<style scoped>
.parametres {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
</style>
