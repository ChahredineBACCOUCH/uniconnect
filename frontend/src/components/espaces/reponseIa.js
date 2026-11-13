/*
 * Découpe le texte d'une réponse de l'IA en blocs affichables sans v-html
 * (le texte vient d'un modèle d'IA : on ne l'injecte jamais comme du HTML).
 *
 *   « Intro :\n- point **important** [1]\n- autre point »
 *   → [ { type: 'paragraphe', segments }, { type: 'liste', items: [segments, segments] } ]
 *
 * Un segment = { texte, gras } : seul le gras Markdown (**…**) est reconnu.
 * Avec la liste des numéros de sources (SCRUM-143), les marqueurs [1], [1, 3] ou 【2】
 * deviennent des segments { texte, gras, citations: [n…] } cliquables. Un marqueur
 * sans source correspondante (l'IA s'est trompée de numéro) reste du texte normal.
 */

const PUCE = /^\s*(?:[-*•]|\d+[.)])\s+/

// Même format que AssistantService::sourcesCitees() côté API : [2], [1, 3], 【2】, 【2†source】.
const MARQUEUR = /([[【]\d+(?:\s*,\s*\d+)*(?:†[^\]】]*)?[\]】])/

function citations(marqueur, numeros) {
  return (marqueur.match(/\d+/g) ?? []).map(Number).filter((n) => numeros.has(n))
}

function decouperCitations(segment, numeros) {
  if (!numeros?.size) return [segment]

  return segment.texte
    .split(MARQUEUR)
    .filter((morceau) => morceau !== '')
    .map((morceau) => {
      const cites = MARQUEUR.test(morceau) ? citations(morceau, numeros) : []
      return cites.length
        ? { texte: morceau, gras: segment.gras, citations: cites }
        : { texte: morceau, gras: segment.gras }
    })
}

/**
 * @param {string} ligne
 * @param {Set<number>} [numeros] numéros des sources renvoyées par l'API
 */
export function segmenter(ligne, numeros) {
  return ligne
    .split(/(\*\*[^*]+\*\*)/)
    .filter((morceau) => morceau !== '')
    .map((morceau) =>
      morceau.startsWith('**') && morceau.endsWith('**') && morceau.length > 4
        ? { texte: morceau.slice(2, -2), gras: true }
        : { texte: morceau, gras: false },
    )
    .flatMap((segment) => decouperCitations(segment, numeros))
}

/**
 * @param {string|null} texte
 * @param {Array<{ n: number }>} [sources] sources renvoyées par l'API (rend les [n] cliquables)
 */
export function decouperReponse(texte, sources = []) {
  const numeros = new Set(sources.map((source) => source.n))
  const blocs = []
  for (const brute of (texte ?? '').split(/\r?\n/)) {
    const ligne = brute.trim()
    if (ligne === '') continue

    if (PUCE.test(ligne)) {
      const item = segmenter(ligne.replace(PUCE, ''), numeros)
      const dernier = blocs.at(-1)
      if (dernier?.type === 'liste') dernier.items.push(item)
      else blocs.push({ type: 'liste', items: [item] })
    } else {
      blocs.push({ type: 'paragraphe', segments: segmenter(ligne, numeros) })
    }
  }
  return blocs
}

/** « chap03.pdf · p. 3 », ou seulement le fichier quand la page est inconnue. */
export function libelleSource(source) {
  return source.page != null ? `${source.fichier} · p. ${source.page}` : source.fichier
}
