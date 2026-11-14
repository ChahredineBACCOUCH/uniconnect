import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import Composer from '@/components/espaces/Composer.vue'
import vuetify from '@/plugins/vuetify'

const espace = {
  id: 2,
  nom: 'Bases de données',
  type: 'PRIVE',
  etat: 'ACTIF',
  role: 'MEMBRE',
  duree_visibilite_heures: 24,
  duree_courte_autorisee: true,
  mode_annonce: false,
}

function monterComposer(surcharge = {}, envoyer = vi.fn().mockResolvedValue({})) {
  return mount(Composer, {
    props: { espace: { ...espace, ...surcharge }, envoyer, utilisateurId: 1 },
    global: { plugins: [vuetify] },
  })
}

function fichier(nom = 'photo.png', taille = 1024, type = 'image/png') {
  const element = new File(['contenu'], nom, { type })
  Object.defineProperty(element, 'size', { value: taille })
  return element
}

async function joindre(wrapper, ...fichiers) {
  const input = wrapper.find('input[type="file"]')
  Object.defineProperty(input.element, 'files', { value: fichiers, configurable: true })
  await input.trigger('change')
}

describe('Composer', () => {
  it('envoie le texte et vide la zone de saisie', async () => {
    const envoyer = vi.fn().mockResolvedValue({ id: 9 })
    const wrapper = monterComposer({}, envoyer)

    const zone = wrapper.find('textarea')
    await zone.setValue('Bonjour le groupe')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(envoyer).toHaveBeenCalledWith({
      contenu: 'Bonjour le groupe',
      parent_id: null,
      duree_visibilite_heures: null,
    })
    expect(wrapper.find('textarea').element.value).toBe('')
  })

  it('désactive la saisie en mode annonce pour un membre simple', () => {
    const wrapper = monterComposer({ mode_annonce: true, role: 'MEMBRE' })

    expect(wrapper.text()).toContain('seuls les gestionnaires peuvent publier')
    expect(wrapper.find('textarea').attributes('disabled')).toBeDefined()

    const bouton = wrapper.findAll('button').find((element) => element.text().includes('Envoyer'))
    expect(bouton.attributes('disabled')).toBeDefined()
  })

  it('autorise un gestionnaire à publier en mode annonce', () => {
    const wrapper = monterComposer({ mode_annonce: true, role: 'PROPRIETAIRE' })

    expect(wrapper.text()).not.toContain('seuls les gestionnaires peuvent publier')
    expect(wrapper.find('textarea').attributes('disabled')).toBeUndefined()
  })

  it('passe en lecture seule quand l’espace est archivé', () => {
    const wrapper = monterComposer({ etat: 'ARCHIVE' })

    expect(wrapper.text()).toContain('la discussion est en lecture seule')
    expect(wrapper.find('textarea').attributes('disabled')).toBeDefined()
  })

  it('propose un sélecteur de durée plus courte quand l’espace l’autorise', () => {
    const wrapper = monterComposer()
    expect(wrapper.find('.v-select').exists()).toBe(true)

    const sansDureeCourte = monterComposer({ duree_courte_autorisee: false })
    expect(sansDureeCourte.find('.v-select').exists()).toBe(false)
  })

  it('affiche les erreurs renvoyées par l’API sans vider le brouillon', async () => {
    const envoyer = vi.fn().mockRejectedValue(new Error('Le serveur est indisponible.'))
    const wrapper = monterComposer({}, envoyer)

    await wrapper.find('textarea').setValue('Message important')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Le serveur est indisponible.')
    expect(wrapper.find('textarea').element.value).toBe('Message important')
  })

  it('joint des fichiers, envoie en multipart et suit la progression (SCRUM-139)', async () => {
    let terminer
    const envoyer = vi.fn().mockImplementation(
      () =>
        new Promise((resolve) => {
          terminer = resolve
        }),
    )
    const wrapper = monterComposer({}, envoyer)
    const photo = fichier('photo.png', 2048)

    await joindre(wrapper, photo)
    expect(wrapper.text()).toContain('photo.png')
    expect(wrapper.text()).toContain('2 Ko')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const [payload, options] = envoyer.mock.calls[0]

    expect(payload).toEqual({
      contenu: '',
      parent_id: null,
      duree_visibilite_heures: null,
      fichiers: [photo],
    })
    expect(options.onUploadProgress).toEqual(expect.any(Function))

    options.onUploadProgress({ loaded: 1, total: 4 })
    await flushPromises()
    expect(wrapper.text()).toContain('Téléversement : 25 %')

    terminer({ id: 12 })
    await flushPromises()

    expect(wrapper.text()).not.toContain('photo.png')
    expect(wrapper.text()).not.toContain('Téléversement')
  })

  it('accepte un dépôt de fichiers sur le composer', async () => {
    const wrapper = monterComposer()
    const planning = fichier('planning.pdf', 4096, 'application/pdf')

    await wrapper.find('form').trigger('drop', { dataTransfer: { files: [planning] } })

    expect(wrapper.text()).toContain('planning.pdf')
    expect(wrapper.text()).toContain('4 Ko')
  })

  it('refuse un fichier trop gros avant l’envoi', async () => {
    const envoyer = vi.fn().mockResolvedValue({})
    const wrapper = monterComposer({}, envoyer)
    const gros = fichier('memoire.pdf', 26 * 1024 * 1024, 'application/pdf')

    await joindre(wrapper, gros)

    expect(wrapper.text()).toContain('dépasse la taille maximale de 25 Mo')
    expect(wrapper.find('.v-chip').exists()).toBe(false)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(envoyer).not.toHaveBeenCalled()
  })

  it('refuse un format interdit avant l’envoi', async () => {
    const wrapper = monterComposer()

    await joindre(wrapper, fichier('script.exe', 1024, 'application/x-msdownload'))

    expect(wrapper.text()).toContain("Le format de « script.exe » n'est pas autorisé.")
    expect(wrapper.find('.v-chip').exists()).toBe(false)
  })

  it('retire un fichier avec la croix de sa puce', async () => {
    const wrapper = monterComposer()

    await joindre(wrapper, fichier('notes.pdf', 1024, 'application/pdf'))
    expect(wrapper.text()).toContain('notes.pdf')

    await wrapper.find('.v-chip__close').trigger('click')
    await flushPromises()

    expect(wrapper.text()).not.toContain('notes.pdf')
  })
})
