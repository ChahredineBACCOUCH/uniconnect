<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { useEspacesStore } from '@/stores/espaces'
import { formaterDate, libelleProfil, messageErreur } from '@/utils/espaces'

/*
 * SCRUM-86 : accueil (E04). Compteurs, invitations et espaces viennent de l'API
 * (store `espaces` : GET /me/espaces et GET /me/invitations), rien n'est écrit en dur.
 */
const router = useRouter()
const auth = useAuthStore()
const espaces = useEspacesStore()

const salutation = computed(() => (auth.user?.prenom ? `Bonjour ${auth.user.prenom}` : 'Bonjour'))
const dateDuJour = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
}).format(new Date())

const chargementInitial = computed(() => espaces.chargement && !espaces.charge)

const stats = computed(() => [
  {
    value: espaces.totalNonLus,
    label: espaces.totalNonLus > 1 ? 'messages non lus' : 'message non lu',
    icon: 'mdi-message-text-outline',
    background: '#E3EBFB',
    color: '#1E4FB8',
  },
  {
    value: espaces.invitations.length,
    label: espaces.invitations.length > 1 ? 'invitations en attente' : 'invitation en attente',
    icon: 'mdi-account-group-outline',
    background: '#DDF2EF',
    color: '#00695C',
  },
  {
    value: espaces.tous.length,
    label: espaces.tous.length > 1 ? 'espaces' : 'espace',
    icon: 'mdi-view-grid-outline',
    background: '#FEF3DC',
    color: '#8A4100',
  },
])

// Invitation en cours de traitement (bouton en chargement) et message d'erreur.
const enCours = ref(null)
const erreurInvitation = ref(null)

async function accepter(invitation) {
  enCours.value = invitation.id
  erreurInvitation.value = null
  try {
    const espace = await espaces.accepter(invitation.id)
    router.push({ name: 'espace', params: { id: espace?.id ?? invitation.espace.id } })
  } catch (error) {
    erreurInvitation.value = messageErreur(error, "L'invitation n'a pas pu être acceptée.")
    await espaces.charger({ discret: true })
  } finally {
    enCours.value = null
  }
}

async function refuser(invitation) {
  enCours.value = invitation.id
  erreurInvitation.value = null
  try {
    await espaces.refuser(invitation.id)
  } catch (error) {
    erreurInvitation.value = messageErreur(error, "L'invitation n'a pas pu être refusée.")
    await espaces.charger({ discret: true })
  } finally {
    enCours.value = null
  }
}

function invitePar(invitation) {
  const personne = invitation.invite_par
  if (!personne) return ''
  return `${personne.prenom} ${personne.nom} (${libelleProfil(personne.profil)})`
}
</script>

<template>
  <div class="home">
    <header class="home-head">
      <div class="home-head__text">
        <h1 class="text-h5 font-weight-medium">{{ salutation }}</h1>
        <p class="home-head__subtitle home-head__date">{{ dateDuJour }}</p>
      </div>
      <v-btn
        variant="outlined"
        color="primary"
        prepend-icon="mdi-compass-outline"
        to="/channels"
        height="40"
      >
        Explorer les channels
      </v-btn>
    </header>

    <v-alert v-if="espaces.erreur" type="error" variant="tonal" density="compact">
      {{ espaces.erreur }}
      <template #append>
        <v-btn variant="text" size="small" @click="espaces.charger()">Réessayer</v-btn>
      </template>
    </v-alert>

    <section class="home-stats" aria-label="Résumé">
      <v-card v-for="stat in stats" :key="stat.icon" class="stat-card" variant="outlined">
        <span class="stat-card__icon" :style="{ background: stat.background, color: stat.color }">
          <v-icon :icon="stat.icon" size="22" />
        </span>
        <div>
          <v-skeleton-loader v-if="chargementInitial" type="text" width="40" />
          <p v-else class="stat-card__value">{{ stat.value }}</p>
          <p class="stat-card__label">{{ stat.label }}</p>
        </div>
      </v-card>
    </section>

    <v-card variant="outlined" class="home-card">
      <v-card-title class="d-flex align-center ga-2">
        <v-icon icon="mdi-email-outline" color="primary" size="22" />
        <span class="text-h6 font-weight-medium">Invitations</span>
      </v-card-title>
      <v-card-text>
        <v-alert
          v-if="erreurInvitation"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-3"
          closable
          @click:close="erreurInvitation = null"
        >
          {{ erreurInvitation }}
        </v-alert>

        <v-skeleton-loader v-if="chargementInitial" type="list-item-two-line" />

        <div v-else-if="espaces.invitations.length === 0" class="home-empty">
          <v-icon icon="mdi-inbox-outline" size="32" />
          <p>Aucune invitation en attente.</p>
        </div>

        <ul v-else class="invitations">
          <li v-for="invitation in espaces.invitations" :key="invitation.id" class="invitation">
            <span class="invitation__icone">
              <v-icon icon="mdi-lock-outline" size="20" />
            </span>
            <div class="invitation__texte">
              <p class="invitation__nom">{{ invitation.espace?.nom }}</p>
              <p class="invitation__meta">
                Invité par {{ invitePar(invitation) }} · expire le
                {{ formaterDate(invitation.expire_le) }}
              </p>
            </div>
            <div class="invitation__actions">
              <v-btn variant="text" :disabled="enCours !== null" @click="refuser(invitation)">
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
      </v-card-text>
    </v-card>

    <v-card variant="outlined" class="home-card">
      <v-card-title class="d-flex align-center ga-2">
        <v-icon icon="mdi-view-grid-outline" color="primary" size="22" />
        <span class="text-h6 font-weight-medium">Mes espaces</span>
      </v-card-title>
      <v-card-text>
        <v-skeleton-loader v-if="chargementInitial" type="list-item" />

        <div v-else-if="espaces.estVide" class="home-empty">
          <v-icon icon="mdi-account-group-outline" size="32" />
          <p>Vous n'avez encore aucun espace.</p>
        </div>

        <v-list v-else density="comfortable" class="pa-0">
          <v-list-item
            v-for="espace in espaces.tous"
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
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.home {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.home-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.home-head__text {
  flex: 1;
  min-width: 0;
}

.home-head__subtitle {
  margin: 4px 0 0;
  color: rgb(var(--v-theme-on-surface-variant));
}

.home-head__date::first-letter {
  text-transform: uppercase;
}

.home-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
}

.stat-card__icon {
  display: grid;
  place-items: center;
  flex: none;
  width: 44px;
  height: 44px;
  border-radius: 10px;
}

.stat-card__value {
  margin: 0;
  font-size: 24px;
  line-height: 32px;
  font-weight: 500;
}

.stat-card__label {
  margin: 0;
  font-size: 12px;
  line-height: 16px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.home-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px 0;
  text-align: center;
  color: rgb(var(--v-theme-on-surface-variant));
}

.home-empty p {
  margin: 0;
}

.invitations {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.invitation {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.invitation__icone {
  display: grid;
  place-items: center;
  flex: none;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: rgb(var(--v-theme-primary-soft));
  color: rgb(var(--v-theme-primary));
}

.invitation__texte {
  flex: 1;
  min-width: 180px;
}

.invitation__nom {
  margin: 0;
  font-weight: 500;
  overflow-wrap: anywhere;
}

.invitation__meta {
  margin: 2px 0 0;
  font-size: 12px;
  color: rgb(var(--v-theme-on-surface-variant));
}

.invitation__actions {
  display: flex;
  gap: 8px;
}

@media (max-width: 900px) {
  .home-stats {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
