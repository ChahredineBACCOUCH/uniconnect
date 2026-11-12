<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { recupererEspace } from '@/api/espaces'
import {
  iconeEspace,
  libelleRole,
  libelleType,
  libelleVisibilite,
  messageErreur,
} from '@/utils/espaces'

import AnnoncesTab from '@/components/espaces/AnnoncesTab.vue'
import AssistantIATab from '@/components/espaces/AssistantIATab.vue'
import DiscussionTab from '@/components/espaces/DiscussionTab.vue'
import EpinglesTab from '@/components/espaces/EpinglesTab.vue'
import FichiersTab from '@/components/espaces/FichiersTab.vue'
import MembresTab from '@/components/espaces/MembresTab.vue'
import ParametresTab from '@/components/espaces/ParametresTab.vue'
import RessourcesTab from '@/components/espaces/RessourcesTab.vue'

const route = useRoute()
const router = useRouter()

/*
 * Chaque onglet est un composant autonome. Seul l'onglet actif est monté :
 * le polling de la discussion s'arrête donc quand on change d'onglet.
 */
const ONGLETS = [
  { valeur: 'discussion', libelle: 'Discussion', composant: DiscussionTab },
  { valeur: 'annonces', libelle: 'Annonces', composant: AnnoncesTab },
  { valeur: 'ressources', libelle: 'Ressources', composant: RessourcesTab },
  { valeur: 'fichiers', libelle: 'Fichiers', composant: FichiersTab },
  { valeur: 'epingles', libelle: 'Épinglés', composant: EpinglesTab },
  { valeur: 'membres', libelle: 'Membres', composant: MembresTab },
  {
    valeur: 'assistant',
    libelle: 'Assistant IA',
    composant: AssistantIATab,
    icone: 'mdi-creation-outline',
  },
  { valeur: 'parametres', libelle: 'Paramètres', composant: ParametresTab },
]

function ongletValide(valeur) {
  return ONGLETS.some((onglet) => onglet.valeur === valeur) ? valeur : 'discussion'
}

const espace = ref(null)
const chargement = ref(true)
const erreur = ref(null)
const onglet = ref(ongletValide(route.params.onglet))

/*
 * L'onglet Assistant IA n'apparaît que si l'assistant est activé dans l'espace (SCRUM-142).
 * En développement, VITE_IA_SIMULATION=true l'affiche toujours (réponses simulées).
 */
const SIMULATION_IA = import.meta.env.DEV && import.meta.env.VITE_IA_SIMULATION === 'true'
const assistantVisible = computed(() => SIMULATION_IA || espace.value?.assistant?.actif === true)
const ongletsVisibles = computed(() =>
  ONGLETS.filter((element) => element.valeur !== 'assistant' || assistantVisible.value),
)

watch([espace, onglet], () => {
  if (espace.value && onglet.value === 'assistant' && !assistantVisible.value) {
    onglet.value = 'discussion'
  }
})

const ongletActif = computed(
  () => ONGLETS.find((element) => element.valeur === onglet.value) ?? ONGLETS[0],
)
const composantActif = computed(() => ongletActif.value.composant)

watch(() => route.params.id, chargerEspace, { immediate: true })

watch(
  () => route.params.onglet,
  (valeur) => {
    onglet.value = ongletValide(valeur)
  },
)

watch(onglet, (valeur) => {
  if (route.params.onglet === valeur) return

  router.replace({
    name: 'espace',
    params: { id: route.params.id, onglet: valeur },
    query: route.query,
  })
})

async function chargerEspace() {
  chargement.value = true
  erreur.value = null
  espace.value = null

  try {
    espace.value = await recupererEspace(route.params.id)
  } catch (error) {
    const statut = error?.response?.status

    if (statut === 403) {
      erreur.value = "Vous n'êtes pas membre de cet espace."
    } else if (statut === 404) {
      erreur.value = "Cet espace n'existe pas ou n'est plus disponible."
    } else {
      erreur.value = messageErreur(error, "L'espace n'a pas pu être chargé.")
    }
  } finally {
    chargement.value = false
  }
}

