import { describe, expect, it } from 'vitest'

import {
  badgeAuteur,
  estGestionnaire,
  formaterExpiration,
  fusionnerMessages,
  grouperMessagesParJour,
  libelleDuree,
  libelleVisibilite,
  messageErreur,
  nomAuteur,
  optionsDuree,
  peutPublier,
} from '@/utils/espaces'

describe('utilitaires de l’espace', () => {
  it('reconnaît les rôles qui peuvent publier en mode annonce', () => {
    expect(estGestionnaire('PROPRIETAIRE')).toBe(true)
    expect(estGestionnaire('CO_GESTIONNAIRE')).toBe(true)
    expect(estGestionnaire('MEMBRE')).toBe(false)

    expect(peutPublier({ mode_annonce: false, role: 'MEMBRE', etat: 'ACTIF' })).toBe(true)
    expect(peutPublier({ mode_annonce: true, role: 'MEMBRE', etat: 'ACTIF' })).toBe(false)
    expect(peutPublier({ mode_annonce: true, role: 'PROPRIETAIRE', etat: 'ACTIF' })).toBe(true)
    expect(peutPublier({ mode_annonce: false, role: 'MEMBRE', etat: 'ARCHIVE' })).toBe(false)
  })

  it('formate les durées et la visibilité', () => {
    expect(libelleDuree(1)).toBe('1 heure')
    expect(libelleDuree(24)).toBe('24 heures')
    expect(libelleDuree(168)).toBe('7 jours')
    expect(libelleDuree(null)).toBe('durée illimitée')
    expect(libelleVisibilite(24)).toBe('Messages visibles 24 heures')
    expect(libelleVisibilite(null)).toBe('Messages sans expiration')
  })

  it('ne propose que des durées plus courtes que celle de l’espace', () => {
    const options = optionsDuree({
      duree_courte_autorisee: true,
      duree_visibilite_heures: 24,
    })

    expect(options.map((option) => option.value)).toEqual([null, 1, 6, 12])
    expect(options[0].title).toContain('24 heures')

    expect(optionsDuree({ duree_courte_autorisee: false })).toEqual([])
  })

  it('fusionne les messages sans doublon et dans l’ordre des identifiants', () => {
    const existants = [
      { id: 1, contenu: 'Un' },
      { id: 2, contenu: 'Deux' },
    ]

    const fusion = fusionnerMessages(existants, [
      { id: 2, contenu: 'Deux corrigé' },
      { id: 3, contenu: 'Trois' },
    ])

    expect(fusion.map((message) => message.id)).toEqual([1, 2, 3])
    expect(fusion[1].contenu).toBe('Deux corrigé')
  })

  it('insère un séparateur de date avant chaque nouveau jour', () => {
    const maintenant = new Date(2026, 10, 10, 12, 0, 0)
    const messages = [
      { id: 1, contenu: 'Un', cree_le: new Date(2026, 10, 9, 16, 0, 0).toISOString() },
      { id: 2, contenu: 'Deux', cree_le: new Date(2026, 10, 9, 17, 0, 0).toISOString() },
      { id: 3, contenu: 'Trois', cree_le: new Date(2026, 10, 10, 9, 0, 0).toISOString() },
    ]

    const entrees = grouperMessagesParJour(messages, maintenant)

    expect(entrees.map((entree) => entree.type)).toEqual([
      'jour',
      'message',
      'message',
      'jour',
      'message',
    ])
    expect(entrees[0].libelle).toBe('Hier')
    expect(entrees[3].libelle).toBe("Aujourd'hui")
  })

  it('affiche l’expiration avec le jour et l’heure', () => {
    const maintenant = new Date(2026, 10, 10, 12, 0, 0)

    expect(formaterExpiration(new Date(2026, 10, 10, 18, 5, 0).toISOString(), maintenant)).toBe(
      "Expire aujourd'hui à 18:05",
    )
    expect(formaterExpiration(new Date(2026, 10, 11, 18, 5, 0).toISOString(), maintenant)).toBe(
      'Expire le 11/11 à 18:05',
    )
    expect(formaterExpiration(null)).toBeNull()
  })

  it('affiche « Vous » pour l’auteur connecté et un badge pour les enseignants', () => {
    const auteur = { id: 4, prenom: 'Sophie', nom: 'Martin', profil: 'ENSEIGNANT', badge: 'Enseignant' }

    expect(nomAuteur(auteur, 4)).toBe('Vous')
    expect(nomAuteur(auteur, 9)).toBe('Sophie Martin')
    expect(badgeAuteur(auteur)).toBe('Enseignant')
    expect(badgeAuteur({ ...auteur, profil: 'ETUDIANT', badge: 'Étudiant' })).toBeNull()
  })

  it('extrait le premier message d’une erreur de validation', () => {
    const validation = {
      errors: { contenu: ['Le contenu du message est obligatoire.'] },
      message: 'Données invalides.',
    }

    expect(messageErreur(validation)).toBe('Le contenu du message est obligatoire.')
    expect(messageErreur(new Error('Réseau'))).toBe('Réseau')
  })
})
