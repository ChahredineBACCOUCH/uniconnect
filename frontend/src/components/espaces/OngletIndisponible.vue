<script setup>
defineProps({
  titre: { type: String, required: true },
  description: { type: String, required: true },
  icone: { type: String, default: 'mdi-progress-wrench' },
  ticket: { type: String, default: '' },
})
</script>

<template>
  <v-card variant="outlined" class="onglet">
    <v-card-text class="onglet__contenu">
      <span class="onglet__icone">
        <v-icon :icon="icone" size="32" />
      </span>
      <p class="onglet__titre">{{ titre }}</p>
      <p class="onglet__description">{{ description }}</p>
      <v-chip v-if="ticket" size="small" variant="tonal" color="primary">Ticket {{ ticket }}</v-chip>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.onglet {
  border-radius: 12px;
}

.onglet__contenu {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 40px 20px;
  text-align: center;
}

.onglet__icone {
  display: grid;
  place-items: center;
  width: 60px;
  height: 60px;
  border-radius: 16px;
  background: rgb(var(--v-theme-primary-soft));
  color: rgb(var(--v-theme-primary));
}

.onglet__titre {
  margin: 4px 0 0;
  font-size: 16px;
  font-weight: 500;
}

.onglet__description {
  max-width: 520px;
  margin: 0;
  color: rgb(var(--v-theme-on-surface-variant));
}
</style>
