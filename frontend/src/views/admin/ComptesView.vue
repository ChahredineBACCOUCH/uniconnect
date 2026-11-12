<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'

import {
  analyserImport,
  creerCompte,
  desactiverCompte,
  modifierCompte,
  recupererClasses,
  recupererComptes,
  supprimerCompte,
  telechargerModele,
  validerImport,
} from '@/api/comptes'
import { messageErreur } from '@/utils/espaces'
import { ETATS, PROFILS, detailEtat, detailProfil, resumerImport } from '@/utils/comptes'

/*
 * SCRUM-77 — A03 « Comptes autorisés ».
 *
 * Liste paginée côté serveur (GET /admin/comptes-autorises), filtres et
 * recherche différée de 300 ms. L'import Excel se fait en deux temps :
 * aperçu sans écriture puis validation des lignes valides.
 */
const { xs } = useDisplay()

const chargement = ref(false)
const erreurChargement = ref('')
const comptes = ref([])
const total = ref(0)
const page = ref(1)
const parPage = ref(10)
const optionsParPage = [10, 20, 50]

const recherche = ref('')
const rechercheAppliquee = ref('')
let minuterieRecherche = null

const profil = ref(null)
const classeId = ref(null)
const etat = ref(null)

const classes = ref([])
const classesErreur = ref(false)

const optionsClasses = computed(() =>
  classes.value.map((classe) => ({
    title: `${classe.code} — ${classe.libelle}`,
    value: classe.id,
  })),
)

watch(recherche, (valeur) => {
  clearTimeout(minuterieRecherche)
  minuterieRecherche = setTimeout(() => {
    rechercheAppliquee.value = valeur.trim()
  }, 300)
})

onBeforeUnmount(() => clearTimeout(minuterieRecherche))

/*
 * Une seule requête à la fois : un numéro de requête permet d'ignorer une
 * réponse devenue obsolète si l'utilisateur change vite de filtre.
 */
let numeroRequete = 0

async function charger() {
  const numero = ++numeroRequete
  chargement.value = true
  erreurChargement.value = ''

  try {
    const reponse = await recupererComptes({
      page: page.value,
      par_page: parPage.value,
      q: rechercheAppliquee.value || undefined,
      profil: profil.value || undefined,
      classe_id: classeId.value || undefined,
      etat: etat.value || undefined,
    })

    if (numero !== numeroRequete) return

    comptes.value = reponse.data ?? []
    total.value = reponse.meta?.total ?? 0
  } catch (error) {
    if (numero !== numeroRequete) return

    erreurChargement.value = messageErreur(
      error,
      'Les comptes autorisés n’ont pas pu être chargés.',
    )
    comptes.value = []
    total.value = 0
  } finally {
    if (numero === numeroRequete) chargement.value = false
  }
}

// Filtres : retour en page 1 (le watcher de page déclenche alors le chargement).
watch([rechercheAppliquee, profil, classeId, etat], () => {
  if (page.value !== 1) page.value = 1
  else charger()
})

watch([page, parPage], charger)

async function chargerClasses() {
  classesErreur.value = false

  try {
    classes.value = await recupererClasses()
  } catch {
    classes.value = []
    classesErreur.value = true
  }
}

charger()
chargerClasses()

/* ------------------------------------------------------------------ */
/* Notifications                                                       */
/* ------------------------------------------------------------------ */

const snackbar = ref({ visible: false, message: '', color: 'success' })

function notifier(message, color = 'success') {
  snackbar.value = { visible: true, message, color }
}

/* ------------------------------------------------------------------ */
/* Téléchargement du modèle                                            */
/* ------------------------------------------------------------------ */

const telechargement = ref(false)

