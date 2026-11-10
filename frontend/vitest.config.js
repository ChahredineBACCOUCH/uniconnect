import { fileURLToPath } from 'node:url'
import { mergeConfig, defineConfig, configDefaults } from 'vitest/config'
import viteConfig from './vite.config.js'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      exclude: [...configDefaults.exclude, 'e2e/**'],
      root: fileURLToPath(new URL('./', import.meta.url)),
      setupFiles: [fileURLToPath(new URL('./src/test-setup.js', import.meta.url))],
      // Vuetify importe des fichiers CSS : on le laisse passer par Vite pour que
      // les composants soient testables (sinon Node ne sait pas lire le CSS).
      server: { deps: { inline: ['vuetify'] } },
    },
  }),
)
