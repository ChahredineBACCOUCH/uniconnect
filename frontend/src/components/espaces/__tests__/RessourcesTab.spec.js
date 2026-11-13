import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import {
  creerChapitre,
  creerRessource,
  deplacerRessource,
  modifierRessource,
  recupererChapitres,
  supprimerRessource,
} from '@/api/ressources'
import RessourcesTab from '@/components/espaces/RessourcesTab.vue'
import vuetify from '@/plugins/vuetify'

vi.mock('@/api/ressources', () => ({
  recupererChapitres: vi.fn(),
  creerChapitre: vi.fn(),
  creerRessource: vi.fn(),
  modifierRessource: vi.fn(),
  deplacerRessource: vi.fn(),
  supprimerRessource: vi.fn(),
  telechargerRessource: vi.fn(),
}))

const ESPACE = { id: 7, nom: 'Bases de données · L2 A', role: 'MEMBRE', etat: 'ACTIF' }

function chapitres() {
  return [
    {
      id: 1,
      titre: 'Chapitre 1 – Introduction',
      ordre: 0,
      ressources: [
        {
          id: 11,
          chapitre_id: 1,
          titre: 'Cours 1 – Concepts fondamentaux.pdf',
          description: 'Support de cours',
          mime: 'application/pdf',
          taille: 1_887_437,
          url: null,
          indexee_ia: true,
          cree_le: '2026-09-15T10:00:00+00:00',
        },
        {
          id: 12,
          chapitre_id: 1,
          titre: 'Fiche de révision – Vocabulaire',
          description: null,
          mime: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
          taille: 122_880,
          url: null,
          indexee_ia: false,
          cree_le: '2026-09-18T10:00:00+00:00',
        },
      ],
    },
    {
      id: 2,
      titre: 'Chapitre 2 – Modèle relationnel',
      ordre: 1,
      ressources: [
        {
          id: 21,
          chapitre_id: 2,
          titre: 'Simulateur en ligne',
          description: 'Outil en ligne',
          mime: null,
          taille: null,
          url: 'https://exemple.test/simulateur',
          indexee_ia: false,
          cree_le: '2026-10-06T10:00:00+00:00',
        },
      ],
    },
  ]
}

let wrapper

function monter(role = 'MEMBRE') {
  wrapper = mount(RessourcesTab, {
    props: { espace: { ...ESPACE, role } },
    global: { plugins: [vuetify] },
    attachTo: document.body,
  })
  return wrapper
}

function boutonTexte(texte, racine = document) {
  return [...racine.querySelectorAll('button')].find((element) =>
    element.textContent.includes(texte),
  )
}

function saisir(element, valeur) {
  element.value = valeur
  element.dispatchEvent(new Event('input'))
  element.dispatchEvent(new Event('change'))
}

function elementTexte(selecteur, texte) {
  return [...document.querySelectorAll(selecteur)].find((element) =>
    element.textContent.includes(texte),
  )
}

async function ouvrirActions(titre) {
  await wrapper.find(`button[aria-label="Actions pour ${titre}"]`).trigger('click')
  await flushPromises()
}

