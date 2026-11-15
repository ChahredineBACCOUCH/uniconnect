<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'

import {
  envoyerMessage,
  modifierMessage,
  recupererMessages,
  supprimerMessage,
} from '@/api/espaces'
import { usePolling } from '@/composables/usePolling'
import { useAuthStore } from '@/stores/auth'
import {
  extrait,
  fusionnerMessages,
  grouperMessagesParJour,
  messageErreur,
  nomAuteur,
} from '@/utils/espaces'

import Composer from './Composer.vue'
import MessageItem from './MessageItem.vue'

const props = defineProps({
  espace: { type: Object, required: true },
})

const auth = useAuthStore()

const messages = ref([])
const chargement = ref(true)
const erreurChargement = ref(null)
const erreurPolling = ref(null)
const pret = ref(false)
const conteneur = ref(null)
const reponseA = ref(null)
const nouveauxNonLus = ref(0)
const signales = ref([])

const suppressionCible = ref(null)
const suppressionEnCours = ref(false)

const signalementCible = ref(null)
const motif = ref('')
const precisions = ref('')

const snackbar = ref({ actif: false, texte: '' })

const motifs = [
  { label: 'Contenu inapproprié', description: 'Propos choquants, vulgaires ou hors sujet' },
  { label: 'Harcèlement', description: 'Attaque ou intimidation envers une personne' },
  { label: 'Spam', description: 'Publicité, messages répétés ou liens suspects' },
  { label: 'Information erronée', description: 'Information fausse pouvant induire en erreur' },
  { label: 'Autre', description: 'Précisez dans le champ ci-dessous' },
]

const utilisateurId = computed(() => auth.user?.id ?? null)
const entrees = computed(() => grouperMessagesParJour(messages.value))
const dernierId = computed(() => messages.value.at(-1)?.id ?? 0)
const suppressionOuverte = computed(() => suppressionCible.value !== null)
const signalementOuvert = computed(() => signalementCible.value !== null)

onMounted(chargerMessages)

usePolling(verifierNouveaux, {
  onError: () => {
    erreurPolling.value = 'La mise à jour automatique est momentanément indisponible.'
  },
})

async function chargerMessages() {
  chargement.value = true
  erreurChargement.value = null
  pret.value = false

  try {
    const reponse = await recupererMessages(props.espace.id)
    messages.value = reponse.data ?? []
    pret.value = true
    await defilerEnBas()
  } catch (error) {
    erreurChargement.value = messageErreur(error, "La discussion n'a pas pu être chargée.")
  } finally {
    chargement.value = false
  }
}

/** Polling toutes les 5 s : seuls les messages plus récents que le dernier affiché. */
async function verifierNouveaux() {
  if (!pret.value) return

  const reponse = await recupererMessages(props.espace.id, { after_id: dernierId.value })
  const nouveaux = reponse.data ?? []

  erreurPolling.value = null

  if (nouveaux.length === 0) return

  const procheDuBas = estProcheDuBas()
  const avant = messages.value.length

  messages.value = fusionnerMessages(messages.value, nouveaux)

  const ajoutes = messages.value.length - avant

  if (ajoutes <= 0) return

  if (procheDuBas) await defilerEnBas()
  else nouveauxNonLus.value += ajoutes
}

async function envoyer(payload, options) {
  const message = await envoyerMessage(props.espace.id, payload, options)

  messages.value = fusionnerMessages(messages.value, [message])
  reponseA.value = null
  await defilerEnBas()

  return message
}

async function modifier(message, contenu) {
  try {
    const misAJour = await modifierMessage(message.id, contenu)
    messages.value = fusionnerMessages(messages.value, [misAJour])
    notifier('Message modifié.')
  } catch (error) {
    notifier(messageErreur(error, "Le message n'a pas pu être modifié."))
  }
}

function demanderSuppression(message) {
  suppressionCible.value = message
}

async function confirmerSuppression() {
  const message = suppressionCible.value
  if (!message) return

  suppressionEnCours.value = true

  try {
    await supprimerMessage(message.id)
    messages.value = messages.value.filter((element) => element.id !== message.id)
    notifier('Message supprimé.')
    suppressionCible.value = null
  } catch (error) {
    notifier(messageErreur(error, "Le message n'a pas pu être supprimé."))
  } finally {
    suppressionEnCours.value = false
  }
}

