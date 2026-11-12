<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import {
  annulerInvitation,
  changerRole,
  inviter,
  quitterEspace,
  recupererInvitationsEspace,
  recupererMembres,
  retirerMembre,
  transfererPropriete,
} from '@/api/espaces'
import RecherchePersonnes from '@/components/espaces/RecherchePersonnes.vue'
import { useAuthStore } from '@/stores/auth'
import {
  actionsMembre,
  couleurAuteur,
  estGestionnaire,
  formaterDate,
  initialesAuteur,
  libelleProfil,
  libelleRole,
  messageErreur,
} from '@/utils/espaces'

/*
 * SCRUM-91 · E07 « Membres » : liste des membres, gestion (nommer co-gestionnaire,
 * retirer, transférer la propriété), invitations en attente, quitter l'espace.
 * Les règles sont appliquées par l'API (SCRUM-90) ; l'écran n'affiche que les
 * actions autorisées pour éviter des erreurs inutiles.
 */
const props = defineProps({
  espace: { type: Object, required: true },
})
const emit = defineEmits(['actualiser'])

const router = useRouter()
const auth = useAuthStore()

const sousOnglet = ref('membres')
const membres = ref([])
const invitations = ref([])
const chargement = ref(true)
const erreur = ref('')
const recherche = ref('')
const filtreProfil = ref('TOUS')

const dialogueInviter = ref(false)
const aInviter = ref([])
const envoiInvitations = ref(false)

const confirmation = ref({
  ouvert: false,
  titre: '',
  texte: '',
  bouton: '',
  couleur: 'error',
  action: null,
})
const actionEnCours = ref(false)
const snackbar = ref({ actif: false, texte: '' })

const monId = computed(() => auth.user?.id)
const gestionnaire = computed(() => estGestionnaire(props.espace.role))
const archive = computed(() => props.espace.etat === 'ARCHIVE')
const peutQuitter = computed(() => props.espace.type !== 'ORGANISATIONNEL')
const peutInviter = computed(
  () => gestionnaire.value && !archive.value && props.espace.type !== 'ORGANISATIONNEL',
)

const FILTRES = [
  { title: 'Tous les profils', value: 'TOUS' },
  { title: 'Étudiants', value: 'ETUDIANT' },
  { title: 'Enseignants', value: 'ENSEIGNANT' },
  { title: 'Administration', value: 'ADMINISTRATION' },
]

const LIBELLES_ACTIONS = {
  nommer: { titre: 'Nommer co-gestionnaire', icone: 'mdi-shield-account-outline' },
  retrograder: { titre: 'Repasser simple membre', icone: 'mdi-account-arrow-down-outline' },
  transferer: { titre: 'Transférer la propriété', icone: 'mdi-swap-horizontal' },
  retirer: { titre: "Retirer de l'espace", icone: 'mdi-account-remove-outline' },
}

const membresAffiches = computed(() => {
  const q = (recherche.value ?? '').trim().toLowerCase()

  return membres.value.filter((m) => {
    const nom = `${m.user.prenom} ${m.user.nom}`.toLowerCase()
    const profilOk = filtreProfil.value === 'TOUS' || m.user.profil === filtreProfil.value
    return profilOk && (!q || nom.includes(q))
  })
})

const idsExclus = computed(() => [
  ...membres.value.map((m) => m.user.id),
  ...invitations.value.map((i) => i.invite?.id),
])

onMounted(charger)

async function charger() {
  chargement.value = true
  erreur.value = ''

  try {
    const [listeMembres, listeInvitations] = await Promise.all([
      recupererMembres(props.espace.id),
      // Les invitations en attente ne sont visibles que des gestionnaires (sinon 403).
      gestionnaire.value ? recupererInvitationsEspace(props.espace.id) : Promise.resolve([]),
    ])
    membres.value = listeMembres
    invitations.value = listeInvitations
  } catch (error) {
    erreur.value = messageErreur(error, "La liste des membres n'a pas pu être chargée.")
  } finally {
    chargement.value = false
  }
}