async function telechargerLeModele() {
  telechargement.value = true

  try {
    await telechargerModele()
    notifier('Le modèle Excel a été téléchargé.')
  } catch (error) {
    notifier(messageErreur(error, 'Le modèle d’import n’a pas pu être téléchargé.'), 'error')
  } finally {
    telechargement.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Dialogue d'ajout / modification                                     */
/* ------------------------------------------------------------------ */

const dialogue = ref(false)
const compteEdite = ref(null)
const formulaire = ref(null)
const enregistrement = ref(false)
const erreurFormulaire = ref('')
const erreursChamps = ref({})

const formulaireVide = () => ({
  matricule: '',
  nom: '',
  prenom: '',
  email: '',
  profil: 'ETUDIANT',
  classe_id: null,
})

const champs = ref(formulaireVide())

const estModification = computed(() => compteEdite.value !== null)
const titreDialogue = computed(() =>
  estModification.value ? 'Modifier le compte autorisé' : 'Ajouter un compte autorisé',
)

function ouvrirAjout() {
  compteEdite.value = null
  champs.value = formulaireVide()
  erreurFormulaire.value = ''
  erreursChamps.value = {}
  dialogue.value = true
}

function ouvrirModification(compte) {
  compteEdite.value = compte
  champs.value = {
    matricule: compte.matricule ?? '',
    nom: compte.nom ?? '',
    prenom: compte.prenom ?? '',
    email: compte.email ?? '',
    profil: compte.profil ?? 'ETUDIANT',
    classe_id: compte.classe?.id ?? null,
  }
  erreurFormulaire.value = ''
  erreursChamps.value = {}
  dialogue.value = true
}

// La classe ne concerne que les étudiants.
watch(
  () => champs.value.profil,
  (profilChoisi) => {
    if (profilChoisi !== 'ETUDIANT') champs.value.classe_id = null
  },
)

const obligatoire = (libelle) => (valeur) =>
  Boolean(String(valeur ?? '').trim()) || `${libelle} est obligatoire.`

const longueurMax = (maximum, libelle) => (valeur) =>
  String(valeur ?? '').length <= maximum || `${libelle} ne peut pas dépasser ${maximum} caractères.`

const regleEmail = (valeur) => {
  const email = String(valeur ?? '').trim()
  if (!email) return "L'adresse e-mail est obligatoire."
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || "L'adresse e-mail est invalide."
}

const regleClasse = (valeur) => Boolean(valeur) || 'La classe est obligatoire pour un étudiant.'

async function enregistrer() {
  const { valid } = await formulaire.value.validate()
  if (!valid) return

  enregistrement.value = true
  erreurFormulaire.value = ''
  erreursChamps.value = {}

  const payload = {
    matricule: champs.value.matricule.trim(),
    nom: champs.value.nom.trim(),
    prenom: champs.value.prenom.trim(),
    email: champs.value.email.trim(),
    profil: champs.value.profil,
    classe_id: champs.value.profil === 'ETUDIANT' ? champs.value.classe_id : null,
  }

  const modification = estModification.value

  try {
    if (modification) {
      await modifierCompte(compteEdite.value.id, payload)
    } else {
      await creerCompte(payload)
    }

    dialogue.value = false
    notifier(modification ? 'Compte autorisé modifié.' : 'Compte autorisé ajouté.')
    await charger()
  } catch (error) {
    erreursChamps.value = error.errors ?? {}
    erreurFormulaire.value = messageErreur(error, "L'enregistrement a échoué.")
  } finally {
    enregistrement.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Désactivation / suppression                                         */
/* ------------------------------------------------------------------ */

const confirmation = ref(null)
const traitementAction = ref(false)

const titreConfirmation = computed(() =>
  confirmation.value?.action === 'supprimer'
    ? 'Supprimer ce compte autorisé ?'
    : 'Désactiver ce compte autorisé ?',
)

const texteConfirmation = computed(() => {
  if (!confirmation.value) return ''

  const personne = `${confirmation.value.compte.prenom} ${confirmation.value.compte.nom}`

  return confirmation.value.action === 'supprimer'
    ? `L'autorisation de ${personne} sera supprimée définitivement. Cette action est irréversible.`
    : `L'autorisation de ${personne} ne pourra plus servir à créer un compte. Vous pourrez la réactiver en la modifiant.`
})

function demanderAction(action, compte) {
  confirmation.value = { action, compte }
}

async function confirmerAction() {
  if (!confirmation.value) return

  const { action, compte } = confirmation.value
  traitementAction.value = true

  try {
    if (action === 'supprimer') {
      await supprimerCompte(compte.id)
      notifier('Compte autorisé supprimé.')
      confirmation.value = null

      // Dernière ligne d'une page : revenir à la page précédente.
      if (comptes.value.length === 1 && page.value > 1) page.value -= 1
      else await charger()
    } else {
      await desactiverCompte(compte.id)
      notifier('Compte autorisé désactivé.')
      confirmation.value = null
      await charger()
    }
  } catch (error) {
    confirmation.value = null
    notifier(messageErreur(error, "L'action n'a pas pu être effectuée."), 'error')
  } finally {
    traitementAction.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Import Excel en deux étapes                                         */
/* ------------------------------------------------------------------ */

const importDialogue = ref(false)
const etapeImport = ref(1)
const etapesImport = ['Fichier', 'Validation']
const fichier = ref(null)
const apercu = ref(null)
const analyseEnCours = ref(false)
const importEnCours = ref(false)
const erreurImport = ref('')

const fichierChoisi = computed(() =>
  Array.isArray(fichier.value) ? (fichier.value[0] ?? null) : fichier.value,
)
const nomFichier = computed(() => fichierChoisi.value?.name ?? null)
const resume = computed(() => resumerImport(apercu.value))
const peutAnalyser = computed(() => Boolean(fichierChoisi.value) && !analyseEnCours.value)
const peutContinuer = computed(() => Boolean(apercu.value) && resume.value.valides > 0)

watch(fichierChoisi, () => {
  apercu.value = null
  erreurImport.value = ''
  etapeImport.value = 1
})

function ouvrirImport() {
  reinitialiserImport()
  importDialogue.value = true
}

function reinitialiserImport() {
  etapeImport.value = 1
  fichier.value = null
  apercu.value = null
  analyseEnCours.value = false
  importEnCours.value = false
  erreurImport.value = ''
}

function fermerImport() {
  if (importEnCours.value) return
  importDialogue.value = false
  reinitialiserImport()
}

async function analyserLeFichier() {
  if (!fichierChoisi.value) return

  analyseEnCours.value = true
  erreurImport.value = ''
  apercu.value = null

  try {
    apercu.value = await analyserImport(fichierChoisi.value)
  } catch (error) {
    erreurImport.value = messageErreur(error, "Le fichier n'a pas pu être analysé.")
  } finally {
    analyseEnCours.value = false
  }
}

async function lancerImport() {
  if (!peutContinuer.value) return

  importEnCours.value = true
  erreurImport.value = ''

  try {
    const resultat = await validerImport(apercu.value.lignes, nomFichier.value)
    const importes = resultat.importes ?? 0
    const ignores = resultat.ignores ?? 0

    let message = `${importes} ${importes > 1 ? 'comptes importés' : 'compte importé'}.`
    if (ignores > 0) {
      message += ` ${ignores} ${ignores > 1 ? 'lignes ignorées' : 'ligne ignorée'}.`
    }

    importDialogue.value = false
    reinitialiserImport()
    notifier(message, ignores > 0 ? 'warning' : 'success')

    if (page.value === 1) await charger()
    else page.value = 1
  } catch (error) {
    erreurImport.value = messageErreur(error, "L'import n'a pas pu être réalisé.")
  } finally {
    importEnCours.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Tableau                                                             */
/* ------------------------------------------------------------------ */

const entetes = [
  { title: 'Matricule', key: 'matricule', sortable: false },
  { title: 'Nom', key: 'nom', sortable: false },
  { title: 'Prénom', key: 'prenom', sortable: false },
  { title: 'E-mail', key: 'email', sortable: false },
  { title: 'Profil', key: 'profil', sortable: false },
  { title: 'Classe', key: 'classe', sortable: false },
  { title: 'État du compte', key: 'etat', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 110 },
]

function puceProfil(valeur) {
  const detail = detailProfil(valeur)
  return { color: detail.couleur ?? undefined, variant: 'tonal', size: 'small' }
}

function puceEtat(valeur) {
  const detail = detailEtat(valeur)
  return { color: detail.couleur ?? undefined, variant: 'tonal', size: 'small' }
}

const libelleClasse = (compte) => compte.classe?.libelle ?? '—'
const nombrePages = computed(() => Math.max(Math.ceil(total.value / parPage.value), 1))
</script>

<template>
  <div class="comptes">
    <header class="comptes__entete">
      <div class="comptes__titre">
        <h1 class="text-h5 font-weight-medium">Comptes autorisés</h1>
        <p class="text-body-2 text-medium-emphasis comptes__sous-titre">
          Personnes autorisées à créer un compte
          <template v-if="!chargement || total > 0">
            · {{ total }} entrée{{ total > 1 ? 's' : '' }}
          </template>
        </p>
      </div>

      <div class="comptes__actions">
        <v-btn
          variant="text"
          prepend-icon="mdi-download"
          :loading="telechargement"
          @click="telechargerLeModele"
        >
          Modèle d'import
        </v-btn>
        <v-btn variant="outlined" color="primary" prepend-icon="mdi-upload" @click="ouvrirImport">
          Importer
        </v-btn>
        <v-btn color="primary" prepend-icon="mdi-account-plus" @click="ouvrirAjout">
          Ajouter
        </v-btn>
      </div>
    </header>

    <v-alert v-if="erreurChargement" type="error" variant="tonal" density="compact">
      {{ erreurChargement }}
      <template #append>
        <v-btn variant="text" size="small" @click="charger">Réessayer</v-btn>
      </template>
    </v-alert>

    <v-alert v-if="classesErreur" type="warning" variant="tonal" density="compact">
      Les classes de l'année active n'ont pas pu être chargées : le filtre et la création d'un
      étudiant sont indisponibles.
      <template #append>
        <v-btn variant="text" size="small" @click="chargerClasses">Réessayer</v-btn>
      </template>
    </v-alert>

    <v-card variant="outlined">
      <div class="comptes__filtres">
        <v-text-field
          v-model="recherche"
          class="comptes__recherche"
          label="Rechercher"
          placeholder="Nom ou matricule"
          prepend-inner-icon="mdi-magnify"
          density="comfortable"
          clearable
          hide-details
        />

        <v-select
          v-model="profil"
          :items="PROFILS"
          item-title="title"
          item-value="value"
          label="Profil"
          density="comfortable"
          clearable
          hide-details
        />

        <v-select
          v-model="classeId"
          :items="optionsClasses"
          item-title="title"
          item-value="value"
          label="Classe"
          density="comfortable"
          clearable
          hide-details
          :disabled="classesErreur"
          :placeholder="classesErreur ? 'Indisponible' : undefined"
        />

        <v-select
          v-model="etat"
          :items="ETATS"
          item-title="title"
          item-value="value"
          label="État du compte"
          density="comfortable"
          clearable
          hide-details
        />
      </div>

      <!-- Ordinateur : tableau paginé côté serveur. -->
      <v-data-table-server
        v-if="!xs"
        v-model:page="page"
        v-model:items-per-page="parPage"
        :items="comptes"
        :items-length="total"
        :headers="entetes"
        :items-per-page-options="optionsParPage"
        :loading="chargement"
        item-value="id"
        density="comfortable"
        hover
        no-data-text="Aucun compte autorisé ne correspond à ces filtres."
        class="comptes__table"
      >
        <template #[`item.matricule`]="{ item }">
          <span class="comptes__matricule">{{ item.matricule }}</span>
        </template>

        <template #[`item.nom`]="{ item }">
          <span class="font-weight-medium">{{ item.nom }}</span>
        </template>

        <template #[`item.email`]="{ item }">
          <span class="text-medium-emphasis">{{ item.email }}</span>
        </template>

        <template #[`item.profil`]="{ item }">
          <v-chip v-bind="puceProfil(item.profil)">
            {{ detailProfil(item.profil).libelle }}
          </v-chip>
        </template>

        <template #[`item.classe`]="{ item }">
          {{ libelleClasse(item) }}
        </template>

        <template #[`item.etat`]="{ item }">
          <v-chip v-bind="puceEtat(item.etat)">
            {{ detailEtat(item.etat).libelle }}
          </v-chip>
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn
            icon
            variant="text"
            size="small"
            aria-label="Modifier"
            @click="ouvrirModification(item)"
          >
            <v-icon icon="mdi-pencil" size="20" />
          </v-btn>

          <v-menu location="bottom end">
            <template #activator="{ props }">
              <v-btn v-bind="props" icon variant="text" size="small" aria-label="Plus d'actions">
                <v-icon icon="mdi-dots-vertical" size="20" />
              </v-btn>
            </template>
            <v-list density="compact">
              <v-list-item
                prepend-icon="mdi-pencil-outline"
                title="Modifier"
                @click="ouvrirModification(item)"
              />
              <v-list-item
                v-if="item.etat !== 'DESACTIVE'"
                prepend-icon="mdi-account-cancel-outline"
                title="Désactiver"
                @click="demanderAction('desactiver', item)"
              />
              <v-list-item
                prepend-icon="mdi-delete-outline"
                title="Supprimer"
                base-color="error"
                @click="demanderAction('supprimer', item)"
              />
            </v-list>
          </v-menu>
        </template>
      </v-data-table-server>

      <!-- Téléphone : cartes plutôt qu'un tableau à défilement horizontal. -->
      <div v-else class="comptes__cartes">
        <v-skeleton-loader
          v-if="chargement && comptes.length === 0"
          type="card"
          class="comptes__squelette"
        />

        <div v-else-if="comptes.length === 0" class="comptes__vide">
          <v-icon icon="mdi-account-search-outline" size="32" />
          <p>Aucun compte autorisé ne correspond à ces filtres.</p>
        </div>

        <template v-else>
          <v-card
            v-for="compte in comptes"
            :key="compte.id"
            variant="outlined"
            class="compte-carte"
          >
            <v-card-item>
              <v-card-title class="text-subtitle-1">
                {{ compte.prenom }} {{ compte.nom }}
              </v-card-title>
              <v-card-subtitle> {{ compte.matricule }} · {{ compte.email }} </v-card-subtitle>
              <template #append>
                <v-chip v-bind="puceEtat(compte.etat)">
                  {{ detailEtat(compte.etat).libelle }}
                </v-chip>
              </template>
            </v-card-item>

            <v-card-text class="compte-carte__details">
              <v-chip v-bind="puceProfil(compte.profil)">
                {{ detailProfil(compte.profil).libelle }}
              </v-chip>
              <span>{{ libelleClasse(compte) }}</span>
            </v-card-text>

            <v-card-actions>
              <v-btn
                variant="text"
                prepend-icon="mdi-pencil"
                aria-label="Modifier"
                @click="ouvrirModification(compte)"
              >
                Modifier
              </v-btn>
              <v-spacer />
              <v-menu location="bottom end">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon
                    variant="text"
                    size="small"
                    aria-label="Plus d'actions"
                  >
                    <v-icon icon="mdi-dots-vertical" size="20" />
                  </v-btn>
                </template>
                <v-list density="compact">
                  <v-list-item
                    v-if="compte.etat !== 'DESACTIVE'"
                    prepend-icon="mdi-account-cancel-outline"
                    title="Désactiver"
                    @click="demanderAction('desactiver', compte)"
                  />
                  <v-list-item
                    prepend-icon="mdi-delete-outline"
                    title="Supprimer"
                    base-color="error"
                    @click="demanderAction('supprimer', compte)"
                  />
                </v-list>
              </v-menu>
            </v-card-actions>
          </v-card>

          <v-pagination
            v-if="nombrePages > 1"
            v-model="page"
            :length="nombrePages"
            :total-visible="5"
            density="comfortable"
            class="comptes__pagination"
          />
        </template>
      </div>
    </v-card>

    <!-- Dialogue d'ajout / modification -->
    <v-dialog v-model="dialogue" max-width="640" persistent>
      <v-card>
        <v-card-title class="text-h6 font-weight-medium">{{ titreDialogue }}</v-card-title>

        <v-card-text>
          <v-alert
            v-if="erreurFormulaire"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ erreurFormulaire }}
          </v-alert>

          <v-form ref="formulaire" @submit.prevent="enregistrer">
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="champs.matricule"
                  label="Matricule"
                  counter="30"
                  :rules="[obligatoire('Le matricule'), longueurMax(30, 'Le matricule')]"
                  :error-messages="erreursChamps.matricule"
                  autofocus
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="champs.email"
                  label="E-mail institutionnel"
                  type="email"
                  counter="190"
                  :rules="[regleEmail, longueurMax(190, `L'adresse e-mail`)]"
                  :error-messages="erreursChamps.email"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="champs.nom"
                  label="Nom"
                  counter="80"
                  :rules="[obligatoire('Le nom'), longueurMax(80, 'Le nom')]"
                  :error-messages="erreursChamps.nom"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="champs.prenom"
                  label="Prénom"
                  counter="80"
                  :rules="[obligatoire('Le prénom'), longueurMax(80, 'Le prénom')]"
                  :error-messages="erreursChamps.prenom"
                />
              </v-col>
            </v-row>

            <v-select
              v-model="champs.profil"
              :items="PROFILS"
              item-title="title"
              item-value="value"
              label="Profil"
              :rules="[(valeur) => Boolean(valeur) || 'Le profil est obligatoire.']"
              :error-messages="erreursChamps.profil"
            />

            <v-select
              v-if="champs.profil === 'ETUDIANT'"
              v-model="champs.classe_id"
              :items="optionsClasses"
              item-title="title"
              item-value="value"
              label="Classe"
              :disabled="classesErreur"
              :rules="[regleClasse]"
              :error-messages="erreursChamps.classe_id"
            />
          </v-form>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="enregistrement" @click="dialogue = false">
            Annuler
          </v-btn>
          <v-btn color="primary" :loading="enregistrement" @click="enregistrer">
            {{ estModification ? 'Enregistrer' : 'Ajouter' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirmation d'une action destructive -->
    <v-dialog
      :model-value="confirmation !== null"
      max-width="480"
      @update:model-value="(ouverte) => !ouverte && (confirmation = null)"
    >
      <v-card v-if="confirmation">
        <v-card-title class="text-h6 font-weight-medium">{{ titreConfirmation }}</v-card-title>
        <v-card-text>{{ texteConfirmation }}</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="traitementAction" @click="confirmation = null">
            Annuler
          </v-btn>
          <v-btn
            :color="confirmation.action === 'supprimer' ? 'error' : 'primary'"
            :loading="traitementAction"
            @click="confirmerAction"
          >
            {{ confirmation.action === 'supprimer' ? 'Supprimer' : 'Désactiver' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import Excel en deux étapes -->
    <v-dialog v-model="importDialogue" max-width="720" persistent>
      <v-card>
        <v-card-title class="text-h6 font-weight-medium">
          Importer des comptes autorisés
        </v-card-title>

        <v-stepper v-model="etapeImport" :items="etapesImport" :mobile="xs" hide-actions flat>
          <template #[`item.1`]>
            <h2 class="text-subtitle-1 font-weight-medium">Choisir le fichier</h2>
            <p class="text-body-2 text-medium-emphasis mb-3">
              Fichier Excel (<code>.xlsx</code> ou <code>.xls</code>) avec les colonnes matricule,
              nom, prénom, e-mail, profil et classe.
            </p>

            <v-file-input
              v-model="fichier"
              label="Fichier Excel"
              accept=".xlsx,.xls"
              prepend-icon="mdi-file-excel-outline"
              show-size
              :disabled="analyseEnCours || importEnCours"
            />

            <div class="comptes__import-outils">
              <v-btn
                variant="text"
                prepend-icon="mdi-download"
                :loading="telechargement"
                @click="telechargerLeModele"
              >
                Télécharger le modèle
              </v-btn>
              <v-btn
                variant="outlined"
                color="primary"
                :disabled="!peutAnalyser"
                :loading="analyseEnCours"
                @click="analyserLeFichier"
              >
                Analyser le fichier
              </v-btn>
            </div>

            <v-alert
              v-if="erreurImport"
              type="error"
              variant="tonal"
              density="compact"
              class="mt-3"
            >
              {{ erreurImport }}
            </v-alert>

            <template v-if="apercu">
              <div class="comptes__compteurs">
                <div class="compteur compteur--valides">
                  <strong>{{ resume.valides }}</strong>
                  <span
                    >ligne{{ resume.valides > 1 ? 's' : '' }} valide{{
                      resume.valides > 1 ? 's' : ''
                    }}</span
                  >
                </div>
                <div class="compteur compteur--doublons">
                  <strong>{{ resume.doublons }}</strong>
                  <span
                    >doublon{{ resume.doublons > 1 ? 's' : '' }} ignoré{{
                      resume.doublons > 1 ? 's' : ''
                    }}</span
                  >
                </div>
                <div class="compteur compteur--erreurs">
                  <strong>{{ resume.erreurs }}</strong>
                  <span>ligne{{ resume.erreurs > 1 ? 's' : '' }} en erreur</span>
                </div>
              </div>

              <div v-if="apercu.erreurs?.length" class="comptes__erreurs">
                <h3 class="text-subtitle-2 font-weight-medium">Détail des erreurs</h3>
                <ul>
                  <li v-for="erreur in apercu.erreurs" :key="`${erreur.ligne}-${erreur.motif}`">
                    Ligne {{ erreur.ligne }} : {{ erreur.motif }}
                  </li>
                </ul>
              </div>

              <v-alert v-else type="success" variant="tonal" density="compact" class="mt-3">
                Aucune erreur détectée dans ce fichier.
              </v-alert>
            </template>
          </template>

          <template #[`item.2`]>
            <h2 class="text-subtitle-1 font-weight-medium">Confirmer l'import</h2>
            <p class="text-body-2 text-medium-emphasis mb-3">
              Vérifiez le récapitulatif avant d'enregistrer les comptes.
            </p>

            <v-alert type="info" variant="tonal" density="compact" class="mb-3">
              <strong>{{ resume.valides }}</strong>
              compte{{ resume.valides > 1 ? 's' : '' }} sera{{
                resume.valides > 1 ? 'ont' : ''
              }}
              importé{{ resume.valides > 1 ? 's' : '' }}.
              <template v-if="resume.ignores > 0">
                {{ resume.ignores }} ligne{{ resume.ignores > 1 ? 's' : '' }} du fichier
                {{ resume.ignores > 1 ? 'seront ignorées' : 'sera ignorée' }}
                (doublons ou erreurs).
              </template>
            </v-alert>

            <dl class="comptes__recap">
              <div>
                <dt>Fichier</dt>
                <dd>{{ nomFichier ?? 'Fichier Excel' }}</dd>
              </div>
              <div>
                <dt>Lignes lues</dt>
                <dd>{{ resume.total }}</dd>
              </div>
              <div>
                <dt>Lignes valides</dt>
                <dd>{{ resume.valides }}</dd>
              </div>
              <div>
                <dt>Lignes ignorées</dt>
                <dd>{{ resume.ignores }}</dd>
              </div>
            </dl>

            <v-alert
              v-if="resume.valides === 0"
              type="warning"
              variant="tonal"
              density="compact"
              class="mt-3"
            >
              Aucune ligne valide : corrigez le fichier puis relancez l'analyse.
            </v-alert>

            <v-alert
              v-if="erreurImport"
              type="error"
              variant="tonal"
              density="compact"
              class="mt-3"
            >
              {{ erreurImport }}
            </v-alert>
          </template>
        </v-stepper>

        <v-card-actions>
          <v-btn variant="text" :disabled="importEnCours" @click="fermerImport"> Annuler </v-btn>
          <v-spacer />
          <v-btn
            v-if="etapeImport === 2"
            variant="text"
            :disabled="importEnCours"
            @click="etapeImport = 1"
          >
            Retour
          </v-btn>
          <v-btn
            v-if="etapeImport === 1"
            color="primary"
            :disabled="!peutContinuer"
            @click="etapeImport = 2"
          >
            Continuer
          </v-btn>
          <v-btn
            v-else
            color="primary"
            :loading="importEnCours"
            :disabled="!peutContinuer"
            @click="lancerImport"
          >
            Importer {{ resume.valides }} compte{{ resume.valides > 1 ? 's' : '' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.visible" :color="snackbar.color" timeout="5000">
      {{ snackbar.message }}
      <template #actions>
        <v-btn variant="text" @click="snackbar.visible = false">Fermer</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<style scoped>
.comptes {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.comptes__entete {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.comptes__titre {
  flex: 1;
  min-width: 240px;
}

.comptes__titre h1 {
  margin: 0;
}

.comptes__sous-titre {
  margin: 4px 0 0;
}

.comptes__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.comptes__filtres {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid rgb(var(--v-theme-outline));
}

.comptes__recherche {
  flex: 1 1 280px;
  max-width: 380px;
}

.comptes__filtres :deep(.v-select) {
  flex: 0 1 200px;
  min-width: 160px;
}

.comptes__matricule {
  font-family: 'Roboto Mono', monospace;
  font-size: 13px;
}

.comptes__table :deep(td),
.comptes__table :deep(th) {
  white-space: nowrap;
}

.comptes__cartes {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px;
}

.comptes__squelette {
  margin-bottom: 4px;
}

.comptes__vide {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 28px 0;
  text-align: center;
  color: rgb(var(--v-theme-on-surface-variant));
}

.comptes__vide p {
  margin: 0;
}

.compte-carte__details {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.comptes__pagination {
  align-self: center;
  margin-top: 4px;
}

.comptes__import-outils {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
}

.comptes__compteurs {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-top: 16px;
}

.compteur {
  display: flex;
  flex-direction: column;
  border-radius: 8px;
  padding: 12px;
}

.compteur strong {
  font-size: 24px;
  line-height: 32px;
  font-weight: 500;
}

.compteur span {
  font-size: 12px;
  line-height: 16px;
}

.compteur--valides {
  background: #e3f1e4;
  color: #1b5e20;
}

.compteur--doublons {
  background: #fdebd9;
  color: #8a4100;
}

.compteur--erreurs {
  background: #fbe4e4;
  color: #b71c1c;
}

.comptes__erreurs {
  margin-top: 16px;
}

.comptes__erreurs ul {
  margin: 6px 0 0;
  padding-left: 20px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.comptes__recap {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
}

.comptes__recap div {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid rgb(var(--v-theme-outline));
  padding-bottom: 8px;
}

.comptes__recap dt {
  color: rgb(var(--v-theme-on-surface-variant));
}

.comptes__recap dd {
  margin: 0;
  font-weight: 500;
  text-align: right;
  overflow-wrap: anywhere;
}

@media (max-width: 599px) {
  .comptes__actions {
    width: 100%;
  }

  .comptes__actions :deep(.v-btn) {
    flex: 1 1 auto;
  }

  .comptes__compteurs {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
