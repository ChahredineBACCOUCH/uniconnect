<script setup>
import ErrorPage from '@/components/ErrorPage.vue'
</script>

<template>
  <ErrorPage
    code="Erreur 404 · Page introuvable"
    title="Cette page n'existe pas"
    text="Le lien est peut-être incorrect, ou le contenu a été supprimé."
    hint="Utilisez le menu pour retrouver vos espaces."
    icon="mdi-file-search-outline"
  />
</template>
