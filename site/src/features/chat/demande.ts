import { formatEuros, labelType, MOIS, type TypeDeVoyage } from '@/data/catalogue'

/** Les neuf critères du récapitulatif, dans l'ordre d'affichage. */
export const CRITERES = [
  'type',
  'destination',
  'periode',
  'duree',
  'temperature',
  'voyageurs',
  'budget',
  'envies',
  'contraintes',
] as const

export type Critere = (typeof CRITERES)[number]

export const LIBELLES: Record<Critere, string> = {
  type: 'Type',
  destination: 'Destination',
  periode: 'Période',
  duree: 'Durée',
  temperature: 'Température',
  voyageurs: 'Voyageurs',
  budget: 'Budget',
  envies: 'Envies',
  contraintes: 'Contraintes',
}

/** Fiche demande construite au fil de la conversation (équivalent de l'entité Demande côté API). */
export interface Demande {
  type?: TypeDeVoyage
  /** Destination du catalogue reconnue dans le lieu cité, avec son nom pour le récapitulatif. */
  destination?: { slug: string; nom: string }
  /** Zone ou envie de lieu exprimée librement (« la Méditerranée »). */
  zone?: string
  mois?: number
  datesFlexibles?: boolean
  jours?: number
  temperatureMin?: number
  adultes?: number
  enfants?: number
  budget?: number
  budgetParPersonne?: boolean
  depart?: string
  envies: string[]
  contraintes: string[]
  /** Contraintes à signaler au conseiller (régime, allergie, mobilité). */
  contraintesSignalees: string[]
}

export const demandeVide = (): Demande => ({
  envies: [],
  contraintes: [],
  contraintesSignalees: [],
})

/** Vrai si le critère a une valeur. */
export function estRenseigne(d: Demande, critere: Critere): boolean {
  switch (critere) {
    case 'type':
      return d.type !== undefined
    case 'destination':
      return d.destination !== undefined
    case 'periode':
      return d.mois !== undefined
    case 'duree':
      return d.jours !== undefined
    case 'temperature':
      return d.temperatureMin !== undefined
    case 'voyageurs':
      return d.adultes !== undefined
    case 'budget':
      return d.budget !== undefined
    case 'envies':
      return d.envies.length > 0
    case 'contraintes':
      return d.contraintes.length > 0
  }
}

export function nombreRenseignes(d: Demande) {
  return CRITERES.filter((c) => estRenseigne(d, c)).length
}

const majuscule = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)

/** Valeur lisible d'un critère pour le récapitulatif, ou undefined s'il manque. */
export function valeurCritere(d: Demande, critere: Critere): string | undefined {
  switch (critere) {
    case 'type': {
      if (!d.type) return undefined
      const voyageurs = (d.adultes ?? 0) + (d.enfants ?? 0)
      return d.type === 'sejour' && voyageurs === 2 && !d.enfants
        ? 'Séjour à deux'
        : labelType(d.type)
    }
    case 'destination':
      return d.destination?.nom ?? d.zone
    case 'periode':
      if (d.mois === undefined) return undefined
      return `${majuscule(MOIS[d.mois])} 2027${d.datesFlexibles ? ', dates flexibles' : ''}`
    case 'duree':
      return d.jours ? `${d.jours} jours` : undefined
    case 'temperature':
      return d.temperatureMin !== undefined ? `Au moins ${d.temperatureMin} °C` : undefined
    case 'voyageurs': {
      if (d.adultes === undefined) return undefined
      const adultes = `${d.adultes} adulte${d.adultes > 1 ? 's' : ''}`
      const enfants = d.enfants ? ` · ${d.enfants} enfant${d.enfants > 1 ? 's' : ''}` : ''
      const chambre = d.adultes === 2 && !d.enfants ? ' · 1 chambre double' : ''
      return adultes + enfants + chambre
    }
    case 'budget': {
      if (d.budget === undefined) return undefined
      const base = `${formatEuros(d.budget)} ${d.budgetParPersonne ? 'par personne' : 'au total'}`
      return d.depart ? `${base} · départ ${d.depart}` : base
    }
    case 'envies':
      return d.envies.length ? majuscule(d.envies.join(', ')) : undefined
    case 'contraintes':
      return d.contraintes.length ? majuscule(d.contraintes.join(' · ')) : undefined
  }
}
