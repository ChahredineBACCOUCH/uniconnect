<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import {
  creerReferentiel,
  desactiverReferentiel,
  modifierReferentiel,
  recupererReferentiel,
  supprimerReferentiel,
} from '@/api/referentiels'
import { messageErreur } from '@/utils/espaces'

/*
 * SCRUM-80 — tableau générique d'un référentiel académique (écran A02).
 *
 * Le parent fournit l'endpoint, les colonnes affichées et la description des
 * champs du formulaire. La recherche, la pagination, le dialogue d'ajout /
 * modification, la désactivation et la suppression sont gérés ici.
 */
const props = defineProps({
  endpoint: { type: String, required: true },
  colonnes: { type: Array, required: true },
  champs: { type: Array, required: true },
  titreAjout: { type: String, default: 'Ajouter un élément' },
  titreModification: { type: String, default: "Modifier l'élément" },
  placeholderRecherche: { type: String, default: 'Rechercher par code ou libellé' },
  messages: {
    type: Object,
    default: () => ({
      ajout: 'Élément ajouté.',
      modification: 'Élément modifié.',
      desactivation: 'Élément désactivé.',
      suppression: 'Élément supprimé.',
    }),
  },
})

const chargement = ref(false)
const erreurChargement = ref('')
const elements = ref([])
const total = ref(0)
const page = ref(1)
const parPage = ref(10)
const optionsParPage = [10, 20, 50]

const recherche = ref('')
const rechercheAppliquee = ref('')
let minuterieRecherche = null

watch(recherche, (valeur) => {
  clearTimeout(minuterieRecherche)
  minuterieRecherche = setTimeout(() => {
    rechercheAppliquee.value = valeur.trim()
  }, 300)
})

onBeforeUnmount(() => clearTimeout(minuterieRecherche))

/*
 * Une seule requête à la fois : le numéro permet d'ignorer une réponse
 * obsolète si l'utilisateur change rapidement de recherche ou de page.
 */
let numeroRequete = 0

async function charger() {
  const numero = ++numeroRequete
  chargement.value = true
  erreurChargement.value = ''

  try {
    const reponse = await recupererReferentiel(props.endpoint, {
      page: page.value,
      par_page: parPage.value,
      q: rechercheAppliquee.value || undefined,
    })

    if (numero !== numeroRequete) return

    elements.value = reponse.data ?? []
    total.value = reponse.meta?.total ?? 0
  } catch (error) {
    if (numero !== numeroRequete) return

    erreurChargement.value = messageErreur(error, 'Les éléments n’ont pas pu être chargés.')
    elements.value = []
    total.value = 0
  } finally {
    if (numero === numeroRequete) chargement.value = false
  }
}

watch(rechercheAppliquee, () => {
  if (page.value !== 1) page.value = 1
  else charger()
})

watch([page, parPage], charger)

charger()

/* ------------------------------------------------------------------ */
/* Notifications                                                       */
/* ------------------------------------------------------------------ */

const snackbar = ref({ visible: false, message: '', color: 'success', timeout: 5000 })

function notifier(message, color = 'success', timeout = 5000) {
  snackbar.value = { visible: true, message, color, timeout }
}

/* ------------------------------------------------------------------ */
/* Tableau                                                             */
/* ------------------------------------------------------------------ */

/*
 * Les colonnes peuvent fournir une fonction `valeur(element)` pour afficher
 * une donnée calculée (libellé d'une relation, liste de codes, etc.).
 * On prépare les lignes pour éviter un slot dynamique par colonne.
 */
const lignes = computed(() =>
  elements.value.map((element) => {
    const ligne = { ...element }

    for (const colonne of props.colonnes) {
      if (typeof colonne.valeur === 'function') {
        ligne[colonne.key] = colonne.valeur(element)
      }
    }

    return ligne
  }),
)

const entetes = computed(() => [
  ...props.colonnes.map((colonne) => ({
    title: colonne.title,
    key: colonne.key,
    sortable: colonne.sortable ?? false,
    align: colonne.align,
    width: colonne.width,
  })),
  { title: 'État', key: 'etat', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 110 },
])

/* ------------------------------------------------------------------ */
/* Dialogue d'ajout / modification                                     */
/* ------------------------------------------------------------------ */

const dialogue = ref(false)
const elementEdite = ref(null)
const formulaire = ref(null)
const valeurs = ref({})
const enregistrement = ref(false)
const erreurFormulaire = ref('')
const erreursChamps = ref({})