function afficher(texte) {
  snackbar.value = { actif: true, texte }
}

function nomDe(personne) {
  return personne ? `${personne.prenom} ${personne.nom}` : 'Membre supprimé'
}

function demanderConfirmation(options) {
  confirmation.value = { ouvert: true, couleur: 'error', ...options }
}

async function confirmer() {
  actionEnCours.value = true

  try {
    await confirmation.value.action()
  } catch (error) {
    afficher(messageErreur(error))
  } finally {
    confirmation.value.ouvert = false
    actionEnCours.value = false
  }
}

async function executer(action, membre) {
  const nom = nomDe(membre.user)

  if (action === 'nommer' || action === 'retrograder') {
    const role = action === 'nommer' ? 'CO_GESTIONNAIRE' : 'MEMBRE'
    try {
      await changerRole(props.espace.id, membre.user.id, role)
      afficher(`${nom} est maintenant ${libelleRole(role).toLowerCase()}.`)
      await charger()
    } catch (error) {
      afficher(messageErreur(error))
    }
    return
  }

  if (action === 'transferer') {
    demanderConfirmation({
      titre: `Transférer la propriété à ${nom} ?`,
      texte:
        'Vous deviendrez co-gestionnaire. Seul le nouveau propriétaire pourra vous rendre la propriété.',
      bouton: 'Transférer',
      couleur: 'primary',
      action: async () => {
        await transfererPropriete(props.espace.id, membre.user.id)
        afficher(`${nom} est le nouveau propriétaire de l'espace.`)
        // Mon rôle a changé : l'en-tête de l'espace (et donc mes actions) est rechargé.
        emit('actualiser')
        await charger()
      },
    })
    return
  }

  if (action === 'retirer') {
    demanderConfirmation({
      titre: `Retirer ${nom} de l'espace ?`,
      texte:
        "Cette personne perd immédiatement l'accès aux messages et aux fichiers, y compris par lien direct. Ses messages déjà envoyés restent visibles.",
      bouton: 'Retirer',
      action: async () => {
        await retirerMembre(props.espace.id, membre.user.id)
        afficher(`${nom} a été retiré de l'espace.`)
        // Le nombre de membres de l'en-tête change.
        emit('actualiser')
        await charger()
      },
    })
  }
}

function demanderAnnulation(invitation) {
  demanderConfirmation({
    titre: "Annuler l'invitation ?",
    texte: `${nomDe(invitation.invite)} ne pourra plus rejoindre l'espace avec cette invitation.`,
    bouton: "Annuler l'invitation",
    action: async () => {
      await annulerInvitation(invitation.id)
      afficher('Invitation annulée.')
      await charger()
    },
  })
}

function demanderQuitter() {
  const proprietaire = props.espace.role === 'PROPRIETAIRE'

  demanderConfirmation({
    titre: `Quitter « ${props.espace.nom} » ?`,
    texte:
      "Vous n'aurez plus accès aux messages, fichiers et ressources de cet espace. Vos messages déjà envoyés restent visibles pour les autres membres. Pour revenir, il faudra une nouvelle invitation d'un gestionnaire." +
      (proprietaire
        ? " Comme vous êtes propriétaire, la propriété passera au plus ancien co-gestionnaire, sinon à l'administration."
        : ''),
    bouton: "Quitter l'espace",
    action: async () => {
      await quitterEspace(props.espace.id)
      router.push({ name: 'home' })
    },
  })
}

function ouvrirInvitation() {
  aInviter.value = []
  dialogueInviter.value = true
}

function resumeInvitations(resultat) {
  const n = resultat.creees
  const deja = resultat.deja_membres.length + resultat.deja_invites.length
  const envoyees = `${n} invitation${n > 1 ? 's' : ''} envoyée${n > 1 ? 's' : ''}`
  return deja
    ? `${envoyees} (${deja} personne${deja > 1 ? 's' : ''} déjà membre ou invitée).`
    : `${envoyees}.`
}

