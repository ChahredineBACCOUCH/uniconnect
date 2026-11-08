<script setup>
/*
 * Page provisoire /espaces/:id/assistant : affiche l'onglet Assistant IA seul,
 * en attendant EspaceView.vue (en-tête de l'espace et onglets, SCRUM-95).
 * Quand EspaceView existe : y afficher <AssistantTab> pour l'onglet « assistant »,
 * puis supprimer ce fichier et sa route dans src/router/index.js.
 */
import { useRoute } from 'vue-router'

import AssistantTab from '@/components/espace/AssistantTab.vue'

const route = useRoute()
</script>

<template>
  <AssistantTab :espace-id="route.params.id" />
</template>
