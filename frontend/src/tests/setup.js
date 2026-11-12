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

/*
 * jsdom ne fournit pas `visualViewport` : les overlays Vuetify (v-dialog,
 * v-menu) le lisent sans garde et lèvent une ReferenceError à l'ouverture.
 */
if (typeof globalThis.visualViewport === 'undefined') {
  globalThis.visualViewport = {
    width: 1024,
    height: 768,
    addEventListener() {},
    removeEventListener() {},
  }
}

/*
 * Node 22+ expose un global `localStorage` expérimental : à partir de Node 26,
 * il existe mais renvoie undefined (il faut --localstorage-file) et masque alors
 * celui de jsdom. Les tests du store auth échouaient alors sur `localStorage`.
 * Quand aucun stockage n'est disponible, on en installe un en mémoire.
 */
if (typeof window === 'undefined' || !window.localStorage) {
  const donnees = new Map()

  Object.defineProperty(globalThis, 'localStorage', {
    configurable: true,
    writable: true,
    value: {
      get length() {
        return donnees.size
      },
      key(index) {
        return [...donnees.keys()][index] ?? null
      },
      getItem(cle) {
        return donnees.has(cle) ? donnees.get(cle) : null
      },
      setItem(cle, valeur) {
        donnees.set(String(cle), String(valeur))
      },
      removeItem(cle) {
        donnees.delete(cle)
      },
      clear() {
        donnees.clear()
      },
    },
  })
}
