<script setup>
import { RUBRIQUES, useEspacesStore } from '@/stores/espaces'

/*
 * SCRUM-86 : espaces de l'utilisateur dans le menu latéral, en 3 rubriques
 * (classes et matières, groupes privés, channels), avec le nombre de non-lus.
 */
const espaces = useEspacesStore()
</script>

<template>
  <div class="espace-list">
    <v-skeleton-loader
      v-if="espaces.chargement && !espaces.charge"
      type="list-item, list-item, list-item"
      class="espace-list__squelette"
    />

    <div v-else-if="espaces.erreur && !espaces.charge" class="espace-list__message">
      <p>{{ espaces.erreur }}</p>
      <v-btn variant="text" size="small" color="primary" @click="espaces.charger()">
        Réessayer
      </v-btn>
    </div>

    <p v-else-if="espaces.estVide" class="espace-list__message">Vous n'avez encore aucun espace.</p>

    <template v-for="rubrique in RUBRIQUES" v-else :key="rubrique.cle">
      <template v-if="espaces.liste[rubrique.cle]?.length">
        <v-list-subheader class="espace-list__titre">{{ rubrique.titre }}</v-list-subheader>

        <v-list-item
          v-for="espace in espaces.liste[rubrique.cle]"
          :key="espace.id"
          :to="`/espaces/${espace.id}`"
          :prepend-icon="espace.icone"
          :title="espace.nom"
        >
          <template v-if="espace.non_lus" #append>
            <span
              class="espace-list__badge"
              :aria-label="`${espace.non_lus} message${espace.non_lus > 1 ? 's' : ''} non lu${espace.non_lus > 1 ? 's' : ''}`"
            >
              {{ espace.non_lus > 99 ? '99+' : espace.non_lus }}
            </span>
          </template>
        </v-list-item>
      </template>
    </template>
  </div>
</template>

<style scoped>
.espace-list__titre {
  padding-inline: 12px;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.125em;
  text-transform: uppercase;
  color: rgb(var(--v-theme-on-surface-variant));
}

.espace-list__squelette {
  background: transparent;
}

.espace-list__message {
  margin: 12px 16px;
  font-size: 13px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.espace-list__badge {
  display: inline-grid;
  place-items: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgb(var(--v-theme-primary));
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}
</style>
