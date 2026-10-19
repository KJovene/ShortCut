import { describe, expect, it } from 'vitest'
import { demandeVide, extraire, nombreRenseignes, valeurCritere } from './demande'

describe('extraction des exigences', () => {
  it('lit durée, mois, température, budget et contraintes dans une phrase', () => {
    const d = extraire(
      'On aimerait partir au soleil en mai, à deux, une dizaine de jours. Budget 4 000 € tout compris, au moins 25 °C, sans voiture.',
      demandeVide(),
    )
    expect(d).toMatchObject({
      mois: 4,
      jours: 10,
      adultes: 2,
      budget: 4000,
      temperatureMin: 25,
      contraintes: ['sans voiture'],
    })
    expect(d.envies).toContain('soleil')
  })

  it('signale les régimes et allergies au conseiller', () => {
    const d = extraire(
      'Ma compagne est végétarienne et je suis allergique aux arachides',
      demandeVide(),
    )
    expect(d.contraintesSignalees).toEqual(['végétarien', 'allergie (arachides)'])
  })

  it('reconnaît une destination du catalogue et le type de voyage', () => {
    const d = extraire('Notre voyage de noces à Santorin, 2 semaines', demandeVide())
    expect(d).toMatchObject({ destination: 'santorin', type: 'noces', jours: 14 })
  })

  it('garde les critères déjà connus', () => {
    const avant = extraire('10 jours en juin', demandeVide())
    const apres = extraire('Nous serons 4 personnes', avant)
    expect(apres).toMatchObject({ jours: 10, mois: 5, adultes: 4 })
  })

  it('formate le récapitulatif', () => {
    const d = extraire('3 adultes et 2 enfants, 1500 € par personne depuis Lyon', demandeVide())
    expect(valeurCritere(d, 'voyageurs')).toBe('3 adultes · 2 enfants')
    expect(valeurCritere(d, 'budget')).toMatch(/^1\s500 € par personne · départ Lyon$/)
    // Voyageurs, budget, et l'envie « en famille » déduite des enfants.
    expect(nombreRenseignes(d)).toBe(3)
  })
})
