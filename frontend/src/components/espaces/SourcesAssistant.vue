<script setup>
import { computed, onMounted, ref } from 'vue'

import { recupererChapitres } from '@/api/ressources'
import { messageErreur } from '@/utils/espaces'

/*
 * SCRUM-143 (étape 5) : panneau « Sources de l'assistant » à côté de la conversation
 * (desktop). Liste les documents de l'espace indexés pour l'IA, c'est-à-dire ceux
 * dans lesquels l'assistant peut chercher ses réponses. Un clic ouvre la ressource
 * dans l'onglet Ressources, mise en évidence (?ressource=<id>).
 */
const props = defineProps({
  espaceId: { type: [Number, String], required: true },
})

const chapitres = ref([])
const chargement = ref(true)
const erreur = ref('')

// Une entrée par ressource indexée, avec le titre de son chapitre.
const documents = computed(() =>
  chapitres.value.flatMap((chapitre) =>
    (chapitre.ressources ?? [])
      .filter((ressource) => ressource.indexee_ia)
      .map((ressource) => ({ ...ressource, chapitre: chapitre.titre })),
  ),
)

onMounted(charger)

async function charger() {
  chargement.value = true
  erreur.value = ''
  try {
    chapitres.value = (await recupererChapitres(props.espaceId)) ?? []
  } catch (error) {
    erreur.value = messageErreur(error, 'Les sources n’ont pas pu être chargées.')
  } finally {
    chargement.value = false
  }
}
</script>

<template>
  <aside class="sources-assistant" aria-labelledby="titre-sources-assistant">
    <h2 id="titre-sources-assistant" class="sources-assistant__titre">
      <v-icon icon="mdi-folder-search-outline" size="20" color="primary" />
      Sources de l'assistant
    </h2>
    <p class="sources-assistant__aide">Documents du cours dans lesquels l'assistant cherche.</p>

    <v-skeleton-loader v-if="chargement" type="list-item, list-item, list-item" />

    <v-alert v-else-if="erreur" type="error" variant="tonal" density="compact">
      {{ erreur }}
    </v-alert>

    <p v-else-if="!documents.length" class="sources-assistant__aide">
      Aucun document indexé pour l'instant.
    </p>

    <v-list v-else density="compact" class="pa-0">
      <v-list-item
        v-for="document in documents"
        :key="document.id"
        :to="{
          name: 'espace',
          params: { id: espaceId, onglet: 'ressources' },
          query: { ressource: document.id },
        }"
        prepend-icon="mdi-file-pdf-box"
        :title="document.titre"
        :subtitle="document.chapitre"
        rounded="lg"
      />
    </v-list>
  </aside>
</template>

<style scoped>
.sources-assistant {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  border: 1px solid rgb(var(--v-theme-outline));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  overflow-y: auto;
}

.sources-assistant__titre {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 16px;
  font-weight: 500;
}

.sources-assistant__aide {
  margin: 0;
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}
</style>
