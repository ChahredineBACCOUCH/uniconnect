<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { useEspacesStore } from '@/stores/espaces'
import { useNotificationsStore } from '@/stores/notifications'
import { formaterDate, formaterHeure, formaterJour, messageErreur } from '@/utils/espaces'

/*
 * SCRUM-122 : invitations et notifications (E09).
 *
 * Les invitations viennent du store `espaces` (GET /me/invitations) ; les
 * notifications du store `notifications` (GET /notifications, SCRUM-121).
 * Le compteur de la cloche est rafraîchi toutes les 30 s dans AppLayout.
 */
const router = useRouter()
const espaces = useEspacesStore()
const notifications = useNotificationsStore()

const onglet = ref('notifications')

const enCours = ref(null)
const erreurInvitation = ref('')
const erreurAction = ref('')

const TYPES_NOTIFICATION = {
  INVITATION: {
    icone: 'mdi-account-plus-outline',
    fond: '#DDF2EF',
    couleur: '#00695C',
    libelle: 'Invitation',
  },
  DEMANDE_ADHESION: {
    icone: 'mdi-account-clock-outline',
    fond: '#DDF2EF',
    couleur: '#00695C',
    libelle: "Demande d'adhésion",
  },
  ANNONCE: {
    icone: 'mdi-bullhorn-outline',
    fond: '#E3EBFB',
    couleur: '#1E4FB8',
    libelle: 'Annonce',
  },
  RESSOURCE: {
    icone: 'mdi-folder-outline',
    fond: '#FEF3DC',
    couleur: '#7A4A00',
    libelle: 'Ressource',
  },
  SIGNALEMENT_TRAITE: {
    icone: 'mdi-flag-outline',
    fond: '#FBE4E4',
    couleur: '#B71C1C',
    libelle: 'Modération',
  },
  RETRAIT_ESPACE: {
    icone: 'mdi-account-minus-outline',
    fond: '#FBE4E4',
    couleur: '#B71C1C',
    libelle: 'Retrait',
  },
}

const TYPE_PAR_DEFAUT = {
  icone: 'mdi-bell-outline',
  fond: '#EEF2FA',
  couleur: '#4A5366',
  libelle: 'Notification',
}

function decoration(notification) {
  return TYPES_NOTIFICATION[notification.type] ?? TYPE_PAR_DEFAUT
}

const compteNonLues = computed(() =>
  notifications.nonLues > 1
    ? `${notifications.nonLues} notifications non lues`
    : `${notifications.nonLues} notification non lue`,
)

function invitePar(invitation) {
  const personne = invitation.invite_par
  if (!personne) return 'un membre'
  return `${personne.prenom ?? ''} ${personne.nom ?? ''}`.trim() || 'un membre'
}

/** « Il y a 20 min », « Hier à 14:32 » ou « 10/10/2026 ». */
function dateNotification(iso) {
  if (!iso) return ''

  const date = new Date(iso)
  const minutes = Math.round((Date.now() - date.getTime()) / 60_000)

  if (minutes < 1) return "À l'instant"
  if (minutes < 60) return `Il y a ${minutes} min`

  const jour = formaterJour(iso)
  if (jour === "Aujourd'hui" || jour === 'Hier') return `${jour} à ${formaterHeure(iso)}`

  return formaterDate(iso)
}

onMounted(() => {
  if (!notifications.charge) notifications.charger()
  if (!espaces.charge) espaces.charger()
})

async function accepter(invitation) {
  enCours.value = invitation.id
  erreurInvitation.value = ''

  try {
    const espace = await espaces.accepter(invitation.id)
    await notifications.charger({ discret: true })
    router.push({ name: 'espace', params: { id: espace?.id ?? invitation.espace?.id } })
  } catch (error) {
    erreurInvitation.value = messageErreur(error, "L'invitation n'a pas pu être acceptée.")
    await espaces.charger({ discret: true })
  } finally {
    enCours.value = null
  }
}

