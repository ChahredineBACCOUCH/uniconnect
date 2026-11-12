import { describe, expect, it } from 'vitest'

import { initiales, nomComplet, sousTitreProfil } from '@/utils/utilisateur'

const sophie = { prenom: 'Sophie', nom: 'Martin', profil: 'ENSEIGNANT', classe: null }
const amine = {
  prenom: 'Amine',
  nom: 'Benali',
  profil: 'ETUDIANT',
  classe: { id: 1, libelle: 'L2 Info – Groupe A' },
}

describe('affichage de l’utilisateur connecté', () => {
  it('donne le nom complet', () => {
    expect(nomComplet(sophie)).toBe('Sophie Martin')
    expect(nomComplet(null)).toBe('')
  })

  it('donne les initiales', () => {
    expect(initiales(sophie)).toBe('SM')
    expect(initiales({ prenom: 'élise', nom: '' })).toBe('É')
    expect(initiales(null)).toBe('?')
  })

  it('donne le profil, avec la classe pour un étudiant', () => {
    expect(sousTitreProfil(sophie)).toBe('Enseignant')
    expect(sousTitreProfil(amine)).toBe('Étudiant · L2 Info – Groupe A')
    expect(sousTitreProfil(null)).toBe('')
  })
})
