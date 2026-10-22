import { formatEuros, MOIS, type TypeDeVoyage } from '@/data/catalogue'
import { type Langue, nomDuMois, typeDeVoyage } from '@/lib/langue'
import { TEXTES } from './textes'

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
export function valeurCritere(
  d: Demande,
  critere: Critere,
  langue: Langue = 'fr',
): string | undefined {
  const t = TEXTES[langue]
  switch (critere) {
    case 'type': {
      if (!d.type) return undefined
      const voyageurs = (d.adultes ?? 0) + (d.enfants ?? 0)
      return d.type === 'sejour' && voyageurs === 2 && !d.enfants
        ? t.sejourADeux
        : typeDeVoyage(d.type, langue)
    }
    case 'destination':
      return d.destination?.nom ?? d.zone
    case 'periode':
      if (d.mois === undefined) return undefined
      return `${majuscule(langue === 'en' ? nomDuMois(d.mois, langue) : MOIS[d.mois])} 2027${d.datesFlexibles ? t.datesFlexibles : ''}`
    case 'duree':
      return d.jours ? t.jours(d.jours) : undefined
    case 'temperature':
      return d.temperatureMin !== undefined ? t.auMoins(d.temperatureMin) : undefined
    case 'voyageurs': {
      if (d.adultes === undefined) return undefined
      const enfants = d.enfants ? ` · ${t.enfants(d.enfants)}` : ''
      const chambre = d.adultes === 2 && !d.enfants ? t.chambreDouble : ''
      return t.adultes(d.adultes) + enfants + chambre
    }
    case 'budget': {
      if (d.budget === undefined) return undefined
      const base = `${formatEuros(d.budget)} ${d.budgetParPersonne ? t.parPersonne : t.auTotal}`
      return d.depart ? `${base} · ${t.departDe(d.depart)}` : base
    }
    case 'envies':
      return d.envies.length ? majuscule(d.envies.join(', ')) : undefined
    case 'contraintes':
      return d.contraintes.length ? majuscule(d.contraintes.join(' · ')) : undefined
  }
}
