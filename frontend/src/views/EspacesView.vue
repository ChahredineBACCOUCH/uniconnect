<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

import EmptyState from '@/components/EmptyState.vue'
import LoadingSkeleton from '@/components/LoadingSkeleton.vue'
import { useAuthStore } from '@/stores/auth'
import { RUBRIQUES, useEspacesStore } from '@/stores/espaces'

/*
 * « Mes espaces » : tous les espaces de l'utilisateur, par rubrique. C'est l'entrée
 * « Espaces » de la barre de navigation mobile, où le menu latéral est replié.
 */
const espaces = useEspacesStore()
const auth = useAuthStore()
const router = useRouter()

const peutCreer = computed(() => auth.hasDroit('CREER_GROUPE_PRIVE'))
const chargementInitial = computed(() => !espaces.charge && !espaces.erreur)
const rubriques = computed(() =>
  RUBRIQUES.map((rubrique) => ({ ...rubrique, espaces: espaces.liste[rubrique.cle] ?? [] })).filter(
    (rubrique) => rubrique.espaces.length > 0,
  ),
)

onMounted(() => {
  if (!espaces.charge && !espaces.chargement) espaces.charger()
})
</script>

<template>
  <div class="espaces">
    <header class="espaces__entete">
      <div>
        <h1 class="text-h5 font-weight-medium">Mes espaces</h1>
        <p class="espaces__sous-titre">Vos classes, matières, groupes privés et channels.</p>
      </div>
      <v-btn
        v-if="peutCreer"
        color="primary"
        variant="flat"
        prepend-icon="mdi-plus"
        :to="{ name: 'espace-nouveau' }"
      >
        Créer un espace
      </v-btn>
    </header>

    <v-alert v-if="espaces.erreur" type="error" variant="tonal" density="compact">
      {{ espaces.erreur }}
      <template #append>
        <v-btn variant="text" size="small" @click="espaces.charger()">Réessayer</v-btn>
      </template>
    </v-alert>

    <v-card v-if="chargementInitial" variant="outlined">
      <v-card-text><LoadingSkeleton type="liste" :lignes="3" /></v-card-text>
    </v-card>

    <v-card v-else-if="espaces.charge && espaces.estVide" variant="outlined">
      <EmptyState
        icone="mdi-account-group-outline"
        titre="Vous n'avez encore aucun espace."
        :texte="
          peutCreer
            ? 'Créez un groupe privé ou attendez d’être ajouté au groupe de votre classe.'
            : 'Vous serez ajouté automatiquement au groupe de votre classe.'
        "
        :action-label="peutCreer ? 'Créer un espace' : ''"
        @action="router.push({ name: 'espace-nouveau' })"
      />
    </v-card>

    <v-card v-for="rubrique in rubriques" v-else :key="rubrique.cle" variant="outlined">
      <v-card-title class="text-subtitle-1 font-weight-medium">{{ rubrique.titre }}</v-card-title>
      <v-list density="comfortable" class="pa-0" role="navigation" :aria-label="rubrique.titre">
        <v-list-item
          v-for="espace in rubrique.espaces"
          :key="espace.id"
          :to="`/espaces/${espace.id}`"
          :prepend-icon="espace.icone"
          :title="espace.nom"
        >
          <template v-if="espace.non_lus" #append>
            <v-chip size="small" color="primary" variant="flat">
              {{ espace.non_lus }} non lu{{ espace.non_lus > 1 ? 's' : '' }}
            </v-chip>
          </template>
        </v-list-item>
      </v-list>
    </v-card>
  </div>
</template>

<style scoped>
.espaces {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.espaces__entete {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.espaces__entete h1 {
  margin: 0;
}

.espaces__sous-titre {
  margin: 4px 0 0;
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 14px;
}
</style>