async function refuser(invitation) {
  enCours.value = invitation.id
  erreurInvitation.value = ''

  try {
    await espaces.refuser(invitation.id)
    await notifications.charger({ discret: true })
  } catch (error) {
    erreurInvitation.value = messageErreur(error, "L'invitation n'a pas pu être refusée.")
    await espaces.charger({ discret: true })
  } finally {
    enCours.value = null
  }
}

async function ouvrir(notification) {
  erreurAction.value = ''

  if (!notification.lue) {
    try {
      await notifications.marquerLue(notification.id)
    } catch (error) {
      erreurAction.value = messageErreur(
        error,
        "La notification n'a pas pu être marquée comme lue.",
      )
    }
  }

  if (notification.lien) router.push(notification.lien)
}

async function toutLire() {
  erreurAction.value = ''

  try {
    await notifications.toutLire()
  } catch (error) {
    erreurAction.value = messageErreur(
      error,
      "Les notifications n'ont pas pu être marquées comme lues.",
    )
  }
}
</script>

<template>
  <div class="notifications">
    <header class="notifications__entete">
      <h1 class="text-h5 font-weight-medium">Invitations et notifications</h1>
    </header>

    <v-alert
      v-if="notifications.erreur"
      type="error"
      variant="tonal"
      density="compact"
      class="notifications__alerte"
    >
      {{ notifications.erreur }}
      <template #append>
        <v-btn variant="text" size="small" @click="notifications.charger()">Réessayer</v-btn>
      </template>
    </v-alert>

    <v-alert
      v-if="erreurAction"
      type="error"
      variant="tonal"
      density="compact"
      closable
      class="notifications__alerte"
      @click:close="erreurAction = ''"
    >
      {{ erreurAction }}
    </v-alert>

    <v-card variant="outlined" class="notifications__carte">
      <v-tabs v-model="onglet" color="primary" show-arrows>
        <v-tab value="invitations" class="text-none">
          Invitations
          <v-badge
            v-if="espaces.invitations.length"
            :content="espaces.invitations.length"
            color="primary"
            inline
            class="ms-2"
          />
        </v-tab>

        <v-tab value="notifications" class="text-none">
          Notifications
          <v-badge
            v-if="notifications.nonLues"
            :content="notifications.nonLues"
            color="error"
            inline
            class="ms-2"
          />
        </v-tab>
      </v-tabs>

      <v-divider />

      <v-window v-model="onglet">
        <v-window-item value="invitations">
          <v-alert
            v-if="erreurInvitation"
            type="error"
            variant="tonal"
            density="compact"
            closable
            class="ma-4"
            @click:close="erreurInvitation = ''"
          >
            {{ erreurInvitation }}
          </v-alert>

          <v-skeleton-loader
            v-if="espaces.chargement && !espaces.charge"
            type="list-item-two-line, list-item-two-line"
          />

          <div v-else-if="espaces.invitations.length === 0" class="notifications__vide">
            <v-icon icon="mdi-email-open-outline" size="36" />
            <p>Aucune invitation pour l'instant.</p>
          </div>

          <ul v-else class="notifications__liste">
            <li
              v-for="invitation in espaces.invitations"
              :key="invitation.id"
              class="notifications__ligne"
            >
              <span class="notifications__icone">
                <v-icon icon="mdi-account-plus-outline" size="22" />
              </span>

              <div class="notifications__texte">
                <p class="notifications__titre">{{ invitation.espace?.nom ?? 'Un espace' }}</p>
                <p class="notifications__meta">
                  Invitation · invité par {{ invitePar(invitation) }}
                </p>
                <p class="notifications__date">
                  Reçue le {{ formaterDate(invitation.cree_le) }} · expire le
                  {{ formaterDate(invitation.expire_le) }}
                </p>
              </div>

              <div class="notifications__actions">
                <v-btn variant="outlined" :disabled="enCours !== null" @click="refuser(invitation)">
                  Refuser
                </v-btn>
                <v-btn
                  color="primary"
                  variant="flat"
                  :loading="enCours === invitation.id"
                  :disabled="enCours !== null && enCours !== invitation.id"
                  @click="accepter(invitation)"
                >
                  Accepter
                </v-btn>
              </div>
            </li>
          </ul>
        </v-window-item>

        <v-window-item value="notifications">
          <div class="notifications__barre">
            <span v-if="notifications.nonLues" class="notifications__compte">
              {{ compteNonLues }}
            </span>
            <span v-else class="notifications__compte">Tout est à jour</span>

            <v-spacer />

            <v-btn
              variant="text"
              prepend-icon="mdi-check-all"
              :disabled="notifications.nonLues === 0"
              @click="toutLire"
            >
              Tout marquer comme lu
            </v-btn>
          </div>

          <v-divider />

          <v-skeleton-loader
            v-if="notifications.chargement && !notifications.charge"
            type="list-item-two-line, list-item-two-line, list-item-two-line"
          />

          <div v-else-if="notifications.estVide" class="notifications__vide">
            <v-icon icon="mdi-bell-off-outline" size="36" />
            <p>Aucune notification pour l'instant.</p>
          </div>

          <ul v-else class="notifications__liste">
            <li v-for="notification in notifications.liste" :key="notification.id">
              <a
                :href="notification.lien || '#'"
                class="notifications__ligne"
                :class="{ 'notifications__ligne--non-lue': !notification.lue }"
                @click.prevent="ouvrir(notification)"
              >
                <span
                  class="notifications__icone"
                  :style="{
                    background: decoration(notification).fond,
                    color: decoration(notification).couleur,
                  }"
                >
                  <v-icon :icon="decoration(notification).icone" size="22" />
                </span>

                <div class="notifications__texte">
                  <p class="notifications__titre">
                    {{ notification.texte || notification.titre }}
                  </p>
                  <p class="notifications__meta">
                    {{ decoration(notification).libelle }} ·
                    {{ dateNotification(notification.cree_le) }}
                  </p>
                </div>

                <span v-if="!notification.lue" class="notifications__point" aria-label="Non lue" />
              </a>
            </li>
          </ul>
        </v-window-item>
      </v-window>
    </v-card>
  </div>