function fermerSuppression(valeur) {
  if (!valeur && !suppressionEnCours.value) suppressionCible.value = null
}

function ouvrirSignalement(message) {
  signalementCible.value = message
  motif.value = ''
  precisions.value = ''
}

function fermerSignalement(valeur) {
  if (!valeur) signalementCible.value = null
}

function envoyerSignalement() {
  if (!signalementCible.value || !motif.value) return

  // Les signalements arrivent avec la modération (v1.1) : on garde l'état
  // localement pour que l'utilisateur voie que son signalement est pris en compte.
  signales.value = [...signales.value, signalementCible.value.id]
  signalementCible.value = null
  motif.value = ''
  precisions.value = ''
  notifier('Signalement envoyé. Vous serez notifié de la décision.')
}

function estSignale(message) {
  return signales.value.includes(message.id)
}

function estProcheDuBas() {
  const element = conteneur.value
  if (!element) return true

  return element.scrollHeight - element.scrollTop - element.clientHeight < 80
}

async function defilerEnBas() {
  await nextTick()
  const element = conteneur.value
  if (element) element.scrollTop = element.scrollHeight
  nouveauxNonLus.value = 0
}

function surDefilement() {
  if (estProcheDuBas()) nouveauxNonLus.value = 0
}

function notifier(texte) {
  snackbar.value = { actif: true, texte }
}
</script>

<template>
  <div class="discussion">
    <v-alert v-if="erreurChargement" type="error" variant="tonal" class="ma-4">
      {{ erreurChargement }}
      <template #append>
        <v-btn variant="text" size="small" @click="chargerMessages">Réessayer</v-btn>
      </template>
    </v-alert>

    <div v-else class="discussion__panneau">
      <div class="discussion__barre">
        <span class="discussion__compteur">
          {{ messages.length }} {{ messages.length > 1 ? 'messages' : 'message' }}
        </span>
        <v-chip
          v-if="erreurPolling"
          size="small"
          color="warning"
          variant="tonal"
          prepend-icon="mdi-alert-circle-outline"
        >
          {{ erreurPolling }}
        </v-chip>
      </div>

      <div class="discussion__messages-wrap">
        <div
          ref="conteneur"
          class="discussion__messages"
          role="log"
          aria-label="Messages de la discussion"
          @scroll.passive="surDefilement"
        >
          <v-progress-linear v-if="chargement" indeterminate color="primary" />

          <div v-else-if="messages.length === 0" class="discussion__vide">
            <v-icon icon="mdi-message-outline" size="40" />
            <p>Aucun message pour l'instant.</p>
            <span>Soyez le premier à lancer la discussion.</span>
          </div>

          <template v-for="entree in entrees" :key="entree.cle">
            <div v-if="entree.type === 'jour'" class="discussion__jour">
              <span>{{ entree.libelle }}</span>
            </div>

            <MessageItem
              v-else
              :message="entree.message"
              :utilisateur-id="utilisateurId"
              :signale="estSignale(entree.message)"
              @repondre="reponseA = $event"
              @modifier="modifier"
              @supprimer="demanderSuppression"
              @signaler="ouvrirSignalement"
              @notifier="notifier"
            />
          </template>
        </div>

        <v-btn
          v-if="nouveauxNonLus > 0"
          class="discussion__nouveaux"
          color="primary"
          size="small"
          rounded="pill"
          variant="flat"
          prepend-icon="mdi-arrow-down"
          @click="defilerEnBas"
        >
          {{ nouveauxNonLus }} nouveau{{ nouveauxNonLus > 1 ? 'x' : '' }} message{{
            nouveauxNonLus > 1 ? 's' : ''
          }}
        </v-btn>
      </div>

      <Composer
        :espace="espace"
        :reponse-a="reponseA"
        :utilisateur-id="utilisateurId"
        :envoyer="envoyer"
        @annuler-reponse="reponseA = null"
      />
    </div>

    <v-dialog :model-value="suppressionOuverte" max-width="430" @update:model-value="fermerSuppression">
      <v-card>
        <v-card-title class="text-h6">Supprimer ce message ?</v-card-title>
        <v-card-text>
          Cette action est définitive. Le message ne sera plus visible dans la discussion.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="suppressionEnCours" @click="suppressionCible = null">
            Annuler
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="suppressionEnCours"
            @click="confirmerSuppression"
          >
            Supprimer
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog :model-value="signalementOuvert" max-width="580" @update:model-value="fermerSignalement">
      <v-card>
        <v-card-title class="d-flex align-center ga-2 text-h6">
          <v-icon icon="mdi-flag-outline" color="error" />
          Signaler ce message
        </v-card-title>

        <v-card-text class="signalement">
          <div v-if="signalementCible" class="signalement__citation">
            <span class="signalement__auteur">
              {{ nomAuteur(signalementCible.auteur, utilisateurId) }} ·
              {{ signalementCible.cree_le ? new Date(signalementCible.cree_le).toLocaleString('fr-FR') : '' }}
            </span>
            <span>{{ extrait(signalementCible.contenu, 220) }}</span>
          </div>

          <v-radio-group v-model="motif" label="Motif du signalement" hide-details>
            <v-radio
              v-for="item in motifs"
              :key="item.label"
              :value="item.label"
              :label="`${item.label} — ${item.description}`"
            />
          </v-radio-group>

          <v-textarea
            v-model="precisions"
            label="Précisions (facultatif)"
            rows="2"
            variant="outlined"
            density="compact"
            hide-details
          />

          <p class="signalement__note">
            Votre signalement est anonyme pour l'auteur du message. Il est transmis à la
            propriétaire de l'espace et aux modérateurs ; vous serez notifié de la décision.
          </p>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="signalementCible = null">Annuler</v-btn>
          <v-btn color="primary" variant="flat" :disabled="!motif" @click="envoyerSignalement">
            Envoyer le signalement
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.actif" :timeout="4000" location="bottom">
      {{ snackbar.texte }}
    </v-snackbar>
  </div>