/*
 * Rechargement discret de l'en-tête (rôle, nombre de membres) demandé par un
 * onglet après une action : l'onglet reste affiché, sans squelette de chargement.
 */
async function rafraichirEspace() {
  try {
    espace.value = await recupererEspace(route.params.id)
  } catch {
    // L'utilisateur n'a peut-être plus accès (ex. il vient de quitter) : rechargement complet.
    await chargerEspace()
  }
}

function changerOnglet(valeur) {
  onglet.value = ongletValide(valeur)
}
</script>

<template>
  <div class="espace">
    <v-skeleton-loader v-if="chargement" type="heading, text, article" class="espace__squelette" />

    <v-alert v-else-if="erreur" type="error" variant="tonal">
      {{ erreur }}
      <template #append>
        <div class="espace__erreur-actions">
          <v-btn variant="text" size="small" @click="chargerEspace">Réessayer</v-btn>
          <v-btn variant="text" size="small" to="/">Retour à l'accueil</v-btn>
        </div>
      </template>
    </v-alert>

    <template v-else-if="espace">
      <!-- SCRUM-89 : l'espace a été créé mais l'envoi des invitations a échoué. -->
      <v-alert
        v-if="route.query.avertissement"
        type="warning"
        variant="tonal"
        density="compact"
        closable
      >
        {{ route.query.avertissement }}
      </v-alert>

      <header class="espace__entete">
        <span class="espace__icone">
          <v-icon :icon="iconeEspace(espace.type)" size="28" />
        </span>

        <div class="espace__titre">
          <h1 class="text-h5 font-weight-medium">{{ espace.nom }}</h1>

          <div class="espace__meta">
            <v-chip size="small" color="primary" variant="tonal" label>
              {{ libelleType(espace.type) }}
            </v-chip>

            <v-chip
              size="small"
              color="warning"
              variant="tonal"
              label
              prepend-icon="mdi-timer-outline"
            >
              {{ libelleVisibilite(espace.duree_visibilite_heures) }}
            </v-chip>

            <v-chip v-if="espace.mode_annonce" size="small" color="secondary" variant="tonal" label>
              Mode annonce
            </v-chip>

            <v-chip v-if="espace.etat === 'ARCHIVE'" size="small" variant="tonal" label>
              Espace archivé
            </v-chip>

            <span class="espace__membres">
              {{ espace.nb_membres }} membre{{ espace.nb_membres > 1 ? 's' : '' }} ·
              {{ libelleRole(espace.role) }}
            </span>
          </div>
        </div>

        <v-btn
          icon
          variant="text"
          color="on-surface-variant"
          aria-label="Paramètres de l'espace"
          @click="changerOnglet('parametres')"
        >
          <v-icon icon="mdi-cog-outline" size="22" />
        </v-btn>
      </header>

      <v-tabs
        :model-value="onglet"
        color="primary"
        class="espace__onglets"
        show-arrows
        @update:model-value="changerOnglet"
      >
        <v-tab
          v-for="element in ongletsVisibles"
          :key="element.valeur"
          :value="element.valeur"
          :prepend-icon="element.icone"
          class="text-none"
        >
          {{ element.libelle }}
        </v-tab>
      </v-tabs>

      <section class="espace__contenu" :aria-label="`Onglet ${ongletActif.libelle}`">
        <component
          :is="composantActif"
          :key="`${espace.id}-${onglet}`"
          :espace="espace"
          @actualiser="rafraichirEspace"
        />
      </section>
    </template>
  </div>
</template>

<style scoped>
.espace {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.espace__squelette {
  border-radius: 12px;
}

.espace__entete {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.espace__icone {
  display: grid;
  place-items: center;
  flex: none;
  width: 52px;
  height: 52px;
  border-radius: 12px;
  background: rgb(var(--v-theme-primary-soft));
  color: rgb(var(--v-theme-primary));
}

.espace__titre {
  flex: 1;
  min-width: 0;
}

.espace__titre h1 {
  margin: 0;
  overflow-wrap: anywhere;
}

.espace__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}

.espace__membres {
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.espace__onglets {
  border-bottom: 1px solid rgb(var(--v-theme-outline));
}

.espace__contenu {
  min-height: 0;
}

.espace__erreur-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
</style>
