<script setup>
import { computed, ref } from 'vue'

import PieceJointeItem from './PieceJointeItem.vue'
import {
  badgeAuteur,
  couleurAuteur,
  formaterExpiration,
  formaterHeure,
  initialesAuteur,
  nomAuteur,
} from '@/utils/espaces'

const props = defineProps({
  message: { type: Object, required: true },
  utilisateurId: { type: Number, default: null },
  signale: { type: Boolean, default: false },
})

const emit = defineEmits(['repondre', 'modifier', 'supprimer', 'signaler', 'notifier'])

const menuOuvert = ref(false)
const editionEnCours = ref(false)
const brouillon = ref('')

const auteur = computed(() => props.message.auteur)
const estMoi = computed(() => auteur.value?.id === props.utilisateurId)
const nom = computed(() => nomAuteur(auteur.value, props.utilisateurId))
const initiales = computed(() => initialesAuteur(auteur.value))
const couleur = computed(() => couleurAuteur(auteur.value))
const badge = computed(() => badgeAuteur(auteur.value))
const heure = computed(() => formaterHeure(props.message.cree_le))
const expiration = computed(() => formaterExpiration(props.message.expire_le))

function repondre() {
  menuOuvert.value = false
  emit('repondre', props.message)
}

async function copier() {
  menuOuvert.value = false

  try {
    await navigator.clipboard.writeText(props.message.contenu)
    emit('notifier', 'Message copié.')
  } catch {
    // Certains navigateurs refusent l'API Clipboard hors contexte sécurisé.
    const zone = document.createElement('textarea')
    zone.value = props.message.contenu
    zone.setAttribute('readonly', '')
    zone.style.position = 'fixed'
    zone.style.opacity = '0'
    document.body.appendChild(zone)
    zone.select()

    try {
      document.execCommand('copy')
      emit('notifier', 'Message copié.')
    } finally {
      document.body.removeChild(zone)
    }
  }
}

function commencerEdition() {
  menuOuvert.value = false
  brouillon.value = props.message.contenu
  editionEnCours.value = true
}

function validerEdition() {
  const contenu = brouillon.value.trim()

  if (!contenu || contenu === props.message.contenu) {
    editionEnCours.value = false
    return
  }

  emit('modifier', props.message, contenu)
  editionEnCours.value = false
}

function annulerEdition() {
  editionEnCours.value = false
  brouillon.value = ''
}

function supprimer() {
  menuOuvert.value = false
  emit('supprimer', props.message)
}

function signaler() {
  menuOuvert.value = false
  emit('signaler', props.message)
}
</script>

<template>
  <article class="message" :class="{ 'message--own': estMoi }">
    <v-avatar :color="couleur" size="40" class="message__avatar">
      <span class="message__initiales">{{ initiales }}</span>
    </v-avatar>

    <div class="message__corps">
      <div class="message__entete">
        <span class="message__auteur">{{ nom }}</span>
        <v-chip v-if="badge" size="x-small" color="primary" variant="tonal" label>
          {{ badge }}
        </v-chip>
        <span class="message__heure">{{ heure }}</span>
        <v-chip v-if="signale" size="x-small" color="warning" variant="tonal" label>
          Signalé par vous
        </v-chip>
        <span v-if="message.modifie" class="message__modifie">(modifié)</span>
      </div>

      <div v-if="editionEnCours" class="message__edition">
        <v-textarea
          v-model="brouillon"
          variant="outlined"
          density="compact"
          rows="2"
          auto-grow
          maxlength="10000"
          label="Modifier le message"
          hide-details
          autofocus
          @keydown.esc="annulerEdition"
        />
        <div class="message__edition-actions">
          <v-btn variant="text" size="small" @click="annulerEdition">Annuler</v-btn>
          <v-btn color="primary" size="small" variant="flat" @click="validerEdition">
            Enregistrer
          </v-btn>
        </div>
      </div>

      <p v-else-if="message.contenu" class="message__contenu">{{ message.contenu }}</p>

      <div v-if="message.pieces_jointes?.length" class="message__pieces">
        <PieceJointeItem
          v-for="piece in message.pieces_jointes"
          :key="piece.id"
          :piece="piece"
          @erreur="emit('notifier', $event)"
        />
      </div>

      <span v-if="expiration" class="message__expiration">
        <v-icon icon="mdi-timer-outline" size="14" />
        {{ expiration }}
      </span>
    </div>

    <v-menu v-model="menuOuvert" location="bottom end">
      <template #activator="{ props: menuProps }">
        <v-btn
          v-bind="menuProps"
          icon
          variant="text"
          size="small"
          density="comfortable"
          aria-label="Actions du message"
          class="message__menu"
        >
          <v-icon icon="mdi-dots-vertical" size="20" />
        </v-btn>
      </template>

      <v-list density="compact" min-width="220">
        <v-list-item prepend-icon="mdi-reply" title="Répondre" @click="repondre" />
        <v-list-item prepend-icon="mdi-content-copy" title="Copier le texte" @click="copier" />
        <template v-if="estMoi">
          <v-list-item prepend-icon="mdi-pencil" title="Modifier" @click="commencerEdition" />
          <v-divider />
          <v-list-item
            prepend-icon="mdi-delete-outline"
            title="Supprimer"
            base-color="error"
            @click="supprimer"
          />
        </template>
        <template v-else>
          <v-divider />
          <v-list-item
            prepend-icon="mdi-flag-outline"
            title="Signaler le message"
            base-color="error"
            @click="signaler"
          />
        </template>
      </v-list>
    </v-menu>
  </article>
</template>

<style scoped>
.message {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 12px;
}

.message:hover {
  background: rgba(var(--v-theme-on-surface), 0.03);
}

.message__avatar {
  flex: none;
}

.message__initiales {
  font-size: 14px;
  font-weight: 700;
  color: #fff;
}

.message__corps {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: 3px;
}

.message__entete {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.message__auteur {
  font-weight: 500;
}

.message__heure,
.message__modifie {
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.message__modifie {
  font-style: italic;
}

.message__contenu {
  margin: 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.message__edition {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.message__edition-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.message__pieces {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 4px;
}

.message__expiration {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
  font-size: 12px;
  color: #8a4100;
}

.message__menu {
  flex: none;
}
</style>