</template>

<style scoped>
.notifications {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.notifications__entete {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.notifications__carte {
  overflow: hidden;
}

.notifications__liste {
  margin: 0;
  padding: 0;
  list-style: none;
}

.notifications__ligne {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 20px;
  border-bottom: 1px solid rgb(var(--v-theme-outline));
  color: rgb(var(--v-theme-on-surface));
  text-decoration: none;
  transition: background-color 0.15s ease;
}

.notifications__ligne:last-child {
  border-bottom: none;
}

.notifications__ligne:hover {
  background: rgb(var(--v-theme-surface-variant));
}

.notifications__ligne--non-lue {
  background: rgba(var(--v-theme-primary), 0.04);
}

.notifications__icone {
  display: grid;
  place-items: center;
  flex: none;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: #eef2fa;
  color: #4a5366;
}

.notifications__texte {
  flex: 1;
  min-width: 0;
}

.notifications__titre {
  margin: 0;
  font-weight: 500;
  overflow-wrap: anywhere;
}

.notifications__meta,
.notifications__date {
  margin: 2px 0 0;
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.875rem;
}

.notifications__date {
  font-size: 0.8rem;
}

.notifications__actions {
  display: flex;
  flex: none;
  flex-wrap: wrap;
  gap: 8px;
}

.notifications__barre {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 8px 20px;
}

.notifications__compte {
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.875rem;
}

.notifications__point {
  flex: none;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
}

.notifications__vide {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 48px 24px;
  color: rgb(var(--v-theme-on-surface-variant));
  text-align: center;
}

.notifications__vide p {
  margin: 0;
}

@media (max-width: 600px) {
  .notifications__ligne {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .notifications__actions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
