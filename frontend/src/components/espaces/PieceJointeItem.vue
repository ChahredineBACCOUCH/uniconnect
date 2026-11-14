<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import { apercuFichier, telechargerFichier } from '@/api/fichiers'
import { formaterTaille } from '@/utils/ressources'

/*
 * SCRUM-137 : ligne de pièce jointe avec téléchargement protégé.
 *
 * Une balise <img> ne peut pas envoyer le jeton Sanctum : l'aperçu est donc
 * chargé avec Axios (`responseType: 'blob'`) puis affiché via une URL créée par
 * `URL.createObjectURL`. Le téléchargement suit le même chemin.
 */
const props = defineProps({
  piece: { type: Object, required: true },
})

const emit = defineEmits(['erreur'])

const apercu = ref(null)
const chargementApercu = ref(false)
const telechargementEnCours = ref(false)

let urlApercu = null
let monte = true

const mime = computed(() => (props.piece.mime ?? '').toLowerCase())
const estImage = computed(() => mime.value.startsWith('image/'))
const taille = computed(() => formaterTaille(props.piece.taille_octets))
const type = computed(() => {
  const extension = props.piece.nom?.split('.').pop()
  return extension && extension !== props.piece.nom ? extension.toUpperCase() : 'Fichier'
})

function libererApercu() {
  if (urlApercu !== null) {
    URL.revokeObjectURL(urlApercu)
    urlApercu = null
  }
  apercu.value = null
}

async function chargerApercu() {
  libererApercu()
  if (!estImage.value) return

  chargementApercu.value = true

  try {
    const blob = await apercuFichier(props.piece.id)
    if (!monte) return

    urlApercu = URL.createObjectURL(blob)
    apercu.value = urlApercu
  } catch {
    if (monte) emit('erreur', "L'aperçu de l'image n'a pas pu être chargé.")
  } finally {
    if (monte) chargementApercu.value = false
  }
}

watch(() => props.piece.id, chargerApercu, { immediate: true })

onBeforeUnmount(() => {
  monte = false
  libererApercu()
})

async function telecharger() {
  if (telechargementEnCours.value) return

  telechargementEnCours.value = true

  try {
    const blob = await telechargerFichier(props.piece.id)
    const url = URL.createObjectURL(blob)
    const lien = document.createElement('a')

    lien.href = url
    lien.download = props.piece.nom || 'fichier'
    document.body.appendChild(lien)
    lien.click()
    lien.remove()
    URL.revokeObjectURL(url)
  } catch {
    emit('erreur', 'Le téléchargement a échoué.')
  } finally {
    telechargementEnCours.value = false
  }
}
</script>

<template>
  <div class="piece">
    <div class="piece__vignette">
      <v-progress-circular v-if="chargementApercu" indeterminate size="18" color="primary" />
      <a
        v-else-if="estImage && apercu"
        :href="apercu"
        target="_blank"
        rel="noopener"
        class="piece__lien-apercu"
        :aria-label="`Ouvrir l'aperçu de ${piece.nom}`"
      >
        <img :src="apercu" :alt="`Aperçu de ${piece.nom}`" class="piece__image" />
      </a>
      <v-icon
        v-else
        :icon="estImage ? 'mdi-image-outline' : 'mdi-file-document-outline'"
        :color="estImage ? 'primary' : 'error'"
        size="24"
      />
    </div>

    <div class="piece__texte">
      <span class="piece__nom">{{ piece.nom }}</span>
      <span class="piece__meta">
        {{ type }}
        <template v-if="taille !== '—'"> · {{ taille }}</template>
      </span>
    </div>

    <v-btn
      icon="mdi-download"
      variant="text"
      size="small"
      :loading="telechargementEnCours"
      :aria-label="`Télécharger ${piece.nom}`"
      class="piece__telecharger"
      @click="telecharger"
    />
  </div>
</template>

<style scoped>
.piece {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  padding: 8px 10px;
  border: 1px solid rgb(var(--v-theme-outline));
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
}

.piece__vignette {
  display: grid;
  place-items: center;
  flex: none;
  width: 48px;
  height: 40px;
  overflow: hidden;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.piece__lien-apercu {
  display: block;
  width: 100%;
  height: 100%;
}

.piece__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.piece__texte {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
}

.piece__nom {
  font-weight: 500;
  overflow-wrap: anywhere;
}

.piece__meta {
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.piece__telecharger {
  flex: none;
}
</style>
