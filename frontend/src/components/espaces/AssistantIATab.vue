<script setup>
/*
 * Onglet « Assistant IA » d'un espace (maquette E05-AssistantIA / M-AssistantIA, SCRUM-142),
 * affiché par EspaceView. L'étudiant pose une question sur le cours ; l'assistant répond
 * à partir des ressources de l'espace et cite ses sources [n] (fichier + page).
 *
 * Les marqueurs [n] de la réponse sont cliquables et mettent en évidence la source
 * correspondante (SourcesCitees, SCRUM-143).
 * Sous chaque réponse : avis 👍/👎 (SCRUM-146).
 * Pas encore ici : panneau latéral « Résumé » (SCRUM-158) et
 * « Sources de l'assistant », qui attend GET /espaces/{id}/ressources (SCRUM-126).
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'

import { useAuthStore } from '@/stores/auth'
import { QUESTION_MAX, QUESTION_MIN, QUOTA_JOUR, useIaStore } from '@/stores/ia'
import { decouperReponse } from './reponseIa'
import SourcesAssistant from './SourcesAssistant.vue'
import SourcesCitees from './SourcesCitees.vue'

const props = defineProps({
  // Espace renvoyé par GET /espaces/{id} (docs/api.md) : id, nom, assistant { actif, … }.
  espace: { type: Object, required: true },
  suggestions: {
    type: Array,
    default: () => [
      'Résume les notions clés du cours',
      'Explique-moi une notion importante avec un exemple',
      'Prépare-moi un quiz de 3 questions',
    ],
  },
})

const ia = useIaStore()
const auth = useAuthStore()
const { xs, mdAndUp } = useDisplay()

const brouillon = ref('')
const zoneMessages = ref(null)
const racine = ref(null)
const hauteur = ref(null)

/*
 * L'onglet occupe tout l'écran restant sous l'en-tête de l'espace et les onglets,
 * pour que le champ de saisie reste visible en bas sans faire défiler la page.
 */
function ajusterHauteur() {
  const el = racine.value
  if (!el) return
  const haut = el.getBoundingClientRect().top + window.scrollY
  const barreDuBas = parseFloat(getComputedStyle(el).getPropertyValue('--v-layout-bottom')) || 0
  const margeBasse = window.innerWidth < 960 ? 24 : 40 // padding bas de AppLayout
  hauteur.value = Math.max(420, Math.floor(window.innerHeight - haut - barreDuBas - margeBasse))
}

onMounted(() => {
  ajusterHauteur()
  window.addEventListener('resize', ajusterHauteur)
})
onBeforeUnmount(() => window.removeEventListener('resize', ajusterHauteur))

const espaceId = computed(() => props.espace.id)

// SCRUM-143 : source mise en évidence pour chaque réponse (index de l'échange → n).
const sourceActive = ref({})

// Nouvelle conversation : les index des échanges repartent de zéro.
watch(
  () => ia.echanges.length,
  (nombre, avant) => {
    if (nombre < avant) {
      sourceActive.value = {}
      avisEnEchec.value = {}
    }
  },
)

// SCRUM-146 : avis 👍/👎 ; message sous la réponse si l'API l'a refusé.
const avisEnEchec = ref({})

async function donnerAvis(index, utile) {
  const enregistre = await ia.donnerAvis(index, utile)
  avisEnEchec.value = { ...avisEnEchec.value, [index]: !enregistre }
}

async function surlignerSource(index, n) {
  sourceActive.value = { ...sourceActive.value, [index]: n }
  await nextTick()
  document
    .getElementById(`source-${index}-${n}`)
    ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
}
const nomCours = computed(() => props.espace.nom ?? '')

watch(espaceId, (id) => ia.ouvrir(id), { immediate: true })

const longueur = computed(() => brouillon.value.trim().length)
const erreurSaisie = computed(() =>
  longueur.value > QUESTION_MAX ? `${QUESTION_MAX} caractères au maximum.` : '',
)
// Aucun support indexé (renvoyé par GET /espaces/{id}) : l'assistant n'a rien pour répondre (SCRUM-144).
const sansSupport = computed(() => props.espace.assistant?.nb_documents_indexes === 0)

// Panneau « Sources de l'assistant » : grand écran, et seulement s'il y a des supports.
const afficherSources = computed(() => mdAndUp.value && !sansSupport.value)

const peutEnvoyer = computed(
  () =>
    !sansSupport.value &&
    !ia.enCours &&
    !ia.quotaAtteint &&
    longueur.value >= QUESTION_MIN &&
    longueur.value <= QUESTION_MAX,
)

const accueil = computed(() => {
  const prenom = auth.user?.prenom ? ` ${auth.user.prenom}` : ''
  return `Bonjour${prenom}. Je réponds à vos questions à partir des supports publiés dans cet espace, en citant mes sources.`
})

