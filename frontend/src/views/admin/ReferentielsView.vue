<script setup>
import { computed, onMounted, reactive, ref } from 'vue'

import { recupererAnnees, recupererEnseignants, recupererOptions } from '@/api/referentiels'
import ReferentielTable from '@/components/admin/ReferentielTable.vue'

/*
 * SCRUM-80 — A02 « Référentiels académiques ».
 *
 * Cinq onglets partagent le même composant `ReferentielTable` : seule la
 * configuration (endpoint, colonnes, champs) change.
 */
const onglet = ref('departements')

const onglets = [
  { value: 'departements', title: 'Départements' },
  { value: 'filieres', title: 'Filières' },
  { value: 'niveaux', title: 'Niveaux' },
  { value: 'classes', title: 'Classes' },
  { value: 'matieres', title: 'Matières' },
]

const SEMESTRES = [
  { title: 'Semestre 1', value: 1 },
  { title: 'Semestre 2', value: 2 },
]

const options = reactive({
  departements: [],
  filieres: [],
  niveaux: [],
  classes: [],
  annees: [],
  enseignants: [],
})

const chargementOptions = ref(false)
const erreurOptions = ref('')

const enOption = (element, libelle) => ({
  title: libelle ?? `${element.code} — ${element.libelle}`,
  value: element.id,
})

const optionsDepartements = computed(() =>
  options.departements.map((departement) =>
    enOption(departement, `${departement.code} — ${departement.libelle}`),
  ),
)

const optionsFilieres = computed(() =>
  options.filieres.map((filiere) => enOption(filiere, `${filiere.code} — ${filiere.libelle}`)),
)

const optionsNiveaux = computed(() =>
  options.niveaux.map((niveau) => enOption(niveau, `${niveau.code} — ${niveau.libelle}`)),
)

const optionsClasses = computed(() =>
  options.classes.map((classe) =>
    enOption(
      classe,
      `${classe.code} — ${classe.libelle}${classe.annee ? ` (${classe.annee.libelle})` : ''}`,
    ),
  ),
)

const optionsAnnees = computed(() =>
  options.annees.map((annee) => ({
    title: annee.active ? `${annee.libelle} (active)` : annee.libelle,
    value: annee.id,
  })),
)

const optionsEnseignants = computed(() =>
  options.enseignants.map((enseignant) => ({
    title: `${enseignant.prenom} ${enseignant.nom}`,
    value: enseignant.id,
  })),
)

const anneeActive = computed(() => options.annees.find((annee) => annee.active)?.id ?? null)

async function chargerOptions() {
  chargementOptions.value = true
  erreurOptions.value = ''

  const resultats = await Promise.allSettled([
    recupererOptions('/admin/departements'),
    recupererOptions('/admin/filieres'),
    recupererOptions('/admin/niveaux'),
    recupererOptions('/admin/classes'),
    recupererAnnees(),
    recupererEnseignants(),
  ])

  const cles = ['departements', 'filieres', 'niveaux', 'classes', 'annees', 'enseignants']
  let echec = false

  resultats.forEach((resultat, index) => {
    if (resultat.status === 'fulfilled') {
      options[cles[index]] = resultat.value
    } else {
      options[cles[index]] = []
      echec = true
    }
  })

  erreurOptions.value = echec
    ? "Certaines options n'ont pas pu être chargées : les formulaires concernés sont incomplets."
    : ''
  chargementOptions.value = false
}

onMounted(chargerOptions)

