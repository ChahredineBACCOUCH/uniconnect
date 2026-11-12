import { describe, expect, it } from 'vitest'

import {
  detailEtatGroupe,
  libelleClasses,
  libelleProprietaire,
  libelleTypeGroupe,
  messageGeneration,
  messageSynchronisation,
  pluriel,
  resumeGeneration,
  texteBoutonGeneration,
  texteGeneration,
} from '@/utils/groupes'

/*
 * SCRUM-83 : affichage de l'écran A06 (groupes organisationnels).
 */
describe('utilitaires des groupes organisationnels', () => {
  it('accorde les libellés', () => {
    expect(pluriel(0, 'groupe', 'groupes')).toBe('groupe')
    expect(pluriel(1, 'groupe', 'groupes')).toBe('groupe')
    expect(pluriel(2, 'groupe', 'groupes')).toBe('groupes')
  })

  it('distingue un groupe de classe d’un groupe de matière', () => {
    expect(libelleTypeGroupe({ type: 'CLASSE' })).toBe('Classe')
    expect(libelleTypeGroupe({ type: 'MATIERE' })).toBe('Matière')
  })

  it('liste les classes couvertes par le groupe', () => {
    expect(
      libelleClasses({
        classes: [
          { id: 1, code: 'L2-INFO-A' },
          { id: 2, code: 'L2-INFO-B' },
          { id: 3, code: 'L3-INFO' },
        ],
      }),
    ).toBe('L2-INFO-A, L2-INFO-B, L3-INFO')

    expect(libelleClasses({ classes: [] })).toBe('—')
    expect(libelleClasses({})).toBe('—')
  })

  it('affiche le propriétaire ou un tiret', () => {
    expect(libelleProprietaire({ proprietaire: { nom_complet: 'Fatou Diallo' } })).toBe(
      'Fatou Diallo',
    )
    expect(libelleProprietaire({ proprietaire: null })).toBe('—')
  })

  it('décrit l’état du groupe', () => {
    expect(detailEtatGroupe({ etat: 'ACTIF' })).toEqual({ libelle: 'Actif', color: 'success' })
    expect(detailEtatGroupe({ etat: 'ARCHIVE' })).toEqual({ libelle: 'Archivé', color: null })
  })

  it('résume l’aperçu de génération', () => {
    expect(
      resumeGeneration({ a_creer: [{}, {}], deja_existants: 6, membres_a_ajouter: 5 }),
    ).toEqual({ aCreer: 2, existants: 6, membres: 5 })
    expect(resumeGeneration(null)).toEqual({ aCreer: 0, existants: 0, membres: 0 })
  })

  it('construit les textes du dialogue de génération', () => {
    const apercu = { a_creer: [{}, {}], deja_existants: 6 }

    expect(texteGeneration(apercu)).toBe('2 groupes à créer, 6 existants')
    expect(texteBoutonGeneration(apercu)).toBe('Générer 2 groupes')
    expect(texteGeneration({ a_creer: [], deja_existants: 1 })).toBe(
      '0 groupe à créer, 1 existant',
    )
  })

  it('construit les messages de la snackbar', () => {
    expect(messageGeneration({ crees: 9, membres_ajoutes: 16 })).toBe(
      '9 groupes créés et 16 membres ajoutés.',
    )
    expect(messageGeneration({ crees: 0, membres_ajoutes: 1 })).toBe(
      'Aucun groupe à créer. 1 membre ajouté.',
    )
    expect(messageSynchronisation({ membres_ajoutes: 1 }, 'Réseaux')).toBe(
      '1 membre ajouté à « Réseaux ».',
    )
    expect(messageSynchronisation({ membres_ajoutes: 0 }, 'Réseaux')).toBe(
      '« Réseaux » est déjà à jour.',
    )
  })
})