async function poser(question) {
  if (!peutEnvoyerTexte(question)) return
  brouillon.value = ''
  await ia.poser(espaceId.value, question)
}

function peutEnvoyerTexte(question) {
  const n = question.trim().length
  return (
    !sansSupport.value && !ia.enCours && !ia.quotaAtteint && n >= QUESTION_MIN && n <= QUESTION_MAX
  )
}

function envoyer() {
  if (peutEnvoyer.value) poser(brouillon.value)
}

// Messages d'erreur affichés dans la bulle de l'assistant (docs/api.md, codes de réponse).
function messageErreur(erreur) {
  switch (erreur?.code) {
    case 429:
      return {
        type: 'warning',
        titre: 'Quota atteint',
        texte: erreur.message,
        reessayer: false,
      }
    case 503:
      return {
        type: 'error',
        titre: "L'assistant est momentanément indisponible.",
        texte:
          "Le service d'IA ne répond pas. Votre question n'a pas été perdue : réessayez dans quelques instants.",
        reessayer: true,
      }
    case 404:
      return {
        type: 'info',
        titre: "L'assistant n'est pas activé dans cet espace.",
        texte: "Demandez à l'enseignant de l'activer dans les paramètres de l'espace.",
        reessayer: false,
      }
    default:
      return {
        type: 'error',
        titre: "La question n'a pas pu être envoyée.",
        texte: 'Vérifiez votre connexion puis réessayez.',
        reessayer: true,
      }
  }
}

// Fait défiler jusqu'au dernier message à chaque question ou réponse.
watch(
  () => ia.echanges.map((e) => e.statut).join('|'),
  async () => {
    await nextTick()
    const zone = zoneMessages.value
    if (zone) zone.scrollTop = zone.scrollHeight
  },
)
</script>

