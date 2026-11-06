import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

export default createVuetify({
  components,
  directives,
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
          'on-background': '#1C2333',
        },
      },
    },
  },
  defaults: {
    VBtn: { variant: 'flat', rounded: 'sm' },
    VTextField: { variant: 'outlined', density: 'comfortable' },
    VChip: { size: 'small', variant: 'tonal' },
    VCard: { rounded: 'lg', variant: 'outlined' },
    VListItem: { rounded: 'lg' },
  },
})
