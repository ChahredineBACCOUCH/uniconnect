import { describe, expect, it } from 'vitest'

import {
  ACCEPT_FICHIERS,
  MAX_FICHIERS,
  TAILLE_MAX_MO,
  TAILLE_MAX_OCTETS,
  estFormatAutorise,
  extensionFichier,
  verifierFichiers,
} from '@/utils/fichiers'

function fichier(nom, taille = 1024, type = 'application/pdf') {
  const element = new File(['contenu'], nom, { type })
  Object.defineProperty(element, 'size', { value: taille })
  return element
}

describe('utils fichiers (SCRUM-139)', () => {
  it('lit l’extension sans le point et en minuscules', () => {
    expect(extensionFichier('Rapport.PDF')).toBe('pdf')
    expect(extensionFichier('archive.tar.gz')).toBe('gz')
    expect(extensionFichier('sans-extension')).toBe('')
    expect(extensionFichier('.gitignore')).toBe('')
    expect(extensionFichier(null)).toBe('')
  })

  it('accepte uniquement les formats autorisés', () => {
    expect(estFormatAutorise(fichier('photo.JPG'))).toBe(true)
    expect(estFormatAutorise(fichier('notes.pdf'))).toBe(true)
    expect(estFormatAutorise(fichier('script.exe'))).toBe(false)
    expect(estFormatAutorise(fichier('sans-extension'))).toBe(false)
  })

  it('construit la liste accept avec les formats autorisés', () => {
    expect(ACCEPT_FICHIERS).toContain('.pdf')
    expect(ACCEPT_FICHIERS).toContain('.jpeg')
    expect(ACCEPT_FICHIERS).not.toContain('.exe')
  })

  it('accepte un fichier valide', () => {
    const photo = fichier('photo.png', 2048, 'image/png')

    expect(verifierFichiers([photo])).toEqual({ acceptes: [photo], erreur: null })
  })

  it('refuse un fichier plus gros que 25 Mo', () => {
    const gros = fichier('memoire.pdf', TAILLE_MAX_OCTETS + 1)

    const resultat = verifierFichiers([gros])

    expect(resultat.acceptes).toEqual([])
    expect(resultat.erreur).toBe('« memoire.pdf » dépasse la taille maximale de 25 Mo.')
    expect(TAILLE_MAX_MO).toBe(25)
  })

  it('refuse un format interdit', () => {
    const resultat = verifierFichiers([fichier('virus.exe')])

    expect(resultat.acceptes).toEqual([])
    expect(resultat.erreur).toBe("Le format de « virus.exe » n'est pas autorisé.")
  })

  it('limite le message à cinq fichiers au total', () => {
    const dejaJoints = Array.from({ length: MAX_FICHIERS - 1 }, (_, index) =>
      fichier(`deja-${index}.pdf`),
    )
    const nouveau = fichier('sixieme.pdf')
    const enTrop = fichier('septieme.pdf')

    const resultat = verifierFichiers([nouveau, enTrop], dejaJoints)

    expect(resultat.acceptes).toEqual([nouveau])
    expect(resultat.erreur).toBe('Vous ne pouvez joindre que 5 fichiers au maximum.')
  })

  it('conserve les fichiers valides quand un autre est refusé', () => {
    const valide = fichier('notes.pdf')
    const resultat = verifierFichiers([fichier('virus.exe'), valide])

    expect(resultat.acceptes).toEqual([valide])
    expect(resultat.erreur).toContain('virus.exe')
  })
})
