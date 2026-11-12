import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import {
  analyserImport,
  creerCompte,
  recupererClasses,
  recupererComptes,
  validerImport,
} from '@/api/comptes'
import vuetify from '@/plugins/vuetify'
import ComptesView from '@/views/admin/ComptesView.vue'

/*
 * SCRUM-77 : écran A03 « Comptes autorisés ».
 * L'API est simulée : on vérifie les requêtes envoyées et le rendu.
 */
vi.mock('@/api/comptes', () => ({
  recupererComptes: vi.fn(),
  recupererClasses: vi.fn(),
  creerCompte: vi.fn(),
  modifierCompte: vi.fn(),
  desactiverCompte: vi.fn(),
  supprimerCompte: vi.fn(),
  analyserImport: vi.fn(),
  validerImport: vi.fn(),
  telechargerModele: vi.fn(),
}))

const COMPTES = [
  {
    id: 1,
    matricule: 'MAT-001',
    nom: 'Ben Salah',
    prenom: 'Yassine',
    email: 'yassine.bensalah@univ.tn',
    profil: 'ETUDIANT',
    classe: { id: 2, libelle: 'L2 Informatique A' },
    etat: 'EN_ATTENTE',
  },
  {
    id: 2,
    matricule: 'MAT-002',
    nom: 'Diallo',
    prenom: 'Fatou',
    email: 'fatou.diallo@univ.tn',
    profil: 'ENSEIGNANT',
    classe: null,
    etat: 'ACTIF',
  },
]

const APERCU = {
  total: 3,
  valides: 1,
  lignes: [
    {
      ligne: 2,
      matricule: 'MAT-010',
      nom: 'Trabelsi',
      prenom: 'Sami',
      email: 'sami.trabelsi@univ.tn',
      profil: 'ETUDIANT',
      classe_code: 'L2-INFO-A',
    },
  ],
  erreurs: [
    { ligne: 3, motif: 'Ce matricule figure déjà dans la liste des comptes autorisés.' },
    { ligne: 4, motif: 'La classe L9 est introuvable.' },
  ],
}

let derniere = null

/*
 * Vuetify lit `visualViewport` directement (et non `window.visualViewport`) :
 * jsdom ne l'expose pas, ce qui faisait échouer l'ouverture des dialogues.
 */
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
  const pinia = createPinia()
  setActivePinia(pinia)

  derniere = mount(ComptesView, {
    global: {
      plugins: [vuetify, pinia],
    },
  })

  return derniere
}

/** Texte de l'écran, y compris les dialogues téléportés dans <body>. */
function texte(wrapper) {
  return `${wrapper.text()} ${document.body.textContent ?? ''}`
}

describe('écran Comptes autorisés', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    recupererComptes.mockResolvedValue({ data: COMPTES, meta: { page: 1, par_page: 10, total: 2 } })
    recupererClasses.mockResolvedValue([{ id: 2, code: 'L2-INFO-A', libelle: 'L2 Informatique A' }])
  })

  it('charge la première page et l’affiche', async () => {
    const wrapper = monter()
    await flushPromises()

    expect(recupererComptes).toHaveBeenCalledWith({
      page: 1,
      par_page: 10,
      q: undefined,
      profil: undefined,
      classe_id: undefined,
      etat: undefined,
    })
    expect(recupererClasses).toHaveBeenCalled()

    expect(wrapper.text()).toContain('Comptes autorisés')
    expect(wrapper.text()).toContain('MAT-001')
    expect(wrapper.text()).toContain('Ben Salah')
    expect(wrapper.text()).toContain('L2 Informatique A')
    expect(wrapper.text()).toContain('En attente')
    expect(wrapper.text()).toContain('2 entrées')
  })

  it('recharge la liste quand un filtre change', async () => {
    const wrapper = monter()
    await flushPromises()

    recupererComptes.mockClear()
    wrapper.vm.profil = 'ETUDIANT'
    await flushPromises()

    expect(recupererComptes).toHaveBeenCalledWith(
      expect.objectContaining({ page: 1, profil: 'ETUDIANT' }),
    )
  })

  it('ajoute un compte autorisé depuis le dialogue', async () => {
    creerCompte.mockResolvedValue({ id: 3 })

    const wrapper = monter()
    await flushPromises()

    wrapper.vm.ouvrirAjout()
    await flushPromises()
    expect(texte(wrapper)).toContain('Ajouter un compte autorisé')

    wrapper.vm.champs.matricule = 'MAT-003'
    wrapper.vm.champs.nom = 'Trabelsi'
    wrapper.vm.champs.prenom = 'Sami'
    wrapper.vm.champs.email = 'sami.trabelsi@univ.tn'
    wrapper.vm.champs.profil = 'ETUDIANT'
    wrapper.vm.champs.classe_id = 2

    await flushPromises()
    await wrapper.vm.enregistrer()
    await flushPromises()

    expect(creerCompte).toHaveBeenCalledWith({
      matricule: 'MAT-003',
      nom: 'Trabelsi',
      prenom: 'Sami',
      email: 'sami.trabelsi@univ.tn',
      profil: 'ETUDIANT',
      classe_id: 2,
    })
    expect(wrapper.vm.dialogue).toBe(false)
    expect(recupererComptes).toHaveBeenCalledTimes(2)
  })

  it('analyse le fichier puis importe les lignes valides', async () => {
    analyserImport.mockResolvedValue(APERCU)
    validerImport.mockResolvedValue({ importes: 1, ignores: 2, erreurs: 2, import_id: 4 })

    const wrapper = monter()
    await flushPromises()

    wrapper.vm.ouvrirImport()
    await flushPromises()
    expect(texte(wrapper)).toContain('Choisir le fichier')

    wrapper.vm.fichier = new File(['xlsx'], 'comptes.xlsx')
    await wrapper.vm.analyserLeFichier()
    await flushPromises()

    expect(analyserImport).toHaveBeenCalledWith(expect.any(File))

    // Compteurs de la maquette : valides / doublons / erreurs.
    expect(texte(wrapper)).toContain('ligne valide')
    expect(texte(wrapper)).toContain('doublon ignoré')
    expect(texte(wrapper)).toContain('ligne en erreur')
    expect(texte(wrapper)).toContain('Ligne 3 : Ce matricule figure déjà')

    wrapper.vm.etapeImport = 2
    await flushPromises()
    expect(texte(wrapper)).toContain("Confirmer l'import")

    await wrapper.vm.lancerImport()
    await flushPromises()

    expect(validerImport).toHaveBeenCalledWith(APERCU.lignes, 'comptes.xlsx')
    expect(wrapper.vm.importDialogue).toBe(false)
    expect(texte(wrapper)).toContain('1 compte importé')
    expect(texte(wrapper)).toContain('2 lignes ignorées')
  })

  it('refuse d’importer quand aucune ligne n’est valide', async () => {
    analyserImport.mockResolvedValue({ total: 1, valides: 0, lignes: [], erreurs: [] })

    const wrapper = monter()
    await flushPromises()

    wrapper.vm.ouvrirImport()
    wrapper.vm.fichier = new File(['xlsx'], 'vide.xlsx')
    await wrapper.vm.analyserLeFichier()
    await flushPromises()

    expect(wrapper.vm.peutContinuer).toBe(false)

    await wrapper.vm.lancerImport()
    expect(validerImport).not.toHaveBeenCalled()
  })
})
