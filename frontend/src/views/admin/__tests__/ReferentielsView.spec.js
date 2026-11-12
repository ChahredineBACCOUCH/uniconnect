import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import {
  recupererAnnees,
  recupererEnseignants,
  recupererOptions,
  recupererReferentiel,
} from '@/api/referentiels'
import ReferentielTable from '@/components/admin/ReferentielTable.vue'
import vuetify from '@/plugins/vuetify'
import ReferentielsView from '@/views/admin/ReferentielsView.vue'

/*
 * SCRUM-80 : écran A02 et ses cinq onglets.
 */
vi.mock('@/api/referentiels', () => ({
  recupererReferentiel: vi.fn(),
  recupererOptions: vi.fn(),
  recupererAnnees: vi.fn(),
  recupererEnseignants: vi.fn(),
  creerReferentiel: vi.fn(),
  modifierReferentiel: vi.fn(),
  desactiverReferentiel: vi.fn(),
  supprimerReferentiel: vi.fn(),
}))

const MATIERE = {
  id: 9,
  code: 'BD',
  libelle: 'Bases de données',
  semestre: 1,
  actif: true,
  classes: [{ id: 3, code: 'L2-A', libelle: 'L2 Info A' }],
  enseignants: [{ id: 7, nom: 'Ben Ali', prenom: 'Sana', email: 'sana@univ.tn' }],
}

let derniere = null

afterEach(() => {
  derniere?.unmount()
  derniere = null
})

function monter() {
  const pinia = createPinia()
  setActivePinia(pinia)

  derniere = mount(ReferentielsView, {
    global: {
      plugins: [vuetify, pinia],
    },
  })

  return derniere
}

describe('écran Référentiels académiques', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    recupererReferentiel.mockImplementation(async (endpoint) => {
      if (endpoint === '/admin/matieres') {
        return { data: [MATIERE], meta: { page: 1, par_page: 10, total: 1 } }
      }

      return {
        data: [{ id: 1, code: 'INFO', libelle: 'Informatique', actif: true }],
        meta: { page: 1, par_page: 10, total: 1 },
      }
    })

    recupererOptions.mockImplementation(async (endpoint) => {
      if (endpoint === '/admin/departements') {
        return [{ id: 1, code: 'INFO', libelle: 'Informatique', actif: true }]
      }
      if (endpoint === '/admin/filieres') {
        return [{ id: 2, code: 'LI', libelle: 'Licence Informatique', actif: true }]
      }
      if (endpoint === '/admin/niveaux') {
        return [{ id: 4, code: 'L2', libelle: 'Licence 2', actif: true }]
      }
      if (endpoint === '/admin/classes') {
        return [
          {
            id: 3,
            code: 'L2-A',
            libelle: 'L2 Info A',
            actif: true,
            annee: { id: 6, libelle: '2026/2027' },
          },
        ]
      }

      return []
    })

    recupererAnnees.mockResolvedValue([
      { id: 6, libelle: '2026/2027', active: true },
      { id: 5, libelle: '2025/2026', active: false },
    ])
    recupererEnseignants.mockResolvedValue([
      { id: 7, nom: 'Ben Ali', prenom: 'Sana', email: 'sana@univ.tn' },
    ])
  })

  it('affiche les cinq onglets et charge le premier référentiel', async () => {
    const wrapper = monter()
    await flushPromises()

    expect(wrapper.text()).toContain('Référentiels académiques')
    expect(wrapper.text()).toContain('Départements')
    expect(wrapper.text()).toContain('Filières')
    expect(wrapper.text()).toContain('Niveaux')
    expect(wrapper.text()).toContain('Classes')
    expect(wrapper.text()).toContain('Matières')
    expect(wrapper.text()).toContain('Informatique')

    expect(recupererOptions).toHaveBeenCalledWith('/admin/departements')
    expect(recupererOptions).toHaveBeenCalledWith('/admin/filieres')
    expect(recupererOptions).toHaveBeenCalledWith('/admin/niveaux')
    expect(recupererOptions).toHaveBeenCalledWith('/admin/classes')
    expect(recupererAnnees).toHaveBeenCalled()
    expect(recupererEnseignants).toHaveBeenCalled()
    expect(recupererReferentiel).toHaveBeenCalledWith(
      '/admin/departements',
      expect.objectContaining({ page: 1, par_page: 10 }),
    )
  })

  it('bascule sur les matières avec des sélecteurs multiples', async () => {
    const wrapper = monter()
    await flushPromises()

    wrapper.vm.onglet = 'matieres'
    await flushPromises()

    expect(recupererReferentiel).toHaveBeenCalledWith(
      '/admin/matieres',
      expect.objectContaining({ page: 1, par_page: 10 }),
    )

    const table = wrapper.findComponent(ReferentielTable)
    expect(table.props('endpoint')).toBe('/admin/matieres')
    expect(wrapper.text()).toContain('Bases de données')
    expect(wrapper.text()).toContain('L2-A')
    expect(wrapper.text()).toContain('Sana Ben Ali')

    const champs = table.props('champs')
    const classes = champs.find((champ) => champ.cle === 'classe_ids')
    const enseignants = champs.find((champ) => champ.cle === 'enseignant_ids')

    expect(classes).toMatchObject({
      type: 'autocomplete',
      multiple: true,
      items: [{ title: 'L2-A — L2 Info A (2026/2027)', value: 3 }],
    })
    expect(enseignants).toMatchObject({
      type: 'autocomplete',
      multiple: true,
      items: [{ title: 'Sana Ben Ali', value: 7 }],
    })
  })
})
