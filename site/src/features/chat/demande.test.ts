import { describe, expect, it } from 'vitest'
import {
  codesDuQuestionnaire,
  demandeDepuisQuestionnaire,
  demandeVide,
  estRenseigne,
  nombreRenseignes,
  valeurCritere,
} from './demande'

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

  it('formate les critères en anglais pour un client qui écrit en anglais (US15)', () => {
    const d = {
      ...demandeVide(),
      type: 'noces' as const,
      mois: 5,
      annee: 2027,
      datesFlexibles: true,
      jours: 7,
      adultes: 2,
      enfants: 1,
      budget: 5000,
      budgetParPersonne: false,
      depart: 'Paris',
    }
    expect(valeurCritere(d, 'type', 'en')).toBe('Honeymoon')
    expect(valeurCritere(d, 'periode', 'en')).toBe('June 2027, flexible dates')
    expect(valeurCritere(d, 'duree', 'en')).toBe('7 days')
    expect(valeurCritere(d, 'voyageurs', 'en')).toBe('2 adults · 1 child')
    expect(valeurCritere(d, 'budget', 'en')).toMatch(/^5\s000 € in total · from Paris$/)
  })

  it('affiche l’année du départ, et seulement le mois quand l’année est inconnue', () => {
    expect(valeurCritere({ ...demandeVide(), mois: 11, annee: 2026 }, 'periode')).toBe(
      'Décembre 2026',
    )
    expect(valeurCritere({ ...demandeVide(), mois: 11 }, 'periode')).toBe('Décembre')
  })

  it('présente un séjour à deux sans enfant', () => {
    const d = { ...demandeVide(), type: 'sejour' as const, adultes: 2, enfants: 0 }
    expect(valeurCritere(d, 'type')).toBe('Séjour à deux')
    expect(valeurCritere(d, 'voyageurs')).toBe('2 adultes · 1 chambre double')
  })

  it('convertit les réponses du questionnaire en critères du voyage', () => {
    const d = demandeDepuisQuestionnaire(['Noces', 'À deux', 'De 1 500 à 3 000 €'], demandeVide())

    expect(d).toMatchObject({
      type: 'noces',
      adultes: 2,
      budget: 3000,
      budgetIndicatif: 'De 1 500 à 3 000 €',
      budgetParPersonne: false,
    })
    expect(valeurCritere(d, 'type')).toBe('Voyage de noces')
    expect(valeurCritere(d, 'voyageurs')).toBe('2 adultes · 1 chambre double')
    expect(valeurCritere(d, 'budget')).toBe('De 1 500 à 3 000 € au total')
  })

  it('garde « Plus de 3 000 € » sans montant cible (D26)', () => {
    const d = demandeDepuisQuestionnaire(
      ['Séjours', 'En famille', 'Plus de 3 000 €'],
      demandeVide(),
    )

    expect(d.budget).toBeUndefined()
    expect(estRenseigne(d, 'budget')).toBe(true)
    expect(valeurCritere(d, 'budget')).toBe('Plus de 3 000 € au total')
    expect(codesDuQuestionnaire(['Séjours', 'En famille', 'Plus de 3 000 €'])).toEqual({
      typeDeVoyage: 'sejour',
      voyageurs: 'famille',
      budget: 'plus_de_3000',
    })
  })
})
