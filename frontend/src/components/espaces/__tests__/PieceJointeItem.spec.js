import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { apercuFichier, telechargerFichier } from '@/api/fichiers'
import PieceJointeItem from '@/components/espaces/PieceJointeItem.vue'
import vuetify from '@/plugins/vuetify'

vi.mock('@/api/fichiers', () => ({
  apercuFichier: vi.fn(),
  telechargerFichier: vi.fn(),
}))

function monter(piece) {
  return mount(PieceJointeItem, {
    props: { piece },
    global: { plugins: [vuetify] },
  })
}

const PDF = {
  id: 3,
  nom: 'notes.pdf',
  mime: 'application/pdf',
  taille_octets: 2048,
}

describe('PieceJointeItem (SCRUM-137)', () => {
  let urlCreation
  let urlRevocation

  beforeEach(() => {
    vi.clearAllMocks()

    urlCreation = vi.fn(() => 'blob:apercu')
    urlRevocation = vi.fn()
    vi.stubGlobal('URL', {
      ...URL,
      createObjectURL: urlCreation,
      revokeObjectURL: urlRevocation,
    })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('affiche le nom, le type et la taille du fichier', () => {
    const wrapper = monter(PDF)

    expect(wrapper.text()).toContain('notes.pdf')
    expect(wrapper.text()).toContain('PDF')
    expect(wrapper.text()).toContain('2 Ko')
    expect(apercuFichier).not.toHaveBeenCalled()
  })

  it('affiche une icône adaptée au type de fichier', () => {
    const wrapper = monter({
      id: 6,
      nom: 'budget.xlsx',
      mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      taille_octets: 10,
    })

    expect(wrapper.html()).toContain('mdi-file-excel-outline')
    expect(wrapper.text()).toContain('XLSX')
  })

  it("charge l'aperçu d'une image avec un Blob authentifié", async () => {
    const blob = new Blob(['image'], { type: 'image/png' })
    apercuFichier.mockResolvedValue(blob)

    const wrapper = monter({ id: 4, nom: 'photo.png', mime: 'image/png', taille_octets: 10 })
    await flushPromises()

    expect(apercuFichier).toHaveBeenCalledWith(4)
    expect(urlCreation).toHaveBeenCalledWith(blob)
    expect(wrapper.find('img').attributes('src')).toBe('blob:apercu')

    wrapper.unmount()
    expect(urlRevocation).toHaveBeenCalledWith('blob:apercu')
  })

  it("signale l'échec du chargement de l'aperçu sans bloquer le téléchargement", async () => {
    apercuFichier.mockRejectedValue(new Error('403'))

    const wrapper = monter({ id: 5, nom: 'photo.jpg', mime: 'image/jpeg', taille_octets: 10 })
    await flushPromises()

    expect(wrapper.emitted('erreur')?.[0]).toEqual([
      "L'aperçu de l'image n'a pas pu être chargé.",
    ])
    expect(wrapper.find('.piece__telecharger').exists()).toBe(true)
  })

  it("télécharge le fichier via le Blob et conserve le nom d'origine", async () => {
    const blob = new Blob(['pdf'])
    telechargerFichier.mockResolvedValue(blob)
    const clic = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

    const wrapper = monter(PDF)
    await wrapper.find('.piece__telecharger').trigger('click')
    await flushPromises()

    expect(telechargerFichier).toHaveBeenCalledWith(3)
    expect(urlCreation).toHaveBeenCalledWith(blob)
    expect(clic).toHaveBeenCalled()
    expect(urlRevocation).toHaveBeenCalledWith('blob:apercu')

    clic.mockRestore()
  })

  it('signale un téléchargement refusé par l’API', async () => {
    telechargerFichier.mockRejectedValue(new Error('403'))

    const wrapper = monter(PDF)
    await wrapper.find('.piece__telecharger').trigger('click')
    await flushPromises()

    expect(wrapper.emitted('erreur')?.[0]).toEqual(['Le téléchargement a échoué.'])
  })
})
