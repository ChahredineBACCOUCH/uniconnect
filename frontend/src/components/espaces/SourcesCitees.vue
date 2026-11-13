<script setup>
/*
 * SCRUM-143 : sources citées sous une réponse de l'Assistant IA.
 * Une chip par source « [1] chap03.pdf · p. 12 » ; la chip `active` est mise en
 * évidence quand l'étudiant clique sur le marqueur [n] correspondant dans la réponse.
 *
 * Clic sur une chip → onglet Ressources avec `?ressource=<id>` pour mettre le fichier
 * en évidence. L'ouverture directe du PDF arrivera avec la route protégée
 * GET /fichiers/{id}/telecharger (SCRUM-137) et l'onglet Ressources (SCRUM-127).
 */
import { libelleSource } from './reponseIa'

defineProps({
  // [{ n, fichier, page, ressource_id }] (docs/api.md, section 7)
  sources: { type: Array, required: true },
  espaceId: { type: [Number, String], required: true },
  // Préfixe des id HTML des chips (une réponse = un préfixe) : `${prefixe}-${n}`.
  prefixe: { type: String, default: 'source' },
  active: { type: Number, default: null },
})

function icone(source) {
  return /\.pdf$/i.test(source.fichier ?? '') ? 'mdi-file-pdf-box' : 'mdi-file-document-outline'
}
</script>

<template>
  <div v-if="sources.length" class="sources-citees" role="list" aria-label="Sources citées">
    <v-chip
      v-for="source in sources"
      :id="`${prefixe}-${source.n}`"
      :key="source.n"
      role="listitem"
      :variant="active === source.n ? 'flat' : 'outlined'"
      :color="active === source.n ? 'primary' : undefined"
      :prepend-icon="icone(source)"
      :to="{
        name: 'espace',
        params: { id: espaceId, onglet: 'ressources' },
        query: source.ressource_id ? { ressource: source.ressource_id } : {},
      }"
      :aria-label="`Source ${source.n} : ${libelleSource(source)}`"
    >
      <strong class="mr-1">[{{ source.n }}]</strong>
      {{ libelleSource(source) }}
    </v-chip>
  </div>
</template>

<style scoped>
.sources-citees {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
