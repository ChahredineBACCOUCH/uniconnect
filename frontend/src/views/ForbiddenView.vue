<script setup>
import ErrorPage from '@/components/ErrorPage.vue'
</script>

<template>
  <ErrorPage
    code="Erreur 403 · Accès refusé"
    title="Vous n'avez pas accès à cette page"
    text="Votre compte n'a pas le profil ou le droit nécessaire pour ouvrir cette page."
    hint="Si vous pensez qu'il s'agit d'une erreur, contactez la scolarité."
    icon="mdi-shield-lock-outline"
    tone="warning"
  />
</template>
