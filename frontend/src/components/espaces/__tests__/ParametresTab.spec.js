import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

import http from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import ParametresTab from '../ParametresTab.vue'

vi.mock('@/api/http', () => ({
  default: { patch: vi.fn() },
}))

const ESPACE = {
  id: 3,
  nom: 'Bases de données',
  role: 'PROPRIETAIRE',
  etat: 'ACTIF',
  assistant: { actif: false, instructions: null, nb_documents_indexes: 2 },
}

function monter(espace = ESPACE, profil = 'ENSEIGNANT') {
  useAuthStore().user = { id: 1, prenom: 'Ines', nom: 'Trabelsi', profil, droits: [] }
  return mount(ParametresTab, {
    props: { espace },
    global: { plugins: [createVuetify({ components, directives })] },
  })
}

const boutonEnregistrer = (wrapper) =>
  wrapper.findAll('button').find((b) => b.text().includes('Enregistrer'))

describe('ParametresTab · réglage Assistant IA (SCRUM-147)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it("affiche le réglage à l'enseignant propriétaire", () => {
    const wrapper = monter()

    expect(wrapper.text()).toContain('Assistant IA')
    expect(wrapper.text()).toContain('Assistant désactivé')
    expect(wrapper.text()).toContain('2 documents indexés')
  })

  it('le cache aux étudiants, aux simples membres et dans un espace archivé', () => {
    expect(monter(ESPACE, 'ETUDIANT').text()).not.toContain('Assistant désactivé')
    expect(monter({ ...ESPACE, role: 'MEMBRE' }).text()).not.toContain('Assistant désactivé')
    expect(monter({ ...ESPACE, etat: 'ARCHIVE' }).text()).not.toContain('Assistant désactivé')
  })

  it("le montre aussi à l'enseignant co-gestionnaire", () => {
    expect(monter({ ...ESPACE, role: 'CO_GESTIONNAIRE' }).text()).toContain('Assistant désactivé')
  })

  it('active l’assistant avec une consigne, puis recharge l’espace', async () => {
    http.patch.mockResolvedValue({
      data: { actif: true, instructions: 'Ne donne pas les corrigés.', nb_documents_indexes: 2 },
    })
    const wrapper = monter()

    expect(boutonEnregistrer(wrapper).element.disabled).toBe(true)

    await wrapper.find('input[type="checkbox"]').setValue(true)
    await wrapper.find('textarea').setValue('  Ne donne pas les corrigés.  ')
    expect(boutonEnregistrer(wrapper).element.disabled).toBe(false)

    await boutonEnregistrer(wrapper).trigger('click')
    await flushPromises()

    expect(http.patch).toHaveBeenCalledWith('/espaces/3/assistant', {
      actif: true,
      instructions: 'Ne donne pas les corrigés.',
    })
    expect(wrapper.emitted('actualiser')).toHaveLength(1)
    expect(wrapper.text()).toContain('Assistant activé pour les membres')
    // Rien n'a changé depuis l'enregistrement : le bouton est de nouveau grisé.
    expect(boutonEnregistrer(wrapper).element.disabled).toBe(true)
  })

  it('prévient quand aucun support n’est indexé', async () => {
    const wrapper = monter({ ...ESPACE, assistant: { actif: true, nb_documents_indexes: 0 } })

    expect(wrapper.text()).toContain("Aucun support de cours n'est encore indexé")
  })

  it('refuse une consigne de plus de 1 000 caractères', async () => {
    const wrapper = monter()

    await wrapper.find('textarea').setValue('a'.repeat(1001))

    expect(wrapper.text()).toContain('La consigne ne doit pas dépasser 1 000 caractères.')
    expect(boutonEnregistrer(wrapper).element.disabled).toBe(true)
  })

  it('affiche un message en français après un refus (403)', async () => {
    http.patch.mockRejectedValue(
      Object.assign(new Error('403'), {
        response: { status: 403, data: { message: 'This action is unauthorized.' } },
      }),
    )
    const wrapper = monter()

    await wrapper.find('input[type="checkbox"]').setValue(true)
    await boutonEnregistrer(wrapper).trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Seul un enseignant propriétaire ou co-gestionnaire')
    expect(wrapper.text()).not.toContain('unauthorized')
    expect(wrapper.emitted('actualiser')).toBeUndefined()
  })

  it("affiche l'erreur de validation de l'API (422)", async () => {
    http.patch.mockRejectedValue(
      Object.assign(new Error('422'), {
        response: {
          status: 422,
          data: { errors: { actif: ['La valeur « actif » doit être vrai ou faux.'] } },
        },
      }),
    )
    const wrapper = monter()

    await wrapper.find('input[type="checkbox"]').setValue(true)
    await boutonEnregistrer(wrapper).trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('La valeur « actif » doit être vrai ou faux.')
  })
})
