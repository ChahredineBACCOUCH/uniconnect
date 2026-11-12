import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { usePolling } from '@/composables/usePolling'

function monterPolling(callback, options) {
  const Composant = defineComponent({
    setup() {
      usePolling(callback, options)
      return () => h('div')
    },
  })

  return mount(Composant)
}

function ongletMasque(masque) {
  Object.defineProperty(document, 'hidden', {
    configurable: true,
    value: masque,
  })
}

describe('usePolling', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    ongletMasque(false)
  })

  afterEach(() => {
    vi.useRealTimers()
    delete document.hidden
  })

  it('appelle la fonction toutes les 5 secondes', async () => {
    const callback = vi.fn().mockResolvedValue(undefined)
    const wrapper = monterPolling(callback)

    await vi.advanceTimersByTimeAsync(4999)
    expect(callback).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1)
    expect(callback).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(5000)
    expect(callback).toHaveBeenCalledTimes(2)

    wrapper.unmount()
  })

  it("ne fait rien quand l'onglet est masqué et reprend au retour", async () => {
    const callback = vi.fn().mockResolvedValue(undefined)
    const wrapper = monterPolling(callback)

    ongletMasque(true)
    await vi.advanceTimersByTimeAsync(15_000)
    expect(callback).not.toHaveBeenCalled()

    ongletMasque(false)
    document.dispatchEvent(new Event('visibilitychange'))
    await vi.advanceTimersByTimeAsync(0)

    expect(callback).toHaveBeenCalledTimes(1)

    wrapper.unmount()
  })

  it("s'arrête au démontage du composant", async () => {
    const callback = vi.fn().mockResolvedValue(undefined)
    const wrapper = monterPolling(callback)

    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(15_000)

    expect(callback).not.toHaveBeenCalled()
  })

  it('ne superpose pas deux appels si le précédent est encore en cours', async () => {
    let resoudre
    const callback = vi.fn(
      () =>
        new Promise((resolve) => {
          resoudre = resolve
        }),
    )
    const wrapper = monterPolling(callback)

    await vi.advanceTimersByTimeAsync(5000)
    await vi.advanceTimersByTimeAsync(5000)
    expect(callback).toHaveBeenCalledTimes(1)

    resoudre()
    await vi.advanceTimersByTimeAsync(0)
    await vi.advanceTimersByTimeAsync(5000)
    expect(callback).toHaveBeenCalledTimes(2)

    wrapper.unmount()
  })

  it("signale les erreurs sans arrêter le polling", async () => {
    const onError = vi.fn()
    const callback = vi
      .fn()
      .mockRejectedValueOnce(new Error('réseau'))
      .mockResolvedValue(undefined)
    const wrapper = monterPolling(callback, { onError })

    await vi.advanceTimersByTimeAsync(5000)
    expect(onError).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(5000)
    expect(callback).toHaveBeenCalledTimes(2)

    wrapper.unmount()
  })
})
