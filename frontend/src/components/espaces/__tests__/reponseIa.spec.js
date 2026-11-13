import { describe, expect, it } from 'vitest'

import { decouperReponse, libelleSource, segmenter } from '../reponseIa'

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
  describe('marqueurs de citation (SCRUM-143)', () => {
    const SOURCES = [{ n: 1 }, { n: 2 }]

    it('rend cliquables les marqueurs qui ont une source', () => {
      const [bloc] = decouperReponse('Les attributs [1] et la zone [2].', SOURCES)

      expect(bloc.segments).toEqual([
        { texte: 'Les attributs ', gras: false },
        { texte: '[1]', gras: false, citations: [1] },
        { texte: ' et la zone ', gras: false },
        { texte: '[2]', gras: false, citations: [2] },
        { texte: '.', gras: false },
      ])
    })

    it('reconnaît [1, 2] et 【2】, et ignore un numéro sans source', () => {
      const [bloc] = decouperReponse('A [1, 2] B 【2】 C [7]', SOURCES)
      const cites = bloc.segments.filter((s) => s.citations)

      expect(cites.map((s) => s.citations)).toEqual([[1, 2], [2]])
      expect(bloc.segments.at(-1)).toEqual({ texte: '[7]', gras: false })
    })

    it('ne transforme rien sans sources', () => {
      expect(segmenter('Réponse [1]')).toEqual([{ texte: 'Réponse [1]', gras: false }])
    })

    it('affiche la page seulement quand elle est connue', () => {
      expect(libelleSource({ fichier: 'chap03.pdf', page: 3 })).toBe('chap03.pdf · p. 3')
      expect(libelleSource({ fichier: 'lien.txt', page: null })).toBe('lien.txt')
    })
  })
})
