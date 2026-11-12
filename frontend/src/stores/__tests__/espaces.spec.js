import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/api/espaces', () => ({
  recupererMesEspaces: vi.fn(),
  recupererMesInvitations: vi.fn(),
  accepterInvitation: vi.fn(),
  refuserInvitation: vi.fn(),
}))

import {
  accepterInvitation,
  recupererMesEspaces,
  recupererMesInvitations,
  refuserInvitation,
} from '@/api/espaces'
import { useEspacesStore } from '@/stores/espaces'

const MES_ESPACES = {
  organisationnels: [{ id: 1, nom: 'L2 Info – Groupe A', non_lus: 2 }],
  prives: [{ id: 5, nom: 'Révisions BD', non_lus: 0 }],
  channels: [{ id: 7, nom: 'Club Informatique', non_lus: 3 }],
}
const INVITATION = { id: 9, espace: { id: 12, nom: 'Projet' } }

describe('store espaces', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    recupererMesEspaces.mockResolvedValue(MES_ESPACES)
    recupererMesInvitations.mockResolvedValue([INVITATION])
  })

  it("charge les espaces et les invitations depuis l'API", async () => {
    const store = useEspacesStore()

    await store.charger()

    expect(store.charge).toBe(true)
    expect(store.tous.map((e) => e.id)).toEqual([1, 5, 7])
    expect(store.totalNonLus).toBe(5)
    expect(store.invitations).toEqual([INVITATION])
    expect(store.estVide).toBe(false)
  })

  it("garde un message d'erreur si l'API échoue", async () => {
    recupererMesEspaces.mockRejectedValue({
      response: { data: { message: 'Serveur indisponible' } },
    })
    const store = useEspacesStore()

    await store.charger()

    expect(store.erreur).toBe('Serveur indisponible')
    expect(store.chargement).toBe(false)
    expect(store.estVide).toBe(true)
  })

  it("accepter recharge le menu et renvoie l'espace rejoint", async () => {
    accepterInvitation.mockResolvedValue({ id: 12 })
    const store = useEspacesStore()

    const espace = await store.accepter(9)

    expect(accepterInvitation).toHaveBeenCalledWith(9)
    expect(espace).toEqual({ id: 12 })
    expect(recupererMesEspaces).toHaveBeenCalledTimes(1)
  })

  it("refuser retire l'invitation de la liste", async () => {
    refuserInvitation.mockResolvedValue()
    const store = useEspacesStore()
    await store.charger()

    await store.refuser(9)

    expect(refuserInvitation).toHaveBeenCalledWith(9)
    expect(store.invitations).toEqual([])
  })

  it('vider remet le store à zéro (déconnexion)', async () => {
    const store = useEspacesStore()
    await store.charger()

    store.vider()

    expect(store.charge).toBe(false)
    expect(store.tous).toEqual([])
    expect(store.invitations).toEqual([])
  })
})
