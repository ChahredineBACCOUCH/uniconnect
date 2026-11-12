import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'

import {
  apercuGeneration,
  archiverGroupe,
  genererGroupes,
  recupererFiltresGroupes,
  recupererGroupes,
  synchroniserGroupe,
} from '@/api/groupes'
import vuetify from '@/plugins/vuetify'
import GroupesView from '@/views/admin/GroupesView.vue'

/*
 * SCRUM-83 : écran A06 « Groupes organisationnels ».
 */
vi.mock('@/api/groupes', () => ({
  recupererGroupes: vi.fn(),
  recupererFiltresGroupes: vi.fn(),
  apercuGeneration: vi.fn(),
  genererGroupes: vi.fn(),
  synchroniserGroupe: vi.fn(),
  archiverGroupe: vi.fn(),
}))

const ANNEES = { id: 7, libelle: '2026/2027' }

const GROUPES = [
  {
    id: 1,
    nom: 'L2 Informatique A',
    type: 'CLASSE',
    etat: 'ACTIF',
    classe: { id: 1, code: 'L2-INFO-A' },
    matiere: null,
    classes: [{ id: 1, code: 'L2-INFO-A' }],
    proprietaire: { id: 9, nom_complet: 'Fatou Diallo' },
    nombre_membres: 2,
  },
  {
    id: 2,
    nom: 'Algorithmique avancée',
    type: 'MATIERE',
    etat: 'ACTIF',
    classe: null,
    matiere: { id: 4, code: 'INF201' },
    classes: [
      { id: 1, code: 'L2-INFO-A' },
      { id: 2, code: 'L2-INFO-B' },
      { id: 3, code: 'L3-INFO' },
    ],
    proprietaire: { id: 10, nom_complet: 'Sophie Martin' },
    nombre_membres: 35,
  },
  {
    id: 3,
    nom: 'Bases de données',
    type: 'MATIERE',
    etat: 'ARCHIVE',
    classe: null,
    matiere: { id: 5, code: 'INF202' },
    classes: [{ id: 1, code: 'L2-INFO-A' }],
    proprietaire: { id: 10, nom_complet: 'Sophie Martin' },
    nombre_membres: 34,
  },
]

const APERCU = {
  annee: ANNEES,
  total: 8,
  a_creer: [{ nom: 'L3 Informatique' }, { nom: 'Réseaux' }],
  deja_existants: 6,
  membres_a_ajouter: 5,
}

const FILTRES = {
  annee: ANNEES,
  filieres: [{ id: 1, code: 'GL', libelle: 'Génie logiciel' }],
  niveaux: [{ id: 1, code: 'L2', libelle: 'Licence 2' }],
  classes: [{ id: 1, code: 'L2-INFO-A', libelle: 'L2 Informatique A' }],
  enseignants: [{ id: 10, nom: 'Martin', prenom: 'Sophie', nom_complet: 'Sophie Martin' }],
}

let derniere = null

afterEach(() => {
  derniere?.unmount()
  derniere = null
  document.body.innerHTML = ''
})

function monter() {
  const pinia = createPinia()
  setActivePinia(pinia)

  derniere = mount(GroupesView, {
    global: {
      plugins: [vuetify, pinia],
      stubs: { 'router-link': { template: '<a><slot /></a>' } },
    },
  })

  return derniere
}

