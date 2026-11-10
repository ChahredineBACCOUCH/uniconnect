/*
 * Environnement de test jsdom : Vuetify utilise ResizeObserver pour ses
 * zones de texte auto-extensibles et ses menus.
 */
class ResizeObserverMock {
  observe() {}

  unobserve() {}

  disconnect() {}
}

if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = ResizeObserverMock
}