describe('RessourcesTab', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    recupererChapitres.mockResolvedValue(chapitres())
  })

  afterEach(() => {
    wrapper?.unmount()
    document.body.innerHTML = ''
  })

  it('affiche les chapitres et leurs ressources pour un membre', async () => {
    monter('MEMBRE')
    await flushPromises()

    expect(recupererChapitres).toHaveBeenCalledWith(7)
    expect(wrapper.text()).toContain('Chapitre 1 – Introduction')
    expect(wrapper.text()).toContain('Cours 1 – Concepts fondamentaux.pdf')
    expect(wrapper.text()).toContain('Fiche de révision – Vocabulaire')
    expect(wrapper.text()).toContain('Simulateur en ligne')
    expect(wrapper.text()).toContain('PDF · 1,8 Mo')
    expect(wrapper.text()).toContain('Word · 120 Ko')

    // Lecture seule : ni actions de gestion, ni badge IA.
    expect(wrapper.text()).not.toContain('Nouveau chapitre')
    expect(wrapper.text()).not.toContain('Ajouter une ressource')
    expect(wrapper.text()).not.toContain("Indexé pour l'IA")
    expect(wrapper.findAll('button[aria-label^="Actions pour"]')).toHaveLength(0)
  })

  it('montre les actions enseignant et le badge IA seulement sur les PDF indexés', async () => {
    monter('PROPRIETAIRE')
    await flushPromises()

    expect(wrapper.text()).toContain('Nouveau chapitre')
    expect(wrapper.text()).toContain('Ajouter une ressource')
    expect(wrapper.text().match(/Indexé pour l'IA/g)).toHaveLength(1)
    expect(wrapper.findAll('button[aria-label^="Actions pour"]')).toHaveLength(3)
  })

  it('filtre les ressources par titre avec la recherche', async () => {
    monter()
    await flushPromises()

    await wrapper.find('.ressources__recherche input').setValue('simulateur')
    await flushPromises()

    expect(wrapper.text()).toContain('Simulateur en ligne')
    expect(wrapper.text()).not.toContain('Cours 1 – Concepts fondamentaux.pdf')
    expect(wrapper.text()).not.toContain('Fiche de révision – Vocabulaire')
  })

  it('filtre les ressources par type avec les chips', async () => {
    monter()
    await flushPromises()

    const chipPdf = wrapper.findAll('.v-chip').find((element) => element.text() === 'PDF')
    await chipPdf.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Cours 1 – Concepts fondamentaux.pdf')
    expect(wrapper.text()).not.toContain('Fiche de révision – Vocabulaire')
    expect(wrapper.text()).not.toContain('Simulateur en ligne')
  })

  it('crée un chapitre depuis le dialogue enseignant', async () => {
    creerChapitre.mockResolvedValue({
      id: 3,
      titre: 'Chapitre 3 – Normalisation',
      ordre: 2,
      ressources: [],
    })

    monter('PROPRIETAIRE')
    await flushPromises()

    await boutonTexte('Nouveau chapitre').click()
    await flushPromises()

    saisir(document.querySelector('.v-dialog input'), 'Chapitre 3 – Normalisation')
    await flushPromises()
    boutonTexte('Créer').click()
    await flushPromises()

    expect(creerChapitre).toHaveBeenCalledWith(7, { titre: 'Chapitre 3 – Normalisation' })
    expect(document.body.textContent).toContain('Chapitre créé.')
    expect(wrapper.text()).toContain('Chapitre 3 – Normalisation')
  })

  it('ajoute une ressource avec un lien dans un chapitre', async () => {
    creerRessource.mockResolvedValue({ id: 31 })

    monter('CO_GESTIONNAIRE')
    await flushPromises()

    await boutonTexte('Ajouter une ressource').click()
    await flushPromises()

    saisir(document.querySelector('.v-dialog input'), 'Nouveau simulateur')
    boutonTexte('Lien', document.querySelector('.v-dialog')).click()
    await flushPromises()

    const champLien = document.querySelectorAll('.v-dialog input')
    saisir(champLien[champLien.length - 1], 'https://exemple.test/nouveau')
    await flushPromises()

    boutonTexte('Ajouter', document.querySelector('.v-dialog')).click()
    await flushPromises()

    expect(creerRessource).toHaveBeenCalledWith(
      1,
      expect.objectContaining({
        titre: 'Nouveau simulateur',
        url: 'https://exemple.test/nouveau',
        fichier: null,
      }),
    )
    expect(document.body.textContent).toContain('Ressource ajoutée.')
  })

  it('renomme une ressource depuis le menu ⋮', async () => {
    modifierRessource.mockResolvedValue({ id: 11 })

    monter('PROPRIETAIRE')
    await flushPromises()

    await ouvrirActions('Cours 1 – Concepts fondamentaux.pdf')
    elementTexte('.v-list-item', 'Renommer').click()
    await flushPromises()

    saisir(document.querySelector('.v-dialog input'), 'Cours 1 – Version corrigée')
    await flushPromises()
    boutonTexte('Enregistrer').click()
    await flushPromises()

    expect(modifierRessource).toHaveBeenCalledWith(11, { titre: 'Cours 1 – Version corrigée' })
    expect(document.body.textContent).toContain('Ressource renommée.')
  })

  it('déplace une ressource vers un autre chapitre', async () => {
    deplacerRessource.mockResolvedValue({ id: 11 })

    monter('PROPRIETAIRE')
    await flushPromises()

    await ouvrirActions('Cours 1 – Concepts fondamentaux.pdf')
    elementTexte('.v-list-item', 'Déplacer vers un chapitre').click()
    await flushPromises()

    document.querySelector('.v-dialog input[type="radio"]').click()
    await flushPromises()
    boutonTexte('Déplacer', document.querySelector('.v-dialog')).click()
    await flushPromises()

    expect(deplacerRessource).toHaveBeenCalledWith(11, 2)
    expect(document.body.textContent).toContain('Ressource déplacée.')
  })

  it('supprime une ressource après confirmation', async () => {
    supprimerRessource.mockResolvedValue()

    monter('PROPRIETAIRE')
    await flushPromises()

    await ouvrirActions('Fiche de révision – Vocabulaire')
    elementTexte('.v-list-item', 'Supprimer').click()
    await flushPromises()

    boutonTexte('Supprimer', document.querySelector('.v-dialog')).click()
    await flushPromises()

    expect(supprimerRessource).toHaveBeenCalledWith(12)
    expect(document.body.textContent).toContain('Ressource supprimée.')
  })

  it('affiche une erreur et permet de réessayer', async () => {
    recupererChapitres
      .mockRejectedValueOnce(new Error('Serveur indisponible'))
      .mockResolvedValueOnce(chapitres())

    monter()
    await flushPromises()

    expect(wrapper.text()).toContain('Serveur indisponible')

    await boutonTexte('Réessayer').click()
    await flushPromises()

    expect(wrapper.text()).toContain('Chapitre 1 – Introduction')
  })
})