<template>
  <div
    ref="racine"
    class="assistant-cadre"
    :class="{ 'assistant-cadre--avec-sources': afficherSources }"
    :style="hauteur ? { height: `${hauteur}px` } : undefined"
  >
    <section class="assistant" aria-label="Assistant IA">
      <header class="assistant__entete">
        <span class="assistant__avatar" aria-hidden="true">
          <v-icon icon="mdi-creation" size="20" />
        </span>
        <div class="assistant__titre">
          <div class="d-flex align-center ga-2">
            <h2 class="text-h6">Assistant du cours</h2>
            <v-chip class="chip-beta">Bêta</v-chip>
          </div>
          <span class="text-caption text-medium-emphasis">
            Répond à partir des ressources publiées dans cet espace, en citant ses sources.
          </span>
        </div>
        <v-btn
          variant="outlined"
          prepend-icon="mdi-plus"
          :disabled="ia.estVide || ia.enCours"
          @click="ia.nouvelleConversation()"
        >
          Nouvelle conversation
        </v-btn>
      </header>

      <div v-if="sansSupport" class="assistant__vide">
        <v-icon icon="mdi-book-open-page-variant-outline" size="40" />
        <p class="text-subtitle-1 font-weight-medium">Pas encore de supports de cours</p>
        <p class="text-body-2 text-medium-emphasis">
          L'enseignant n'a pas encore ajouté de supports de cours pour l'assistant. Revenez quand
          des ressources seront publiées dans l'onglet Ressources.
        </p>
      </div>

      <div v-else ref="zoneMessages" class="assistant__messages" aria-live="polite">
        <div class="message message--ia">
          <span class="assistant__avatar" aria-hidden="true">
            <v-icon icon="mdi-creation" size="20" />
          </span>
          <div class="bulle bulle--ia">
            <p>
              {{ accueil }}
              <template v-if="nomCours">
                Cours : <strong>{{ nomCours }}</strong
                >.
              </template>
            </p>
          </div>
        </div>

        <template v-for="(echange, index) in ia.echanges" :key="index">
          <div class="message message--question">
            <div class="bulle bulle--question">{{ echange.question }}</div>
          </div>

          <div class="message message--ia">
            <span class="assistant__avatar" aria-hidden="true">
              <v-icon icon="mdi-creation" size="20" />
            </span>

            <div class="bulle bulle--ia">
              <div v-if="echange.statut === 'EN_COURS'" class="attente">
                <v-progress-circular indeterminate size="18" width="2" color="#B45309" />
                Recherche dans les ressources du cours…
              </div>

              <p v-else-if="echange.statut === 'SANS_SOURCE'" class="text-medium-emphasis">
                Je n'ai pas trouvé de réponse fiable dans les ressources de cet espace. Essayez de
                reformuler, ou posez votre question dans la Discussion.
              </p>

              <v-alert
                v-else-if="echange.statut === 'ERREUR'"
                :type="messageErreur(echange.erreur).type"
                variant="tonal"
                density="compact"
                :title="messageErreur(echange.erreur).titre"
              >
                <p class="text-body-2">{{ messageErreur(echange.erreur).texte }}</p>
                <v-btn
                  v-if="messageErreur(echange.erreur).reessayer"
                  class="mt-2"
                  size="small"
                  variant="outlined"
                  prepend-icon="mdi-refresh"
                  :disabled="ia.enCours"
                  @click="ia.reessayer(index)"
                >
                  Réessayer
                </v-btn>
              </v-alert>

              <template v-else>
                <template
                  v-for="(bloc, b) in decouperReponse(echange.reponse, echange.sources)"
                  :key="b"
                >
                  <p v-if="bloc.type === 'paragraphe'">
                    <template v-for="(s, k) in bloc.segments" :key="k">
                      <button
                        v-if="s.citations"
                        type="button"
                        class="citation"
                        :class="{ 'citation--active': s.citations.includes(sourceActive[index]) }"
                        :aria-label="`Voir la source ${s.citations.join(', ')}`"
                        @click="surlignerSource(index, s.citations[0])"
                      >
                        {{ s.texte }}
                      </button>
                      <strong v-else-if="s.gras">{{ s.texte }}</strong>
                      <template v-else>{{ s.texte }}</template>
                    </template>
                  </p>
                  <ul v-else>
                    <li v-for="(item, j) in bloc.items" :key="j">
                      <template v-for="(s, k) in item" :key="k">
                        <button
                          v-if="s.citations"
                          type="button"
                          class="citation"
                          :class="{ 'citation--active': s.citations.includes(sourceActive[index]) }"
                          :aria-label="`Voir la source ${s.citations.join(', ')}`"
                          @click="surlignerSource(index, s.citations[0])"
                        >
                          {{ s.texte }}
                        </button>
                        <strong v-else-if="s.gras">{{ s.texte }}</strong>
                        <template v-else>{{ s.texte }}</template>
                      </template>
                    </li>
                  </ul>
                </template>

                <SourcesCitees
                  :sources="echange.sources"
                  :espace-id="espaceId"
                  :prefixe="`source-${index}`"
                  :active="sourceActive[index] ?? null"
                />

                <span class="text-caption text-medium-emphasis">
                  Réponse générée par IA, à vérifier dans les sources.
                </span>
              </template>

              <!-- SCRUM-146 : avis sur la réponse (aussi quand le cours ne contenait pas la réponse). -->
              <div
                v-if="echange.echangeId && ['OK', 'SANS_SOURCE'].includes(echange.statut)"
                class="avis"
              >
                <span class="text-caption text-medium-emphasis">
                  {{
                    echange.avis === null
                      ? 'Cette réponse vous a-t-elle aidé ?'
                      : 'Merci pour votre avis.'
                  }}
                </span>
                <v-btn
                  icon
                  size="x-small"
                  variant="text"
                  :color="echange.avis === true ? 'success' : undefined"
                  :aria-pressed="echange.avis === true"
                  aria-label="Réponse utile"
                  @click="donnerAvis(index, true)"
                >
                  <v-icon
                    :icon="echange.avis === true ? 'mdi-thumb-up' : 'mdi-thumb-up-outline'"
                    size="18"
                  />
                </v-btn>
                <v-btn
                  icon
                  size="x-small"
                  variant="text"
                  :color="echange.avis === false ? 'error' : undefined"
                  :aria-pressed="echange.avis === false"
                  aria-label="Réponse pas utile"
                  @click="donnerAvis(index, false)"
                >
                  <v-icon
                    :icon="echange.avis === false ? 'mdi-thumb-down' : 'mdi-thumb-down-outline'"
                    size="18"
                  />
                </v-btn>
                <span v-if="avisEnEchec[index]" class="text-caption text-error">
                  Votre avis n'a pas pu être enregistré.
                </span>
              </div>
            </div>
          </div>
        </template>

        <div v-if="ia.estVide" class="suggestions" aria-label="Questions suggérées">
          <v-chip
            v-for="suggestion in suggestions"
            :key="suggestion"
            variant="outlined"
            prepend-icon="mdi-lightbulb-outline"
            :disabled="ia.enCours || ia.quotaAtteint"
            @click="poser(suggestion)"
          >
            {{ suggestion }}
          </v-chip>
        </div>
      </div>

      <form class="assistant__saisie" @submit.prevent="envoyer">
        <div class="d-flex align-end ga-2">
          <v-textarea
            v-model="brouillon"
            label="Votre question"
            placeholder="Posez une question sur le cours…"
            rows="1"
            auto-grow
            max-rows="5"
            hide-details="auto"
            :error-messages="erreurSaisie"
            :disabled="ia.quotaAtteint || sansSupport"
            @keydown.enter.exact.prevent="envoyer"
          />
          <!-- Mobile : bouton icône seul (un contenu, même vide, masquerait l'icône). -->
          <v-btn
            v-if="xs"
            type="submit"
            class="btn-ia"
            icon="mdi-send"
            aria-label="Demander"
            rounded="lg"
            :loading="ia.enCours"
            :disabled="!peutEnvoyer"
          />
          <v-btn
            v-else
            type="submit"
            class="btn-ia"
            height="48"
            prepend-icon="mdi-send"
            :loading="ia.enCours"
            :disabled="!peutEnvoyer"
          >
            Demander
          </v-btn>
        </div>
        <div class="assistant__aide text-caption text-medium-emphasis">
          <span>
            L'assistant peut se tromper. Vos questions sont privées : ni l'enseignant ni les autres
            membres ne les voient.
          </span>
          <span v-if="ia.quotaRestant !== null" class="font-weight-medium">
            Questions restantes aujourd'hui : {{ ia.quotaRestant }} sur {{ QUOTA_JOUR }}
          </span>
        </div>
      </form>
    </section>

    <!-- SCRUM-143 (étape 5) : documents indexés de l'espace, sur grand écran. -->
    <SourcesAssistant v-if="afficherSources" :espace-id="espaceId" />
  </div>
</template>

<style scoped>
/* Hauteur calculée en JavaScript (ajusterHauteur) : tout l'espace restant sous l'en-tête et les onglets. */
.assistant-cadre {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 16px;
  min-height: 420px;
}

.assistant-cadre--avec-sources {
  grid-template-columns: minmax(0, 1fr) 280px;
}

.assistant-cadre > .assistant {
  height: 100%;
  min-height: 0;
}

.assistant {
  display: flex;
  flex-direction: column;
  min-height: 420px;
  border: 1px solid rgb(var(--v-theme-outline));
  border-radius: 12px;
  background: rgb(var(--v-theme-background));
  overflow: hidden;
}

.assistant__entete {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 12px 24px;
  background: rgb(var(--v-theme-surface));
  border-bottom: 1px solid rgb(var(--v-theme-outline));
}

.assistant__titre {
  flex: 1;
  min-width: 200px;
}

.assistant__titre h2 {
  margin: 0;
}

.assistant__avatar {
  width: 36px;
  height: 36px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: #b45309;
  color: #fff;
}

.assistant__vide {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 32px 24px;
  text-align: center;
  color: rgb(var(--v-theme-on-surface-variant));
}

.assistant__vide p {
  margin: 0;
  max-width: 460px;
}

.chip-beta {
  background: #fff1dc !important;
  color: #7a3e00 !important;
}

.assistant__messages {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.message {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.message--question {
  justify-content: flex-end;
}

.bulle {
  padding: 10px 14px;
  max-width: 70%;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.bulle--question {
  background: rgb(var(--v-theme-primary));
  color: #fff;
  border-radius: 16px 16px 4px 16px;
}

.bulle--ia {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: min(720px, 85%);
  white-space: normal;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgb(var(--v-theme-outline));
  border-radius: 4px 16px 16px 16px;
}

.bulle p,
.bulle ul {
  margin: 0;
}

.bulle ul {
  padding-left: 20px;
}

.avis {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 2px;
  margin-top: 6px;
}

.avis > .text-caption:first-child {
  margin-right: 6px;
}

.attente {
  display: flex;
  align-items: center;
  gap: 10px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.citation {
  padding: 0 2px;
  border-radius: 4px;
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  cursor: pointer;
}

.citation:hover,
.citation:focus-visible,
.citation--active {
  background: rgb(var(--v-theme-primary-soft));
}

.sources,
.suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.suggestions {
  padding-left: 48px;
}

.assistant__saisie {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 16px;
  background: rgb(var(--v-theme-surface));
  border-top: 1px solid rgb(var(--v-theme-outline));
}

.assistant__aide {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 4px 16px;
}

.btn-ia {
  background: #b45309 !important;
  color: #fff !important;
}

/* Mobile : toute la largeur, sans bordure, bulles plus larges, champ de saisie collé en bas. */
@media (max-width: 599px) {
  .assistant {
    border-inline: 0;
    border-radius: 0;
    margin-inline: -16px;
  }

  .assistant__entete,
  .assistant__messages {
    padding-inline: 16px;
  }

  .bulle,
  .bulle--ia {
    max-width: 88%;
  }

  .suggestions {
    padding-left: 0;
  }
}
</style>