const configs = computed(() => ({
  departements: {
    endpoint: '/admin/departements',
    titreAjout: 'Ajouter un département',
    titreModification: 'Modifier le département',
    colonnes: [
      { title: 'Code', key: 'code' },
      { title: 'Libellé', key: 'libelle' },
    ],
    champs: [
      { cle: 'code', libelle: 'Code', type: 'texte', obligatoire: true, max: 20, demi: true },
      {
        cle: 'libelle',
        libelle: 'Libellé',
        type: 'texte',
        obligatoire: true,
        max: 120,
        demi: true,
      },
    ],
    messages: {
      ajout: 'Département ajouté.',
      modification: 'Département modifié.',
      desactivation: 'Département désactivé.',
      suppression: 'Département supprimé.',
    },
  },

  filieres: {
    endpoint: '/admin/filieres',
    titreAjout: 'Ajouter une filière',
    titreModification: 'Modifier la filière',
    colonnes: [
      { title: 'Code', key: 'code' },
      { title: 'Libellé', key: 'libelle' },
      {
        title: 'Département',
        key: 'departement',
        valeur: (filiere) => filiere.departement?.libelle ?? '—',
      },
    ],
    champs: [
      { cle: 'code', libelle: 'Code', type: 'texte', obligatoire: true, max: 20, demi: true },
      {
        cle: 'libelle',
        libelle: 'Libellé',
        type: 'texte',
        obligatoire: true,
        max: 120,
        demi: true,
      },
      {
        cle: 'departement_id',
        libelle: 'Département',
        type: 'select',
        obligatoire: true,
        items: optionsDepartements.value,
        valeur: (filiere) => filiere.departement?.id ?? null,
      },
    ],
    messages: {
      ajout: 'Filière ajoutée.',
      modification: 'Filière modifiée.',
      desactivation: 'Filière désactivée.',
      suppression: 'Filière supprimée.',
    },
  },

  niveaux: {
    endpoint: '/admin/niveaux',
    titreAjout: 'Ajouter un niveau',
    titreModification: 'Modifier le niveau',
    colonnes: [
      { title: 'Code', key: 'code' },
      { title: 'Libellé', key: 'libelle' },
    ],
    champs: [
      { cle: 'code', libelle: 'Code', type: 'texte', obligatoire: true, max: 10, demi: true },
      {
        cle: 'libelle',
        libelle: 'Libellé',
        type: 'texte',
        obligatoire: true,
        max: 60,
        demi: true,
      },
    ],
    messages: {
      ajout: 'Niveau ajouté.',
      modification: 'Niveau modifié.',
      desactivation: 'Niveau désactivé.',
      suppression: 'Niveau supprimé.',
    },
  },

  classes: {
    endpoint: '/admin/classes',
    titreAjout: 'Ajouter une classe',
    titreModification: 'Modifier la classe',
    colonnes: [
      { title: 'Code', key: 'code' },
      { title: 'Libellé', key: 'libelle' },
      { title: 'Filière', key: 'filiere', valeur: (classe) => classe.filiere?.libelle ?? '—' },
      { title: 'Niveau', key: 'niveau', valeur: (classe) => classe.niveau?.libelle ?? '—' },
      { title: 'Année', key: 'annee', valeur: (classe) => classe.annee?.libelle ?? '—' },
    ],
    champs: [
      { cle: 'code', libelle: 'Code', type: 'texte', obligatoire: true, max: 30, demi: true },
      {
        cle: 'libelle',
        libelle: 'Libellé',
        type: 'texte',
        obligatoire: true,
        max: 120,
        demi: true,
      },
      {
        cle: 'filiere_id',
        libelle: 'Filière',
        type: 'select',
        obligatoire: true,
        items: optionsFilieres.value,
        valeur: (classe) => classe.filiere?.id ?? null,
      },
      {
        cle: 'niveau_id',
        libelle: 'Niveau',
        type: 'select',
        obligatoire: true,
        items: optionsNiveaux.value,
        valeur: (classe) => classe.niveau?.id ?? null,
      },
      {
        cle: 'annee_id',
        libelle: 'Année universitaire',
        type: 'select',
        obligatoire: true,
        items: optionsAnnees.value,
        defaut: () => anneeActive.value,
        valeur: (classe) => classe.annee?.id ?? null,
      },
    ],
    messages: {
      ajout: 'Classe ajoutée.',
      modification: 'Classe modifiée.',
      desactivation: 'Classe désactivée.',
      suppression: 'Classe supprimée.',
    },
  },

  matieres: {
    endpoint: '/admin/matieres',
    titreAjout: 'Ajouter une matière',
    titreModification: 'Modifier la matière',
    colonnes: [
      { title: 'Code', key: 'code' },
      { title: 'Libellé', key: 'libelle' },
      { title: 'Semestre', key: 'semestre', valeur: (matiere) => `Semestre ${matiere.semestre}` },
      {
        title: 'Classes',
        key: 'classes',
        valeur: (matiere) =>
          matiere.classes?.length ? matiere.classes.map((classe) => classe.code).join(', ') : '—',
      },
      {
        title: 'Enseignant(s)',
        key: 'enseignants',
        valeur: (matiere) =>
          matiere.enseignants?.length
            ? matiere.enseignants
                .map((enseignant) => `${enseignant.prenom} ${enseignant.nom}`)
                .join(', ')
            : '—',
      },
    ],
    champs: [
      { cle: 'code', libelle: 'Code', type: 'texte', obligatoire: true, max: 30, demi: true },
      {
        cle: 'libelle',
        libelle: 'Libellé',
        type: 'texte',
        obligatoire: true,
        max: 160,
        demi: true,
      },
      {
        cle: 'semestre',
        libelle: 'Semestre',
        type: 'select',
        obligatoire: true,
        items: SEMESTRES,
        demi: true,
      },
      {
        cle: 'classe_ids',
        libelle: 'Classes',
        type: 'autocomplete',
        multiple: true,
        items: optionsClasses.value,
        valeur: (matiere) => matiere.classes?.map((classe) => classe.id) ?? [],
      },
      {
        cle: 'enseignant_ids',
        libelle: 'Enseignants',
        type: 'autocomplete',
        multiple: true,
        items: optionsEnseignants.value,
        valeur: (matiere) => matiere.enseignants?.map((enseignant) => enseignant.id) ?? [],
      },
    ],
    messages: {
      ajout: 'Matière ajoutée.',
      modification: 'Matière modifiée.',
      desactivation: 'Matière désactivée.',
      suppression: 'Matière supprimée.',
    },
  },
}))

const configActive = computed(() => configs.value[onglet.value])
</script>

<template>
  <div class="referentiels">
    <header class="referentiels__entete">
      <h1 class="text-h5 font-weight-medium">Référentiels académiques</h1>
      <p class="text-body-2 text-medium-emphasis referentiels__sous-titre">
        Ces listes servent de base aux groupes organisationnels et aux comptes autorisés.
      </p>
    </header>

    <v-card variant="outlined" class="referentiels__carte">
      <v-tabs v-model="onglet" color="primary" show-arrows>
        <v-tab v-for="tab in onglets" :key="tab.value" :value="tab.value">
          {{ tab.title }}
        </v-tab>
      </v-tabs>

      <v-progress-linear v-if="chargementOptions" indeterminate color="primary" />

      <v-alert
        v-if="erreurOptions"
        type="warning"
        variant="tonal"
        density="compact"
        class="referentiels__alerte"
      >
        {{ erreurOptions }}
        <template #append>
          <v-btn variant="text" size="small" @click="chargerOptions">Réessayer</v-btn>
        </template>
      </v-alert>

      <ReferentielTable :key="onglet" v-bind="configActive" />
    </v-card>
  </div>
</template>

<style scoped>
.referentiels {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.referentiels__entete h1 {
  margin: 0;
}

.referentiels__sous-titre {
  margin: 4px 0 0;
}

.referentiels__carte {
  overflow: hidden;
}

.referentiels__alerte {
  margin: 12px 16px 0;
}
</style>