</template>

<style scoped>
.discussion {
  display: flex;
  flex-direction: column;
  min-height: 0;
}

.discussion__panneau {
  display: flex;
  flex-direction: column;
  min-height: 420px;
  border: 1px solid rgb(var(--v-theme-outline));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  overflow: hidden;
}

.discussion__barre {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 16px;
  border-bottom: 1px solid rgb(var(--v-theme-outline));
  background: rgb(var(--v-theme-surface-variant));
}

.discussion__compteur {
  font-size: 12px;
  font-weight: 500;
  color: rgb(var(--v-theme-on-surface-variant));
}

.discussion__messages-wrap {
  position: relative;
  flex: 1;
  min-height: 0;
}

.discussion__messages {
  height: calc(100vh - 400px);
  min-height: 280px;
  max-height: 720px;
  overflow-y: auto;
  padding: 12px 8px 20px;
}

.discussion__vide {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 260px;
  color: rgb(var(--v-theme-on-surface-variant));
  text-align: center;
}

.discussion__vide p {
  margin: 0;
  font-weight: 500;
  color: rgb(var(--v-theme-on-surface));
}

.discussion__vide span {
  font-size: 13px;
}

.discussion__jour {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 12px 12px 6px;
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 12px;
  font-weight: 500;
}

.discussion__jour::before,
.discussion__jour::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgb(var(--v-theme-outline));
}

.discussion__nouveaux {
  position: absolute;
  bottom: 12px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 1;
}

.signalement {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.signalement__citation {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px;
  border-left: 3px solid rgb(var(--v-theme-outline));
  border-radius: 6px;
  background: rgb(var(--v-theme-surface-variant));
  white-space: pre-line;
}

.signalement__auteur {
  font-size: 12px;
  font-weight: 500;
  color: rgb(var(--v-theme-on-surface-variant));
}

.signalement__note {
  margin: 0;
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

@media (max-width: 760px) {
  .discussion__messages {
    height: calc(100vh - 460px);
  }
}
</style>