async function envoyerInvitations() {
  envoiInvitations.value = true

  try {
    const resultat = await inviter(
      props.espace.id,
      aInviter.value.map((p) => p.id),
    )
    afficher(resumeInvitations(resultat))
    dialogueInviter.value = false
    sousOnglet.value = 'invitations'
    await charger()
  } catch (error) {
    afficher(messageErreur(error, "Les invitations n'ont pas pu être envoyées."))
  } finally {
    envoiInvitations.value = false
  }
}
</script>

<template>
  <div class="membres">
    <div class="membres__barre">
      <v-tabs v-model="sousOnglet" density="compact" color="primary">
        <v-tab value="membres" class="text-none">
          Membres
          <v-chip size="x-small" class="ml-2" variant="tonal">{{ membres.length }}</v-chip>
        </v-tab>
        <v-tab v-if="gestionnaire" value="invitations" class="text-none">
          Invitations en attente
          <v-chip size="x-small" class="ml-2" variant="tonal">{{ invitations.length }}</v-chip>
        </v-tab>
        <v-tab v-if="gestionnaire" value="demandes" class="text-none">Demandes</v-tab>
      </v-tabs>

      <v-spacer />

      <v-btn
        v-if="peutInviter"
        color="primary"
        prepend-icon="mdi-account-plus-outline"
        class="text-none"
        @click="ouvrirInvitation"
      >
        Inviter
      </v-btn>
      <v-btn
        v-if="peutQuitter"
        variant="text"
        color="error"
        prepend-icon="mdi-logout"
        class="text-none"
        @click="demanderQuitter"
      >
        Quitter l'espace
      </v-btn>
      <span v-else class="text-caption text-medium-emphasis">
        Groupe de classe : impossible de le quitter
      </span>
    </div>

    <v-alert v-if="erreur" type="error" variant="tonal" density="compact">
      {{ erreur }}
      <template #append>
        <v-btn variant="text" size="small" @click="charger">Réessayer</v-btn>
      </template>
    </v-alert>

    <v-skeleton-loader v-else-if="chargement" type="list-item-avatar-two-line@4" />

    <v-window v-else v-model="sousOnglet">
      <!-- Membres -->
      <v-window-item value="membres">
        <div class="membres__filtres">
          <v-text-field
            v-model="recherche"
            label="Rechercher un membre"
            prepend-inner-icon="mdi-magnify"
            density="compact"
            hide-details
            clearable
            class="membres__recherche"
          />
          <v-select
            v-model="filtreProfil"
            :items="FILTRES"
            label="Profil"
            density="compact"
            hide-details
            class="membres__filtre-profil"
          />
        </div>

        <v-list v-if="membresAffiches.length" lines="two" class="membres__liste">
          <v-list-item v-for="membre in membresAffiches" :key="membre.user.id">
            <template #prepend>
              <v-avatar :color="couleurAuteur(membre.user)" size="40">
                <span class="text-white">{{ initialesAuteur(membre.user) }}</span>
              </v-avatar>
            </template>

            <v-list-item-title>
              {{ nomDe(membre.user) }}
              <span v-if="membre.user.id === monId" class="text-medium-emphasis">(vous)</span>
            </v-list-item-title>
            <v-list-item-subtitle>
              {{ libelleProfil(membre.user.profil) }} · membre depuis le
              {{ formaterDate(membre.depuis) }}
            </v-list-item-subtitle>

            <template #append>
              <div class="membres__actions">
                <v-chip
                  size="small"
                  label
                  variant="tonal"
                  :color="membre.role === 'MEMBRE' ? undefined : 'primary'"
                >
                  {{ libelleRole(membre.role) }}
                </v-chip>

                <v-menu v-if="!archive && actionsMembre(espace.role, membre, monId).length">
                  <template #activator="{ props: menuProps }">
                    <v-btn
                      v-bind="menuProps"
                      icon="mdi-dots-vertical"
                      variant="text"
                      size="small"
                      :aria-label="`Actions pour ${nomDe(membre.user)}`"
                    />
                  </template>
                  <v-list density="compact">
                    <v-list-item
                      v-for="action in actionsMembre(espace.role, membre, monId)"
                      :key="action"
                      :prepend-icon="LIBELLES_ACTIONS[action].icone"
                      :title="LIBELLES_ACTIONS[action].titre"
                      :class="{ 'text-error': action === 'retirer' }"
                      @click="executer(action, membre)"
                    />
                  </v-list>
                </v-menu>
              </div>
            </template>
          </v-list-item>
        </v-list>
        <p v-else class="membres__vide">Aucun membre ne correspond à votre recherche.</p>

        <p class="text-caption text-medium-emphasis">
          Un membre retiré perd immédiatement l'accès aux messages et fichiers, y compris par lien
          direct.
          <template v-if="espace.type === 'ORGANISATIONNEL'">
            Les étudiants de la classe sont ajoutés automatiquement.
          </template>
        </p>
      </v-window-item>

      <!-- Invitations en attente (gestionnaires) -->
      <v-window-item value="invitations">
        <v-list v-if="invitations.length" lines="two" class="membres__liste">
          <v-list-item v-for="invitation in invitations" :key="invitation.id">
            <template #prepend>
              <v-avatar :color="couleurAuteur(invitation.invite)" size="40">
                <span class="text-white">{{ initialesAuteur(invitation.invite) }}</span>
              </v-avatar>
            </template>
            <v-list-item-title>{{ nomDe(invitation.invite) }}</v-list-item-title>
            <v-list-item-subtitle>
              Invitée par {{ nomDe(invitation.invite_par) }} le
              {{ formaterDate(invitation.cree_le) }} · expire le
              {{ formaterDate(invitation.expire_le) }}
            </v-list-item-subtitle>
            <template #append>
              <v-btn
                variant="text"
                color="error"
                size="small"
                class="text-none"
                @click="demanderAnnulation(invitation)"
              >
                Annuler
              </v-btn>
            </template>
          </v-list-item>
        </v-list>
        <p v-else class="membres__vide">Aucune invitation en attente.</p>
      </v-window-item>

      <!-- Demandes d'adhésion : channels « sur demande », v1.1 -->
      <v-window-item value="demandes">
        <p class="membres__vide">
          Aucune demande d'adhésion. Les demandes concernent uniquement les channels en mode « sur
          demande ».
        </p>
      </v-window-item>
    </v-window>

    <!-- Dialogue « Inviter des membres » -->
    <v-dialog v-model="dialogueInviter" max-width="560">
      <v-card title="Inviter des membres">
        <v-card-text>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Les personnes invitées reçoivent une invitation valable 14 jours.
          </p>
          <RecherchePersonnes v-model="aInviter" :exclure="idsExclus" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" class="text-none" @click="dialogueInviter = false">Annuler</v-btn>
          <v-btn
            color="primary"
            class="text-none"
            :disabled="!aInviter.length"
            :loading="envoiInvitations"
            @click="envoyerInvitations"
          >
            Envoyer les invitations
            <template v-if="aInviter.length">({{ aInviter.length }})</template>
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirmation des actions destructives -->
    <v-dialog v-model="confirmation.ouvert" max-width="460">
      <v-card :title="confirmation.titre">
        <v-card-text>{{ confirmation.texte }}</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn
            variant="text"
            class="text-none"
            :disabled="actionEnCours"
            @click="confirmation.ouvert = false"
          >
            Annuler
          </v-btn>
          <v-btn
            :color="confirmation.couleur"
            variant="flat"
            class="text-none"
            :loading="actionEnCours"
            @click="confirmer"
          >
            {{ confirmation.bouton }}
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
.membres {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.membres__barre {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.membres__filtres {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin: 8px 0;
}

.membres__recherche {
  flex: 1 1 240px;
}

.membres__filtre-profil {
  flex: 0 1 220px;
}

.membres__liste {
  background: transparent;
}

.membres__actions {
  display: flex;
  align-items: center;
  gap: 4px;
}

.membres__vide {
  padding: 24px 0;
  text-align: center;
  color: rgb(var(--v-theme-on-surface-variant));
}
</style>
