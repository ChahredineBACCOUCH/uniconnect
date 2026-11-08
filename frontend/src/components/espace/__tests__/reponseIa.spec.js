import { describe, expect, it } from 'vitest'

import { decouperReponse, segmenter } from '../reponseIa'

describe('découpage des réponses de l’IA', () => {
  it('sépare paragraphes et listes à puces', () => {
    const blocs = decouperReponse(
      'Selon le cours :\n- premier point [1]\n- second point [2]\n\nConclusion.',
    )

    expect(blocs).toEqual([
      { type: 'paragraphe', segments: [{ texte: 'Selon le cours :', gras: false }] },
      {
        type: 'liste',
        items: [
          [{ texte: 'premier point [1]', gras: false }],
          [{ texte: 'second point [2]', gras: false }],
        ],
      },
      { type: 'paragraphe', segments: [{ texte: 'Conclusion.', gras: false }] },
    ])
  })

  it('reconnaît les puces *, • et les listes numérotées', () => {
    const blocs = decouperReponse('* un\n• deux\n3. trois')
    expect(blocs).toHaveLength(1)
    expect(blocs[0].items).toHaveLength(3)
  })

  it('met en gras le texte entre ** sans interpréter de HTML', () => {
    expect(segmenter('Le **curseur** <script>x</script>')).toEqual([
      { texte: 'Le ', gras: false },
      { texte: 'curseur', gras: true },
      { texte: ' <script>x</script>', gras: false },
    ])
  })

  it('renvoie une liste vide pour une réponse vide', () => {
    expect(decouperReponse('')).toEqual([])
    expect(decouperReponse(null)).toEqual([])
  })
})
