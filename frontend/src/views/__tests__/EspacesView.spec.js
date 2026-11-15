import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

const { push } = vi.hoisted(() => ({ push: vi.fn() }))

vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))

vi.mock('@/api/espaces', () => ({
  recupererMesEspaces: vi.fn(),
  recupererMesInvitations: vi.fn(),
  accepterInvitation: vi.fn(),
  refuserInvitation: vi.fn(),
}))

import { recupererMesEspaces, recupererMesInvitations } from '@/api/espaces'
import vuetify from '@/plugins/vuetify'
import { useAuthStore } from '@/stores/auth'
import EspacesView from '@/views/EspacesView.vue'

const global = { plugins: [vuetify], stubs: { RouterLink: true } }

describe('EspacesView (Mes espaces)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    recupererMesInvitations.mockResolvedValue([])
  })

  it('liste les espaces par rubrique, avec les non-lus', async () => {
    recupererMesEspaces.mockResolvedValue({
      organisationnels: [{ id: 3, nom: 'Bases de données', icone: 'mdi-book', non_lus: 2 }],
      prives: [{ id: 7, nom: 'Projet intégration – Équipe 4', icone: 'mdi-lock', non_lus: 0 }],
      channels: [],
    })

    const wrapper = mount(EspacesView, { global })
    await flushPromises()

    expect(wrapper.text()).toContain('Mes classes et matières')
    expect(wrapper.text()).toContain('Bases de données')
    expect(wrapper.text()).toContain('2 non lus')
    expect(wrapper.text()).toContain('Mes groupes privés')
    expect(wrapper.text()).not.toContain('Mes channels')
  })

  it('propose de créer un espace quand la liste est vide', async () => {
    useAuthStore().user = { id: 1, droits: ['CREER_GROUPE_PRIVE'] }
    recupererMesEspaces.mockResolvedValue({ organisationnels: [], prives: [], channels: [] })

    const wrapper = mount(EspacesView, { global })
    await flushPromises()

    expect(wrapper.text()).toContain("Vous n'avez encore aucun espace.")
    const bouton = wrapper.findAll('button').find((b) => b.text() === 'Créer un espace')
    await bouton.trigger('click')
    expect(push).toHaveBeenCalledWith({ name: 'espace-nouveau' })
  })
})
