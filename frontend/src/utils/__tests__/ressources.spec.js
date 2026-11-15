import { describe, expect, it } from 'vitest'

import {
  categorieRessource,
  couleurRessource,
  estPdf,
  filtrerRessources,
  formaterTaille,
  iconeRessource,
  libelleTypeRessource,
  nomTelechargement,
} from '@/utils/ressources'

const pdf = {
  id: 1,
  titre: 'Cours 1 – Concepts fondamentaux.pdf',
  mime: 'application/pdf',
  taille: 1_887_437,
  url: null,
}

const word = {
  id: 2,
  titre: 'Fiche de révision',
  mime: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  taille: 122_880,
  url: null,
}

const lien = {
  id: 3,
  titre: 'Simulateur en ligne',
  mime: null,
  taille: null,
  url: 'https://exemple.test/simulateur',
}

describe('utils ressources', () => {
  it('classe une ressource en PDF, document ou lien', () => {
    expect(categorieRessource(pdf)).toBe('PDF')
    expect(categorieRessource(word)).toBe('DOCUMENTS')
    expect(categorieRessource(lien)).toBe('LIENS')
    expect(estPdf(pdf)).toBe(true)
    expect(estPdf(word)).toBe(false)
  })

  it('choisit un libellé, une icône et une couleur par type', () => {
    expect(libelleTypeRessource(pdf)).toBe('PDF')
    expect(libelleTypeRessource(word)).toBe('Word')
    expect(libelleTypeRessource(lien)).toBe('Lien')

    expect(iconeRessource(pdf)).toBe('mdi-file-pdf-box')
    expect(iconeRessource(word)).toBe('mdi-file-word-outline')
    expect(iconeRessource(lien)).toBe('mdi-link-variant')

    expect(couleurRessource(pdf)).toBe('#C62828')
    expect(couleurRessource(word)).toBe('#1E4FB8')
    expect(couleurRessource(lien)).toBe('#00796B')
  })

  it('formate les tailles en octets, Ko et Mo', () => {
    expect(formaterTaille(0)).toBe('0 o')
    expect(formaterTaille(512)).toBe('512 o')
    expect(formaterTaille(120 * 1024)).toBe('120 Ko')
    expect(formaterTaille(1_887_437)).toBe('1,8 Mo')
    expect(formaterTaille(null)).toBe('—')
  })

  it('déduit le nom de téléchargement du titre et du type MIME', () => {
    expect(nomTelechargement(pdf)).toBe('Cours 1 – Concepts fondamentaux.pdf')
    expect(nomTelechargement(word)).toBe('Fiche de révision.docx')
    expect(nomTelechargement({ titre: 'A/B: C?', mime: 'application/pdf' })).toBe('A-B- C-.pdf')
    expect(nomTelechargement({ titre: 'déjà.pdf', mime: 'application/pdf' })).toBe('déjà.pdf')
  })

  it('filtre par titre et par type sans tenir compte de la casse', () => {
    const ressources = [pdf, word, lien]

    expect(filtrerRessources(ressources, 'fiche')).toEqual([word])
    expect(filtrerRessources(ressources, '', 'PDF')).toEqual([pdf])
    expect(filtrerRessources(ressources, '', 'DOCUMENTS')).toEqual([word])
    expect(filtrerRessources(ressources, '', 'LIENS')).toEqual([lien])
    expect(filtrerRessources(ressources, 'cours', 'DOCUMENTS')).toEqual([])
    expect(filtrerRessources(ressources, '', 'TOUT')).toHaveLength(3)
  })
})
