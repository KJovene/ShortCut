import { describe, expect, it } from 'vitest'
import { demandeVide, nombreRenseignes, valeurCritere } from './demande'

describe('récapitulatif de la demande', () => {
  it('formate les critères renseignés', () => {
    const d = {
      ...demandeVide(),
      adultes: 3,
      enfants: 2,
      budget: 1500,
      budgetParPersonne: true,
      depart: 'Lyon',
      envies: ['en famille'],
    }
    expect(valeurCritere(d, 'voyageurs')).toBe('3 adultes · 2 enfants')
    expect(valeurCritere(d, 'budget')).toMatch(/^1\s500 € par personne · départ Lyon$/)
    expect(valeurCritere(d, 'envies')).toBe('En famille')
    expect(valeurCritere(d, 'periode')).toBeUndefined()
    // Voyageurs, budget et envies.
    expect(nombreRenseignes(d)).toBe(3)
  })

  it('affiche la destination reconnue avec son nom du catalogue, sinon la zone citée', () => {
    const destination = { slug: 'santorin', nom: 'Santorin, Grèce' }
    expect(valeurCritere({ ...demandeVide(), destination }, 'destination')).toBe('Santorin, Grèce')
    expect(valeurCritere({ ...demandeVide(), zone: 'la Méditerranée' }, 'destination')).toBe(
      'la Méditerranée',
    )
    expect(valeurCritere(demandeVide(), 'destination')).toBeUndefined()
  })

  it('présente un séjour à deux sans enfant', () => {
    const d = { ...demandeVide(), type: 'sejour' as const, adultes: 2, enfants: 0 }
    expect(valeurCritere(d, 'type')).toBe('Séjour à deux')
    expect(valeurCritere(d, 'voyageurs')).toBe('2 adultes · 1 chambre double')
  })
})
