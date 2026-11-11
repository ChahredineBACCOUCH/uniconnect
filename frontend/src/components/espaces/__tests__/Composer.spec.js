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
})