const estModification = computed(() => elementEdite.value !== null)
const titreDialogue = computed(() =>
  estModification.value ? props.titreModification : props.titreAjout,
)

function valeurParDefaut(champ) {
  const defaut = typeof champ.defaut === 'function' ? champ.defaut() : champ.defaut

  if (defaut !== undefined) return Array.isArray(defaut) ? [...defaut] : defaut
  if (champ.multiple) return []
  if (champ.type === 'texte' || champ.type === 'nombre') return ''
  return null
}

function formulaireVide() {
  return Object.fromEntries(props.champs.map((champ) => [champ.cle, valeurParDefaut(champ)]))
}

function ouvrirAjout() {
  elementEdite.value = null
  valeurs.value = formulaireVide()
  erreurFormulaire.value = ''
  erreursChamps.value = {}
  dialogue.value = true
}

function ouvrirModification(element) {
  elementEdite.value = element

  valeurs.value = Object.fromEntries(
    props.champs.map((champ) => {
      const valeur = typeof champ.valeur === 'function' ? champ.valeur(element) : element[champ.cle]

      if (valeur !== undefined && valeur !== null) {
        return [champ.cle, Array.isArray(valeur) ? [...valeur] : valeur]
      }

      return [champ.cle, valeurParDefaut(champ)]
    }),
  )

  erreurFormulaire.value = ''
  erreursChamps.value = {}
  dialogue.value = true
}

function reglesChamp(champ) {
  const regles = []

  if (champ.obligatoire) {
    regles.push((valeur) => {
      if (Array.isArray(valeur)) return valeur.length > 0 || `${champ.libelle} est obligatoire.`

      return (
        (valeur !== null && valeur !== undefined && String(valeur).trim() !== '') ||
        `${champ.libelle} est obligatoire.`
      )
    })
  }

  if (champ.max) {
    regles.push(
      (valeur) =>
        String(valeur ?? '').length <= champ.max ||
        `${champ.libelle} ne peut pas dépasser ${champ.max} caractères.`,
    )
  }

  return [...regles, ...(champ.regles ?? [])]
}

function construirePayload() {
  const payload = {}

  for (const champ of props.champs) {
    let valeur = valeurs.value[champ.cle]

    if (champ.type === 'texte') valeur = String(valeur ?? '').trim()
    if (champ.type === 'nombre') {
      valeur = valeur === '' || valeur === null || valeur === undefined ? null : Number(valeur)
    }
    if (champ.multiple) valeur = Array.isArray(valeur) ? [...valeur] : []

    payload[champ.cle] = valeur
  }

  return payload
}

