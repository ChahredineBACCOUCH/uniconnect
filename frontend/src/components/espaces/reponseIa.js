/*
 * Découpe le texte d'une réponse de l'IA en blocs affichables sans v-html
 * (le texte vient d'un modèle d'IA : on ne l'injecte jamais comme du HTML).
 *
 *   « Intro :\n- point **important** [1]\n- autre point »
 *   → [ { type: 'paragraphe', segments }, { type: 'liste', items: [segments, segments] } ]
 *
 * Un segment = { texte, gras } : seul le gras Markdown (**…**) est reconnu.
 */

const PUCE = /^\s*(?:[-*•]|\d+[.)])\s+/

export function segmenter(ligne) {
  return ligne
    .split(/(\*\*[^*]+\*\*)/)
    .filter((morceau) => morceau !== '')
    .map((morceau) =>
      morceau.startsWith('**') && morceau.endsWith('**') && morceau.length > 4
        ? { texte: morceau.slice(2, -2), gras: true }
        : { texte: morceau, gras: false },
    )
}

export function decouperReponse(texte) {
  const blocs = []
  for (const brute of (texte ?? '').split(/\r?\n/)) {
    const ligne = brute.trim()
    if (ligne === '') continue

    if (PUCE.test(ligne)) {
      const item = segmenter(ligne.replace(PUCE, ''))
      const dernier = blocs.at(-1)
      if (dernier?.type === 'liste') dernier.items.push(item)
      else blocs.push({ type: 'liste', items: [item] })
    } else {
      blocs.push({ type: 'paragraphe', segments: segmenter(ligne) })
    }
  }
  return blocs
}
