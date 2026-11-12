import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'

import {
  creerReferentiel,
  desactiverReferentiel,
  recupererReferentiel,
  supprimerReferentiel,
} from '@/api/referentiels'
import ReferentielTable from '@/components/admin/ReferentielTable.vue'
import vuetify from '@/plugins/vuetify'

/*
 * SCRUM-80 : tableau générique utilisé par les cinq onglets de l'écran A02.
 */
vi.mock('@/api/referentiels', () => ({
  recupererReferentiel: vi.fn(),
  creerReferentiel: vi.fn(),
  modifierReferentiel: vi.fn(),
  desactiverReferentiel: vi.fn(),
  supprimerReferentiel: vi.fn(),
}))

const MESSAGE_UTILISE = 'Impossible de supprimer : élément utilisé. Vous pouvez le désactiver.'

const DEPARTEMENTS = [
  { id: 1, code: 'INFO', libelle: 'Informatique', actif: true },
  { id: 2, code: 'MATH', libelle: 'Mathématiques', actif: false },
]

const COLONNES = [
  { title: 'Code', key: 'code' },
  { title: 'Libellé', key: 'libelle' },
]

const CHAMPS = [
  { cle: 'code', libelle: 'Code', type: 'texte', obligatoire: true, max: 20 },
  { cle: 'libelle', libelle: 'Libellé', type: 'texte', obligatoire: true, max: 120 },
]

let derniere = null

// Vuetify lit `visualViewport` directement : jsdom ne l'expose pas.
beforeAll(() => {
  globalThis.visualViewport = {
    width: 1024,
    height: 768,
    offsetLeft: 0,
    offsetTop: 0,
    scale: 1,
    addEventListener: () => {},
    removeEventListener: () => {},
  }
})

afterEach(() => {
  derniere?.unmount()
  derniere = null
})

function monter() {
  derniere = mount(ReferentielTable, {
    props: {
      endpoint: '/admin/departements',
      colonnes: COLONNES,
      champs: CHAMPS,
      titreAjout: 'Ajouter un département',
      titreModification: 'Modifier le département',
    },
    global: {
      plugins: [vuetify],
    },
  })

  return derniere
}

/** Texte de l'écran, y compris les dialogues téléportés dans <body>. */
function texte(wrapper) {
  return `${wrapper.text()} ${document.body.textContent ?? ''}`
}

describe('ReferentielTable', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    recupererReferentiel.mockResolvedValue({
      data: DEPARTEMENTS,
      meta: { page: 1, par_page: 10, total: 2 },
    })
  })

  it('charge et affiche les éléments avec leur état', async () => {
    const wrapper = monter()
    await flushPromises()

    expect(recupererReferentiel).toHaveBeenCalledWith('/admin/departements', {
      page: 1,
      par_page: 10,
      q: undefined,
    })
    expect(wrapper.text()).toContain('INFO')
    expect(wrapper.text()).toContain('Informatique')
    expect(wrapper.text()).toContain('Actif')
    expect(wrapper.text()).toContain('Désactivé')
    expect(wrapper.text()).toContain('Ajouter un département')
  })

  it('relance la recherche sur le serveur', async () => {
    const wrapper = monter()
    await flushPromises()

    recupererReferentiel.mockClear()
    wrapper.vm.rechercheAppliquee = 'info'
    await flushPromises()

    expect(recupererReferentiel).toHaveBeenCalledWith(
      '/admin/departements',
      expect.objectContaining({ page: 1, q: 'info' }),
    )
  })

  it('ajoute un élément depuis le dialogue généré', async () => {
    creerReferentiel.mockResolvedValue({ id: 3, code: 'GEST', libelle: 'Gestion' })

    const wrapper = monter()
    await flushPromises()

    wrapper.vm.ouvrirAjout()
    await flushPromises()
    expect(texte(wrapper)).toContain('Ajouter un département')

    wrapper.vm.valeurs.code = 'GEST'
    wrapper.vm.valeurs.libelle = 'Gestion'
    await flushPromises()

    await wrapper.vm.enregistrer()
    await flushPromises()

    expect(creerReferentiel).toHaveBeenCalledWith('/admin/departements', {
      code: 'GEST',
      libelle: 'Gestion',
    })
    expect(wrapper.vm.dialogue).toBe(false)
    expect(recupererReferentiel).toHaveBeenCalledTimes(2)
  })

  it('désactive un élément après confirmation', async () => {
    desactiverReferentiel.mockResolvedValue({ id: 1, actif: false })

    const wrapper = monter()
    await flushPromises()

    wrapper.vm.demanderAction('desactiver', DEPARTEMENTS[0])
    await flushPromises()
    expect(texte(wrapper)).toContain('Désactiver cet élément ?')

    await wrapper.vm.confirmerAction()
    await flushPromises()

    expect(desactiverReferentiel).toHaveBeenCalledWith('/admin/departements', 1)
    expect(wrapper.vm.confirmation).toBe(null)
    expect(wrapper.vm.snackbar.message).toBe('Élément désactivé.')
  })

  it('affiche le message du 409 dans une snackbar', async () => {
    supprimerReferentiel.mockRejectedValue({
      response: { status: 409, data: { message: MESSAGE_UTILISE } },
    })

    const wrapper = monter()
    await flushPromises()

    wrapper.vm.demanderAction('supprimer', DEPARTEMENTS[0])
    await flushPromises()

    await wrapper.vm.confirmerAction()
    await flushPromises()

    expect(supprimerReferentiel).toHaveBeenCalledWith('/admin/departements', 1)
    expect(wrapper.vm.snackbar.visible).toBe(true)
    expect(wrapper.vm.snackbar.color).toBe('error')
    expect(wrapper.vm.snackbar.message).toBe(MESSAGE_UTILISE)
    expect(wrapper.vm.confirmation).toBe(null)
  })
})
