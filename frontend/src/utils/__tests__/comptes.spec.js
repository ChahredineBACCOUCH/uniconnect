import { describe, expect, it } from 'vitest'

import {
  ETATS,
  PROFILS,
  detailEtat,
  detailProfil,
  estDoublon,
  resumerImport,
} from '@/utils/comptes'

/*
 * SCRUM-77 : fonctions pures de l'écran A03.
 */

describe('libellés des profils et des états', () => {
  it('propose les trois profils de la maquette', () => {
    expect(PROFILS.map((profil) => profil.value)).toEqual([
      'ETUDIANT',
      'ENSEIGNANT',
      'ADMINISTRATION',
    ])
  })

  it('propose les cinq états de compte', () => {
    expect(ETATS.map((etat) => etat.value)).toEqual([
      'ACTIF',
      'EN_ATTENTE',
      'NON_CREE',
      'DESACTIVE',
      'SUSPENDU',
    ])
  })

  it('retourne le libellé et la couleur de chaque profil', () => {
    expect(detailProfil('ETUDIANT')).toEqual({ libelle: 'Étudiant', couleur: 'primary' })
    expect(detailProfil('ENSEIGNANT')).toEqual({ libelle: 'Enseignant', couleur: 'secondary' })
    expect(detailProfil('INCONNU')).toEqual({ libelle: 'Membre', couleur: null })
  })

  it('retourne le libellé et la couleur de chaque état', () => {
    expect(detailEtat('ACTIF')).toEqual({ libelle: 'Actif', couleur: 'success' })
    expect(detailEtat('DESACTIVE')).toEqual({ libelle: 'Désactivé', couleur: 'error' })
    expect(detailEtat(null)).toEqual({ libelle: 'Inconnu', couleur: null })
  })
})

describe('détection des doublons dans un aperçu d’import', () => {
  it('reconnaît les motifs de doublon renvoyés par l’API', () => {
    expect(estDoublon('Ce matricule figure déjà dans la liste des comptes autorisés.')).toBe(true)
    expect(estDoublon('Cette adresse e-mail est présente plusieurs fois dans le fichier.')).toBe(
      true,
    )
    expect(estDoublon('Doublon de matricule dans le fichier.')).toBe(true)
  })

  it('ne confond pas une erreur de format avec un doublon', () => {
    expect(estDoublon("L'adresse e-mail est invalide.")).toBe(false)
    expect(estDoublon('')).toBe(false)
    expect(estDoublon(undefined)).toBe(false)
  })
})

describe('résumé de l’aperçu', () => {
  it('sépare les doublons des autres erreurs', () => {
    const resume = resumerImport({
      total: 5,
      valides: 2,
      erreurs: [
        { ligne: 3, motif: 'Ce matricule figure déjà dans la liste des comptes autorisés.' },
        {
          ligne: 4,
          motif: 'Cette adresse e-mail figure déjà dans la liste des comptes autorisés.',
        },
        { ligne: 5, motif: 'La classe est introuvable.' },
      ],
    })

    expect(resume).toEqual({ total: 5, valides: 2, doublons: 2, erreurs: 1, ignores: 3 })
  })

  it('retourne des compteurs à zéro sans aperçu', () => {
    expect(resumerImport(null)).toEqual({
      total: 0,
      valides: 0,
      doublons: 0,
      erreurs: 0,
      ignores: 0,
    })
  })

  it('ne renvoie pas d’ignorés négatifs si l’API est incohérente', () => {
    expect(resumerImport({ total: 1, valides: 3, erreurs: [] }).ignores).toBe(0)
  })
})
