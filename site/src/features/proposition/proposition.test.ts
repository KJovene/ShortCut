import { describe, expect, it } from 'vitest'
import { demandeVide } from '@/features/chat/demande'
import { genererProposition } from './proposition'

describe('proposition', () => {
  const demande = { ...demandeVide(), type: 'sejour' as const, jours: 8, adultes: 2, mois: 4 }

  it('construit un programme sur le nombre de jours demandé', () => {
    const p = genererProposition(demande, 'lisbonne')
    expect(p.jours).toHaveLength(8)
    expect(p.jours[0].titre).toBe('Arrivée à Lisbonne')
    expect(p.jours.at(-1)!.titre).toBe('Retour')
    expect(p.nuits).toBe(7)
  })

  it('classe les trois niveaux de budget du moins cher au plus cher', () => {
    const { budgets } = genererProposition(demande, 'lisbonne')
    expect(budgets.essentiel.total).toBeLessThan(budgets.equilibre.total)
    expect(budgets.equilibre.total).toBeLessThan(budgets.confort.total)
  })

  it('applique 20 % de marge et les frais de dossier du type de voyage', () => {
    const { budgets } = genererProposition(demande, 'lisbonne')
    const b = budgets.essentiel
    // Hôtel Alfama Vista 110 €/nuit, vol 120 €, transfert 20 €, visite 35 € par personne.
    const cout = 110 * 7 + 120 * 2 + 20 * 2 + 35 * 2
    expect(b.total).toBe(Math.round(cout * 1.2) + 100)
    expect(b.lignes.at(-1)).toEqual({ label: 'Frais de dossier', montant: 100 })
    expect(b.parPersonne).toBe(Math.round(b.total / 2))
  })
})
