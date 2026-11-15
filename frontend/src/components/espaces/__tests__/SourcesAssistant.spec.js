import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createRouter, createMemoryHistory } from 'vue-router'

import { recupererChapitres } from '@/api/ressources'
import SourcesAssistant from '@/components/espaces/SourcesAssistant.vue'
import vuetify from '@/plugins/vuetify'

vi.mock('@/api/ressources', () => ({ recupererChapitres: vi.fn() }))

function monter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/espaces/:id/:onglet?', name: 'espace', component: { template: '<div />' } }],
  })
  return mount(SourcesAssistant, {
    props: { espaceId: 7 },
    global: { plugins: [vuetify, router] },
  })
}

describe('SourcesAssistant (SCRUM-143)', () => {
  beforeEach(() => vi.clearAllMocks())

  it('liste seulement les documents indexés, avec un lien qui met la ressource en évidence', async () => {
    recupererChapitres.mockResolvedValue([
      {
        id: 1,
        titre: 'Chapitre 3 – Curseurs',
        ressources: [
          { id: 14, titre: 'Curseurs PL/SQL.pdf', indexee_ia: true },
          { id: 15, titre: 'Exercices (lien)', indexee_ia: false },
        ],
      },
    ])
    const wrapper = monter()
    await flushPromises()

    expect(recupererChapitres).toHaveBeenCalledWith(7)
    expect(wrapper.text()).toContain('Curseurs PL/SQL.pdf')
    expect(wrapper.text()).toContain('Chapitre 3 – Curseurs')
    expect(wrapper.text()).not.toContain('Exercices (lien)')
    expect(wrapper.find('a').attributes('href')).toBe('/espaces/7/ressources?ressource=14')
  })

  it('affiche un état vide sans document indexé', async () => {
    recupererChapitres.mockResolvedValue([{ id: 1, titre: 'Chapitre 1', ressources: [] }])
    const wrapper = monter()
    await flushPromises()

    expect(wrapper.text()).toContain("Aucun document indexé pour l'instant.")
  })

  it("affiche le message de l'API en cas d'erreur", async () => {
    recupererChapitres.mockRejectedValue({ response: { data: { message: 'Accès refusé.' } } })
    const wrapper = monter()
    await flushPromises()

    expect(wrapper.text()).toContain('Accès refusé.')
  })
})
