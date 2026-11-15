import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { aliases, mdi } from 'vuetify/iconsets/mdi'
import { fr } from 'vuetify/locale'

export default createVuetify({
  components,
  directives,
  // SCRUM-172 : textes internes de Vuetify en français (pagination des tableaux…).
  locale: {
    locale: 'fr',
    fallback: 'fr',
    messages: { fr },
  },
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: {
      mdi,
    },
  },
  theme: {
    defaultTheme: 'uniconnectLight',
    themes: {
      uniconnectLight: {
        dark: false,
        // SCRUM-173 : textes secondaires et libellés de champs à 4,5:1 minimum
        // (0,6 par défaut donnait 4,2:1 sur fond blanc).
        variables: {
          'medium-emphasis-opacity': 0.7,
        },
        colors: {
          primary: '#1E4FB8',
          secondary: '#00796B',
          accent: '#F59E0B',
          error: '#C62828',
          warning: '#ED6C02',
          info: '#0288D1',
          success: '#2E7D32',
          background: '#F4F6FB',
          surface: '#FFFFFF',
          'surface-variant': '#EEF2FA',
          'on-surface': '#1C2333',
          'on-surface-variant': '#5B6478',
          'on-background': '#1C2333',
          outline: '#D5DBE7',
          'primary-soft': '#E3EBFB',
          'on-primary-soft': '#1E4FB8',
        },
      },
    },
  },
  defaults: {
    VBtn: { variant: 'flat', rounded: 'sm' },
    VTextField: { variant: 'outlined', density: 'comfortable' },
    // Même style pour tous les champs de formulaire (sinon listes, zones de texte et
    // choix de fichier apparaissaient en gris « filled » à côté des champs texte).
    VTextarea: { variant: 'outlined', density: 'comfortable' },
    VSelect: { variant: 'outlined', density: 'comfortable' },
    VAutocomplete: { variant: 'outlined', density: 'comfortable' },
    VCombobox: { variant: 'outlined', density: 'comfortable' },
    VFileInput: { variant: 'outlined', density: 'comfortable' },
    VChip: { size: 'small', variant: 'tonal' },
    VCard: { rounded: 'lg', variant: 'outlined' },
    VListItem: { rounded: 'lg' },
    // Une carte « outlined » a un fond transparent : dans une boîte de dialogue, la page
    // se voyait au travers. Les cartes des dialogues sont donc pleines (fond + ombre).
    VDialog: { VCard: { variant: 'elevated' } },
  },
})
