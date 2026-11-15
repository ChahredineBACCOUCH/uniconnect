import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import EmptyState from '@/components/EmptyState.vue'
import LoadingSkeleton from '@/components/LoadingSkeleton.vue'
import NotificationGlobale from '@/components/NotificationGlobale.vue'
import ChargementApplication from '@/components/ChargementApplication.vue'
import { useNotify } from '@/composables/useNotify'
import vuetify from '@/plugins/vuetify'

const global = { plugins: [vuetify] }

describe('SCRUM-184 : retours à l’utilisateur', () => {
  describe('useNotify', () => {
    beforeEach(() => useNotify().fermer())

    it('partage un seul message entre tous les écrans', () => {
      useNotify().succes('Invitation envoyée.')

      const { etat } = useNotify()
      expect(etat.value).toMatchObject({
        visible: true,
        type: 'succes',
        texte: 'Invitation envoyée.',
      })
    })

    it('garde le message affiché tant qu’une action est proposée, puis l’exécute', () => {
      const action = vi.fn()
      const notify = useNotify()
      notify.erreur('Problème de connexion au serveur.', { actionLabel: 'Réessayer', action })

      expect(notify.etat.value.duree).toBe(-1)

      notify.agir()
      expect(action).toHaveBeenCalledOnce()
      expect(notify.etat.value.visible).toBe(false)
    })
  })

  describe('EmptyState', () => {
    it('affiche le titre, le texte et l’action suivante', async () => {
      const wrapper = mount(EmptyState, {
        props: {
          titre: 'Aucune annonce.',
          texte: 'Publiez la première.',
          actionLabel: 'Nouvelle annonce',
        },
        global,
      })

      expect(wrapper.text()).toContain('Aucune annonce.')
      expect(wrapper.text()).toContain('Publiez la première.')
      await wrapper.find('button').trigger('click')
      expect(wrapper.emitted('action')).toHaveLength(1)
    })

    it('n’affiche pas de bouton sans action', () => {
      const wrapper = mount(EmptyState, { props: { titre: 'Rien ici.' }, global })

      expect(wrapper.find('button').exists()).toBe(false)
      expect(wrapper.attributes('role')).toBe('status')
    })
  })

  describe('LoadingSkeleton', () => {
    it('annonce le chargement aux lecteurs d’écran', () => {
      const wrapper = mount(LoadingSkeleton, { props: { type: 'cartes', lignes: 2 }, global })

      expect(wrapper.attributes('aria-busy')).toBe('true')
      expect(wrapper.attributes('aria-label')).toBe('Chargement en cours')
    })
  })

  describe('NotificationGlobale', () => {
    afterEach(() => {
      useNotify().fermer()
      document.body.innerHTML = ''
    })

    it('affiche le message de useNotify avec son bouton d’action', async () => {
      const wrapper = mount(NotificationGlobale, { global, attachTo: document.body })
      useNotify().erreur('Problème de connexion au serveur.', {
        actionLabel: 'Réessayer',
        action: () => {},
      })
      await wrapper.vm.$nextTick()
      await new Promise((r) => setTimeout(r, 0))

      expect(document.body.textContent).toContain('Problème de connexion au serveur.')
      expect(document.body.textContent).toContain('Réessayer')
      wrapper.unmount()
    })
  })

  describe('ChargementApplication', () => {
    afterEach(() => vi.useRealTimers())

    it('explique le réveil du serveur après quelques secondes', async () => {
      vi.useFakeTimers()
      const wrapper = mount(ChargementApplication, { global })

      expect(wrapper.text()).toContain('Chargement de votre espace')
      expect(wrapper.text()).not.toContain('se réveille')

      vi.advanceTimersByTime(5000)
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('Le serveur se réveille')
    })
  })
})