describe('écran Groupes organisationnels', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    recupererGroupes.mockResolvedValue({ annee: ANNEES, groupes: GROUPES, total: 3 })
    recupererFiltresGroupes.mockResolvedValue(FILTRES)
    apercuGeneration.mockResolvedValue(APERCU)
    genererGroupes.mockResolvedValue({ crees: 2, deja_existants: 6, membres_ajoutes: 5 })
    synchroniserGroupe.mockResolvedValue({ membres_ajoutes: 1 })
    archiverGroupe.mockResolvedValue({ groupe: { ...GROUPES[0], etat: 'ARCHIVE' } })
  })

  it('affiche l’année active et les groupes avec leur type, propriétaire et état', async () => {
    const wrapper = monter()
    await flushPromises()

    const texte = wrapper.text()

    expect(texte).toContain('Groupes organisationnels')
    expect(texte).toContain('Année 2026/2027')
    expect(texte).toContain('3 groupes générés depuis les référentiels')
    expect(texte).toContain('L2 Informatique A')
    expect(texte).toContain('Classe')
    expect(texte).toContain('Matière')
    expect(texte).toContain('L2-INFO-A, L2-INFO-B, L3-INFO')
    expect(texte).toContain('Fatou Diallo')
    expect(texte).toContain('Sophie Martin')
    expect(texte).toContain('35')
    expect(texte).toContain('Actif')
    expect(texte).toContain('Archivé')
    expect(texte).toContain('Synchroniser')
    expect(texte).toContain('Archiver')
  })

  it('recharge la liste quand un filtre change', async () => {
    const wrapper = monter()
    await flushPromises()

    wrapper.vm.filtres.filiere_id = 1
    await wrapper.vm.charger()
    await flushPromises()

    expect(recupererGroupes).toHaveBeenLastCalledWith(
      expect.objectContaining({ filiere_id: 1 }),
    )
  })

  it('affiche un avertissement quand aucune année n’est active', async () => {
    recupererGroupes.mockResolvedValue({ annee: null, groupes: [], total: 0 })
    recupererFiltresGroupes.mockResolvedValue({
      annee: null,
      filieres: [],
      niveaux: [],
      classes: [],
      enseignants: [],
    })

    const wrapper = monter()
    await flushPromises()

    expect(wrapper.text()).toContain('Aucune année active')
    expect(wrapper.text()).toContain("Aucune année universitaire n'est active")
  })

  it('ouvre le dialogue de génération avec l’aperçu', async () => {
    const wrapper = monter()
    await flushPromises()

    await wrapper.vm.ouvrirGeneration()
    await flushPromises()
    await nextTick()

    expect(apercuGeneration).toHaveBeenCalled()
    expect(wrapper.vm.dialogueGeneration).toBe(true)
    expect(wrapper.vm.apercu).toEqual(APERCU)
    expect(document.body.textContent).toContain('Générer les groupes de 2026/2027')
    expect(document.body.textContent).toContain('2 groupes à créer, 6 existants')
    expect(document.body.textContent).toContain('Générer 2 groupes')
  })

  it('affiche le message 422 de l’API dans le dialogue', async () => {
    const message = "Aucune année universitaire n'est active : activez une année avant de générer les groupes."
    apercuGeneration.mockRejectedValueOnce({ response: { data: { message } } })

    const wrapper = monter()
    await flushPromises()

    await wrapper.vm.ouvrirGeneration()
    await flushPromises()

    expect(wrapper.vm.dialogueGeneration).toBe(true)
    expect(wrapper.vm.apercu).toBeNull()
    expect(wrapper.vm.erreurGeneration).toBe(message)
  })

  it('génère les groupes après confirmation puis recharge la liste', async () => {
    const wrapper = monter()
    await flushPromises()

    wrapper.vm.apercu = APERCU
    wrapper.vm.dialogueGeneration = true
    const appelsAvant = recupererGroupes.mock.calls.length

    await wrapper.vm.confirmerGeneration()
    await flushPromises()

    expect(genererGroupes).toHaveBeenCalled()
    expect(wrapper.vm.dialogueGeneration).toBe(false)
    expect(wrapper.vm.derniereGeneration).toBe('2 groupes créés et 5 membres ajoutés.')
    expect(wrapper.vm.snackbar).toMatchObject({
      visible: true,
      message: '2 groupes créés et 5 membres ajoutés.',
    })
    expect(recupererGroupes.mock.calls.length).toBeGreaterThan(appelsAvant)
  })

  it('synchronise un groupe et annonce les membres ajoutés', async () => {
    const wrapper = monter()
    await flushPromises()

    await wrapper.vm.synchroniser(GROUPES[1])
    await flushPromises()

    expect(synchroniserGroupe).toHaveBeenCalledWith(2)
    expect(wrapper.vm.snackbar).toMatchObject({
      visible: true,
      message: '1 membre ajouté à « Algorithmique avancée ».',
    })
  })

  it('archive un groupe après confirmation', async () => {
    const wrapper = monter()
    await flushPromises()

    wrapper.vm.demanderArchivage(GROUPES[0])
    await nextTick()

    expect(wrapper.vm.groupeAArchiver).toEqual(GROUPES[0])

    await wrapper.vm.confirmerArchivage()
    await flushPromises()

    expect(archiverGroupe).toHaveBeenCalledWith(1)
    expect(wrapper.vm.groupeAArchiver).toBeNull()
    expect(wrapper.vm.snackbar).toMatchObject({
      visible: true,
      message: '« L2 Informatique A » archivé.',
    })
  })

  it('signale un échec de synchronisation sans casser l’écran', async () => {
    synchroniserGroupe.mockRejectedValueOnce({
      response: { status: 409, data: { message: 'Un groupe archivé ne peut pas être synchronisé.' } },
    })

    const wrapper = monter()
    await flushPromises()

    await wrapper.vm.synchroniser(GROUPES[2])
    await flushPromises()

    expect(wrapper.vm.snackbar).toMatchObject({
      visible: true,
      color: 'error',
      message: 'Un groupe archivé ne peut pas être synchronisé.',
    })
    expect(wrapper.vm.actionEnCours).toBeNull()
  })
})
