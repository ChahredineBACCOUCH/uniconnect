<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'

import { rechercherUtilisateurs } from '@/api/espaces'
import { couleurAuteur, initialesAuteur, libelleProfil, messageErreur } from '@/utils/espaces'

/*
 * SCRUM-89 / SCRUM-91 : choix de personnes à inviter (GET /api/utilisateurs?q=).
 * v-model = liste des personnes choisies ({ id, nom, prenom, profil, classe }).
 * `exclure` : identifiants déjà membres, retirés des résultats.
 */
const personnes = defineModel({ type: Array, default: () => [] })

const props = defineProps({
  exclure: { type: Array, default: () => [] },
  label: { type: String, default: 'Rechercher par nom ou prénom' },
})

const recherche = ref('')
const resultats = ref([])
const chargement = ref(false)
const erreur = ref('')

let minuteur = null
let derniereRequete = 0

watch(recherche, (texte) => {
  clearTimeout(minuteur)
  erreur.value = ''

  const q = (texte ?? '').trim()
  if (q.length < 2) {
    resultats.value = []
    return
  }

  // On attend la fin de la frappe (300 ms) pour ne pas appeler l'API à chaque lettre.
  minuteur = setTimeout(() => chercher(q), 300)
})

onBeforeUnmount(() => clearTimeout(minuteur))

async function chercher(q) {
  const numero = ++derniereRequete
  chargement.value = true

  try {
    const trouves = await rechercherUtilisateurs(q)
    // Une réponse plus ancienne ne doit pas écraser la plus récente.
    if (numero !== derniereRequete) return
    resultats.value = trouves.filter((p) => !props.exclure.includes(p.id))
  } catch (error) {
    if (numero !== derniereRequete) return
    resultats.value = []
    erreur.value = messageErreur(error, 'La recherche a échoué.')
  } finally {
    if (numero === derniereRequete) chargement.value = false
  }
}

function nomComplet(personne) {
  return `${personne.prenom} ${personne.nom}`
}

function retirer(personne) {
  personnes.value = personnes.value.filter((p) => p.id !== personne.id)
}
</script>

<template>
  <v-autocomplete
    v-model="personnes"
    v-model:search="recherche"
    :items="[...personnes, ...resultats.filter((r) => !personnes.some((p) => p.id === r.id))]"
    :item-title="nomComplet"
    item-value="id"
    :label="label"
    :loading="chargement"
    :error-messages="erreur"
    prepend-inner-icon="mdi-magnify"
    multiple
    chips
    closable-chips
    return-object
    clear-on-select
    no-filter
    hide-selected
    :no-data-text="
      (recherche ?? '').trim().length < 2
        ? 'Saisissez au moins 2 caractères'
        : chargement
          ? 'Recherche…'
          : 'Aucune personne trouvée'
    "
  >
    <template #chip="{ props: chipProps, item }">
      <v-chip v-bind="chipProps" @click:close="retirer(item.raw)">
        <template #prepend>
          <v-avatar :color="couleurAuteur(item.raw)" size="22" class="mr-1">
            <span class="text-caption text-white">{{ initialesAuteur(item.raw) }}</span>
          </v-avatar>
        </template>
        {{ nomComplet(item.raw) }}
      </v-chip>
    </template>

    <template #item="{ props: itemProps, item }">
      <v-list-item
        v-bind="itemProps"
        :title="nomComplet(item.raw)"
        :subtitle="[libelleProfil(item.raw.profil), item.raw.classe].filter(Boolean).join(' · ')"
      >
        <template #prepend>
          <v-avatar :color="couleurAuteur(item.raw)" size="32">
            <span class="text-caption text-white">{{ initialesAuteur(item.raw) }}</span>
          </v-avatar>
        </template>
      </v-list-item>
    </template>
  </v-autocomplete>
</template>
