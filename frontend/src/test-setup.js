/*
 * Compléments de jsdom pour tester les composants Vuetify.
 * jsdom n'implémente pas ResizeObserver (jauge de progression du bouton)
 * ni matchMedia (points de rupture de l'affichage adaptatif).
 */

if (!globalThis.ResizeObserver) {
  globalThis.ResizeObserver = class ResizeObserver {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
}

if (typeof window !== 'undefined' && !window.matchMedia) {
  window.matchMedia = (query) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  })
}
