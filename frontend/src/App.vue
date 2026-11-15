<script setup>
import { onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'

import ChargementApplication from '@/components/ChargementApplication.vue'
import NotificationGlobale from '@/components/NotificationGlobale.vue'

/*
 * SCRUM-184 : aucun écran blanc.
 * - Premier chargement (vérification de la session GET /me) : écran de chargement.
 * - Changement de page (code de la page chargé à la demande) : barre de progression en haut.
 * - Messages de retour (succès, erreurs réseau) : un seul snackbar, piloté par useNotify().
 */
const router = useRouter()
const pret = ref(false)
const navigation = ref(false)

router.isReady().finally(() => {
  pret.value = true
})

const retirerAvant = router.beforeEach(() => {
  navigation.value = true
})
const retirerApres = router.afterEach(() => {
  navigation.value = false
})
const retirerErreur = router.onError(() => {
  navigation.value = false
})

onBeforeUnmount(() => {
  retirerAvant()
  retirerApres()
  retirerErreur()
})
</script>

<template>
  <v-progress-linear
    v-if="navigation && pret"
    indeterminate
    color="primary"
    height="3"
    class="barre-navigation"
    aria-label="Chargement de la page"
  />

  <router-view v-if="pret" />
  <ChargementApplication v-else />

  <NotificationGlobale />
</template>

<style scoped>
.barre-navigation {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 2000;
}
</style>