async function enregistrer() {
  const { valid } = await formulaire.value.validate()
  if (!valid) return

  enregistrement.value = true
  erreurFormulaire.value = ''
  erreursChamps.value = {}

  const modification = estModification.value

  try {
    if (modification) {
      await modifierReferentiel(props.endpoint, elementEdite.value.id, construirePayload())
    } else {
      await creerReferentiel(props.endpoint, construirePayload())
    }

    dialogue.value = false
    notifier(modification ? props.messages.modification : props.messages.ajout)
    await charger()
  } catch (error) {
    erreursChamps.value = error.errors ?? error.response?.data?.errors ?? {}
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
    ? 'Supprimer cet élément ?'
    : 'Désactiver cet élément ?',
)

const texteConfirmation = computed(() =>
  confirmation.value?.action === 'supprimer'
    ? 'Cet élément sera supprimé définitivement. Si vous voulez le conserver, désactivez-le plutôt.'
    : "Cet élément ne sera plus proposé dans les listes. Il reste conservé pour l'historique.",
)

function demanderAction(action, element) {
  confirmation.value = { action, element }
}

function fermerConfirmation(ouverte) {
  if (!ouverte && !traitementAction.value) confirmation.value = null
}

async function confirmerAction() {
  if (!confirmation.value) return

  const { action, element } = confirmation.value
  traitementAction.value = true

  try {
    if (action === 'supprimer') {
      await supprimerReferentiel(props.endpoint, element.id)
      confirmation.value = null
      notifier(props.messages.suppression)

      // Dernière ligne d'une page : revenir à la page précédente.
      if (elements.value.length === 1 && page.value > 1) page.value -= 1
      else await charger()
    } else {
      await desactiverReferentiel(props.endpoint, element.id)
      confirmation.value = null
      notifier(props.messages.desactivation)
      await charger()
    }
  } catch (error) {
    confirmation.value = null

    // 409 : l'API explique que l'élément est utilisé et qu'il faut le désactiver.
    const utilise = error.response?.status === 409
    notifier(
      messageErreur(error, "L'action n'a pas pu être effectuée."),
      'error',
      utilise ? 8000 : 5000,
    )
  } finally {
    traitementAction.value = false
  }
}
</script>

<template>
  <div class="referentiel">
    <div class="referentiel__barre">
      <v-text-field
        v-model="recherche"
        class="referentiel__recherche"
        :placeholder="placeholderRecherche"
        prepend-inner-icon="mdi-magnify"
        aria-label="Rechercher"
        density="comfortable"
        clearable
        hide-details
      />

      <v-spacer />

      <v-btn color="primary" prepend-icon="mdi-plus" @click="ouvrirAjout">
        {{ titreAjout }}
      </v-btn>
    </div>

    <v-alert v-if="erreurChargement" type="error" variant="tonal" density="compact">
      {{ erreurChargement }}
      <template #append>
        <v-btn variant="text" size="small" @click="charger">Réessayer</v-btn>
      </template>
    </v-alert>

    <v-data-table-server
      v-model:page="page"
      v-model:items-per-page="parPage"
      :items="lignes"
      :items-length="total"
      :headers="entetes"
      :items-per-page-options="optionsParPage"
      :loading="chargement"
      item-value="id"
      density="comfortable"
      hover
      no-data-text="Aucun élément ne correspond à cette recherche."
      class="referentiel__table"
    >
      <template #[`item.etat`]="{ item }">
        <v-chip
          :color="item.actif ? 'success' : undefined"
          variant="tonal"
          size="small"
          :prepend-icon="item.actif ? 'mdi-check-circle-outline' : 'mdi-minus-circle-outline'"
        >
          {{ item.actif ? 'Actif' : 'Désactivé' }}
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
          <template #activator="{ props: menuProps }">
            <v-btn v-bind="menuProps" icon variant="text" size="small" aria-label="Plus d'actions">
              <v-icon icon="mdi-dots-vertical" size="20" />
            </v-btn>
          </template>

          <v-list density="compact">
            <v-list-item
              v-if="item.actif"
              prepend-icon="mdi-cancel"
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

    <!-- Dialogue d'ajout / modification, généré à partir des champs. -->
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
              <v-col v-for="champ in champs" :key="champ.cle" cols="12" :sm="champ.demi ? 6 : 12">
                <v-text-field
                  v-if="champ.type === 'texte' || champ.type === 'nombre'"
                  v-model="valeurs[champ.cle]"
                  :label="champ.libelle"
                  :type="champ.type === 'nombre' ? 'number' : 'text'"
                  :counter="champ.max"
                  :rules="reglesChamp(champ)"
                  :error-messages="erreursChamps[champ.cle]"
                />

                <v-select
                  v-else-if="champ.type === 'select'"
                  v-model="valeurs[champ.cle]"
                  :items="champ.items ?? []"
                  item-title="title"
                  item-value="value"
                  :label="champ.libelle"
                  :clearable="!champ.obligatoire"
                  :rules="reglesChamp(champ)"
                  :error-messages="erreursChamps[champ.cle]"
                />

                <v-autocomplete
                  v-else-if="champ.type === 'autocomplete'"
                  v-model="valeurs[champ.cle]"
                  :items="champ.items ?? []"
                  item-title="title"
                  item-value="value"
                  :label="champ.libelle"
                  :multiple="champ.multiple ?? false"
                  :chips="champ.multiple ?? false"
                  :closable-chips="champ.multiple ?? false"
                  :clearable="!champ.obligatoire"
                  :rules="reglesChamp(champ)"
                  :error-messages="erreursChamps[champ.cle]"
                />
              </v-col>
            </v-row>
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

    <!-- Confirmation d'une action destructive. -->
    <v-dialog
      :model-value="confirmation !== null"
      max-width="480"
      @update:model-value="fermerConfirmation"
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

    <v-snackbar v-model="snackbar.visible" :color="snackbar.color" :timeout="snackbar.timeout">
      {{ snackbar.message }}
      <template #actions>
        <v-btn variant="text" @click="snackbar.visible = false">Fermer</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<style scoped>
.referentiel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.referentiel__barre {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid rgb(var(--v-theme-outline));
}

.referentiel__recherche {
  flex: 1 1 280px;
  max-width: 380px;
}

.referentiel__table :deep(td),
.referentiel__table :deep(th) {
  white-space: nowrap;
}

.referentiel__table :deep(td) {
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
}

@media (max-width: 599px) {
  .referentiel__barre :deep(.v-btn) {
    flex: 1 1 auto;
  }
}
</style>
