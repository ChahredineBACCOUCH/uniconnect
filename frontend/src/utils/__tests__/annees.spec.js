import { describe, expect, it } from 'vitest'

import { detailEtat, formaterDate, libelleGroupes } from '@/utils/annees'

/*
 * SCRUM-73 : affichage de l'écran A01.
 */
describe('formatage des années universitaires', () => {
  it('convertit une date ISO au format de la maquette', () => {
    expect(formaterDate('2026-09-01')).toBe('01/09/2026')
    expect(formaterDate(null)).toBe('—')
    expect(formaterDate('')).toBe('—')
  })

  it('affiche le nombre de groupes organisationnels', () => {
    expect(libelleGroupes({ nombre_groupes: 0, nombre_groupes_archives: 0 })).toBe('—')
    expect(libelleGroupes({ nombre_groupes: 286, nombre_groupes_archives: 0 })).toBe('286')
    expect(libelleGroupes({ nombre_groupes: 271, nombre_groupes_archives: 271 })).toBe(
      '271 (archivés)',
    )
    expect(libelleGroupes({ nombre_groupes: 10, nombre_groupes_archives: 3 })).toBe(
      '10 (dont 3 archivés)',
    )
  })

  it("décrit l'état de l'année", () => {
    expect(detailEtat({ active: true })).toEqual({ libelle: 'Active', color: 'success' })
    expect(detailEtat({ active: false })).toEqual({ libelle: 'Inactive', color: null })
  })
})
