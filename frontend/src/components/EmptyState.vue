<script setup>
/*
 * SCRUM-184 : état vide réutilisable (liste vide, aucun résultat…), avec une
 * action suivante facultative.
 *
 *   <EmptyState icone="mdi-bullhorn-outline" titre="Aucune annonce pour le moment."
 *               texte="Publiez la première annonce." action-label="Nouvelle annonce" @action="ouvrir" />
 */
defineProps({
  icone: { type: String, default: 'mdi-inbox-outline' },
  titre: { type: String, required: true },
  texte: { type: String, default: '' },
  actionLabel: { type: String, default: '' },
  // Version compacte (dans une carte ou un panneau étroit).
  compact: { type: Boolean, default: false },
})

defineEmits(['action'])
</script>

<template>
  <div class="etat-vide" :class="{ 'etat-vide--compact': compact }" role="status">
    <span class="etat-vide__icone" aria-hidden="true">
      <v-icon :icon="icone" :size="compact ? 24 : 32" />
    </span>
    <p class="etat-vide__titre">{{ titre }}</p>
    <p v-if="texte" class="etat-vide__texte">{{ texte }}</p>
    <v-btn v-if="actionLabel" color="primary" variant="flat" class="mt-2" @click="$emit('action')">
      {{ actionLabel }}
    </v-btn>
    <slot />
  </div>
</template>

<style scoped>
.etat-vide {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 32px 16px;
  text-align: center;
  color: rgb(var(--v-theme-on-surface-variant));
}

.etat-vide--compact {
  padding: 16px 8px;
}

.etat-vide__icone {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: rgb(var(--v-theme-surface-variant));
  color: rgb(var(--v-theme-primary));
}

.etat-vide--compact .etat-vide__icone {
  width: 40px;
  height: 40px;
}

.etat-vide__titre {
  margin: 4px 0 0;
  font-weight: 500;
  color: rgb(var(--v-theme-on-surface));
}

.etat-vide__texte {
  margin: 0;
  max-width: 420px;
  font-size: 14px;
}
</style>
