import { formatEuros, MOIS, type TypeDeVoyage } from '@/data/catalogue'
import { type Langue, nomDuMois, typeDeVoyage } from '@/lib/langue'
import { TEXTES } from './textes'

/** Les huit critères du récapitulatif, dans l'ordre d'affichage. */
export const CRITERES = [
  'type',
  'destination',
  'periode',
  'duree',
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
  /** Année du départ, quand la fiche donne une date. */
  annee?: number
  datesFlexibles?: boolean
  jours?: number
  adultes?: number
  enfants?: number
  budget?: number
  /** Formulation conservée pour les budgets par tranche du questionnaire. */
  budgetIndicatif?: string
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

/** Codes envoyés à l'API pour chaque réponse du questionnaire d'accueil (D26), dans l'ordre de ses choix. */
const CODES_QUESTIONNAIRE = [
  ['sejour', 'circuit', 'groupe', 'noces', 'entreprise'],
  ['seul', 'deux', 'famille', 'amis'],
  ['moins_de_1500', 'de_1500_a_3000', 'plus_de_3000'],
] as const

/** Réponses enregistrées avant le changement des catégories. */
const ANCIENS_TYPES: Record<string, TypeDeVoyage> = {
  'Séjour détente': 'sejour',
  'Circuit découverte': 'circuit',
  'Voyage de noces': 'noces',
}

export interface CodesQuestionnaire {
  typeDeVoyage?: TypeDeVoyage
  voyageurs?: (typeof CODES_QUESTIONNAIRE)[1][number]
  budget?: (typeof CODES_QUESTIONNAIRE)[2][number]
}

/** Les réponses (libellés français, voir QuestionnaireInvite) en codes, pour l'API et la fiche demande. */
export function codesDuQuestionnaire(reponses: string[]): CodesQuestionnaire {
  const code = <T>(codes: readonly T[], rang: number): T | undefined => {
    const index = TEXTES.fr.questionnaire.choix[rang].indexOf(reponses[rang] ?? '')
    return index < 0 ? undefined : codes[index]
  }
  return {
    typeDeVoyage: code(CODES_QUESTIONNAIRE[0], 0) ?? ANCIENS_TYPES[reponses[0] ?? ''],
    voyageurs: code(CODES_QUESTIONNAIRE[1], 1),
    budget: code(CODES_QUESTIONNAIRE[2], 2),
  }
}

/**
 * Convertit les trois réponses de l'accueil en critères immédiatement visibles dans le récapitulatif. « Plus de
 * 3 000 € » n'a pas de montant cible : la tranche reste affichée, l'assistant proposera trois fourchettes.
 */
export function demandeDepuisQuestionnaire(reponses: string[], precedente: Demande): Demande {
  const { typeDeVoyage: type, voyageurs, budget } = codesDuQuestionnaire(reponses)
  const adultes = voyageurs === 'deux' ? 2 : voyageurs === 'seul' ? 1 : undefined
  const montant = budget === 'moins_de_1500' ? 1500 : budget === 'de_1500_a_3000' ? 3000 : undefined

  return {
    ...precedente,
    ...(type ? { type } : {}),
    ...(adultes !== undefined ? { adultes } : {}),
    ...(budget !== undefined
      ? { budget: montant, budgetIndicatif: reponses[2], budgetParPersonne: false }
      : {}),
  }
}

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
    case 'voyageurs':
      return d.adultes !== undefined
    case 'budget':
      return d.budget !== undefined || d.budgetIndicatif !== undefined
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
      return `${majuscule(langue === 'en' ? nomDuMois(d.mois, langue) : MOIS[d.mois])}${d.annee ? ` ${d.annee}` : ''}${d.datesFlexibles ? t.datesFlexibles : ''}`
    case 'duree':
      return d.jours ? t.jours(d.jours) : undefined
    case 'voyageurs': {
      if (d.adultes === undefined) return undefined
      const enfants = d.enfants ? ` · ${t.enfants(d.enfants)}` : ''
      const chambre = d.adultes === 2 && !d.enfants ? t.chambreDouble : ''
      return t.adultes(d.adultes) + enfants + chambre
    }
    case 'budget': {
      const montant =
        d.budgetIndicatif ?? (d.budget === undefined ? undefined : formatEuros(d.budget))
      if (montant === undefined) return undefined
      const base = `${montant} ${d.budgetParPersonne ? t.parPersonne : t.auTotal}`
      return d.depart ? `${base} · ${t.departDe(d.depart)}` : base
    }
    case 'envies':
      return d.envies.length ? majuscule(d.envies.join(', ')) : undefined
    case 'contraintes':
      return d.contraintes.length ? majuscule(d.contraintes.join(' · ')) : undefined
  }
}
