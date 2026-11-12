import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'

import { activerAnnee, creerAnnee, modifierAnnee, recupererAnnees } from '@/api/annees'
import vuetify from '@/plugins/vuetify'
import AnneesView from '@/views/admin/AnneesView.vue'

/*
 * SCRUM-73 : écran A01 « Années universitaires ».
 */
vi.mock('@/api/annees', () => ({
  recupererAnnees: vi.fn(),
  creerAnnee: vi.fn(),
  modifierAnnee: vi.fn(),
  activerAnnee: vi.fn(),
}))

const ANNEES = [
  {
    id: 8,
    libelle: '2027/2028',
    date_debut: '2027-09-01',
    date_fin: '2028-07-31',
    active: false,
    nombre_groupes: 0,
    nombre_groupes_archives: 0,
  },
  {
    id: 7,
    libelle: '2026/2027',
    date_debut: '2026-09-01',
    date_fin: '2027-07-31',
    active: true,
    nombre_groupes: 286,
    nombre_groupes_archives: 0,
  },
  {
    id: 6,
    libelle: '2025/2026',
    date_debut: '2025-09-01',
    date_fin: '2026-07-31',
    active: false,
    nombre_groupes: 271,
    nombre_groupes_archives: 271,
  },
]

let derniere = null

afterEach(() => {
  derniere?.unmount()
  derniere = null
})

function monter() {
  const pinia = createPinia()
  setActivePinia(pinia)

  derniere = mount(AnneesView, {
    global: {
      plugins: [vuetify, pinia],
    },
  })

  return derniere
}

describe('écran Années universitaires', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    recupererAnnees.mockResolvedValue(ANNEES)
    creerAnnee.mockResolvedValue({ id: 9, libelle: '2028/2029' })
    modifierAnnee.mockResolvedValue({ id: 8, libelle: '2027/2028' })
    activerAnnee.mockResolvedValue({ id: 8, active: true })
  })

  it('affiche l’année active, les dates, les états et les groupes', async () => {
    const wrapper = monter()
    await flushPromises()

    const texte = wrapper.text()

    expect(texte).toContain('Années universitaires')
    expect(texte).toContain('Plateforme ouverte')
    expect(texte).toContain('2026/2027')
    expect(texte).toContain('01/09/2026')
    expect(texte).toContain('31/07/2027')
    expect(texte).toContain('Active')
    expect(texte).toContain('Inactive')
    expect(texte).toContain('286')
    expect(texte).toContain('271 (archivés)')
    expect(texte).toContain('—')
  })

  it('affiche une plateforme fermée quand aucune année n’est active', async () => {
    recupererAnnees.mockResolvedValue(ANNEES.filter((annee) => !annee.active))

    const wrapper = monter()
    await flushPromises()

    expect(wrapper.text()).toContain('Plateforme fermée')
    expect(wrapper.text()).toContain('Aucune année active')
  })

  it('prévient quand le chargement échoue et permet de réessayer', async () => {
    recupererAnnees.mockRejectedValueOnce({
      response: { data: { message: 'Le service est indisponible.' } },
    })

    const wrapper = monter()
    await flushPromises()

    expect(wrapper.text()).toContain('Le service est indisponible.')

    wrapper.vm.charger()
    await flushPromises()

    expect(wrapper.text()).toContain('2026/2027')
  })

  it('ajoute une année puis recharge la liste', async () => {
    const wrapper = monter()
    await flushPromises()

    wrapper.vm.ouvrirAjout()
    await nextTick()

    wrapper.vm.champs.libelle = '2028/2029'
    wrapper.vm.champs.date_debut = '2028-09-01'
    wrapper.vm.champs.date_fin = '2029-07-31'
    await nextTick()

    await wrapper.vm.enregistrer()
    await flushPromises()

    expect(creerAnnee).toHaveBeenCalledWith({
      libelle: '2028/2029',
      date_debut: '2028-09-01',
      date_fin: '2029-07-31',
    })
    expect(recupererAnnees).toHaveBeenCalledTimes(2)
    expect(wrapper.vm.snackbar).toMatchObject({
      visible: true,
      message: 'Année universitaire ajoutée.',
    })
  })

  it('modifie une année existante', async () => {
    const wrapper = monter()
    await flushPromises()

    wrapper.vm.ouvrirModification(ANNEES[0])
    await nextTick()

    expect(wrapper.vm.champs).toMatchObject({
      libelle: '2027/2028',
      date_debut: '2027-09-01',
      date_fin: '2028-07-31',
    })

    wrapper.vm.champs.date_fin = '2028-08-31'

    await wrapper.vm.enregistrer()
    await flushPromises()

    expect(modifierAnnee).toHaveBeenCalledWith(8, {
      libelle: '2027/2028',
      date_debut: '2027-09-01',
      date_fin: '2028-08-31',
    })
  })

  it("active une année en archivant l'ancienne par défaut", async () => {
    const wrapper = monter()
    await flushPromises()

    wrapper.vm.demanderActivation(ANNEES[0])
    await nextTick()

    expect(wrapper.vm.archiverPrecedente).toBe(true)

    await wrapper.vm.confirmerActivation()
    await flushPromises()

    expect(activerAnnee).toHaveBeenCalledWith(8, { archiver_precedente: true })
    expect(recupererAnnees).toHaveBeenCalledTimes(2)
    expect(wrapper.vm.snackbar).toMatchObject({
      visible: true,
      message: 'Année 2027/2028 activée.',
    })
  })

  it("n'archive rien quand la case est décochée", async () => {
    const wrapper = monter()
    await flushPromises()

    wrapper.vm.demanderActivation(ANNEES[0])
    await nextTick()

    wrapper.vm.archiverPrecedente = false

    await wrapper.vm.confirmerActivation()
    await flushPromises()

    expect(activerAnnee).toHaveBeenCalledWith(8, { archiver_precedente: false })
  })
})
