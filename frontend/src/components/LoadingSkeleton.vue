<script setup>
import { computed } from 'vue'

/*
 * SCRUM-184 : squelette de chargement réutilisable, pour qu'aucune zone ne reste
 * blanche pendant un chargement.
 *
 *   <LoadingSkeleton type="liste" :lignes="3" />   liste d'éléments
 *   <LoadingSkeleton type="cartes" :lignes="2" />  cartes (annonces, accueil)
 *   <LoadingSkeleton type="tableau" />             tableau (administration)
 */
const props = defineProps({
  type: {
    type: String,
    default: 'liste',
    validator: (valeur) => ['liste', 'cartes', 'tableau'].includes(valeur),
  },
  lignes: { type: Number, default: 3 },
})

const TYPES = {
  liste: 'list-item-avatar-two-line',
  cartes: 'card',
  tableau: 'table-heading, table-tbody',
}

const typeVuetify = computed(() =>
  props.type === 'tableau' ? TYPES.tableau : Array(props.lignes).fill(TYPES[props.type]).join(', '),
)
</script>

<template>
  <v-skeleton-loader
    :type="typeVuetify"
    class="chargement"
    aria-busy="true"
    aria-label="Chargement en cours"
  />
</template>

<style scoped>
.chargement {
  background: transparent;
}
</style>
