import { DESTINATIONS, formatEuros, labelType, MOIS, type TypeDeVoyage } from '@/data/catalogue'

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
  /** Destination précise retenue (slug du catalogue). */
  destination?: string
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
  /** Réponses libres que l'extraction n'a pas su structurer. */
  libres: Partial<Record<Critere, string>>
}

export const demandeVide = (): Demande => ({
  envies: [],
  contraintes: [],
  contraintesSignalees: [],
  libres: {},
})

const NOMBRES: Record<string, number> = {
  un: 1,
  une: 1,
  deux: 2,
  trois: 3,
  quatre: 4,
  cinq: 5,
  six: 6,
  sept: 7,
  huit: 8,
  neuf: 9,
  dix: 10,
  douze: 12,
  quinze: 15,
  vingt: 20,
}

const NOMBRE = `(\\d+|${Object.keys(NOMBRES).join('|')})`

function nombre(mot: string) {
  return NOMBRES[mot] ?? Number.parseInt(mot, 10)
}

function normaliser(texte: string) {
  return texte
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .replace(/\s+/g, ' ')
}

const MOIS_NORMALISES = MOIS.map((m) => normaliser(m))

const ENVIES: [RegExp, string][] = [
  [/plage|baignade|mer chaude|se baigner|lagon/, 'baignade'],
  [/culture|musee|visite|histoire|temple/, 'culture'],
  [/gastronomie|cuisine|manger|vin|gastro/, 'cuisine locale'],
  [/nature|paysage|volcan|foret|cascade/, 'nature'],
  [/randonnee|marche|trek/, 'randonnée'],
  [/detente|se poser|farniente|repos|spa|calme/, 'se poser'],
  [/romanti|en amoureux/, 'romantique'],
  [/aventure|sensation/, 'aventure'],
  [/sport|surf|plongee|kayak/, 'sport'],
  [/village/, 'villages'],
  [/soleil|chaud|chaleur/, 'soleil'],
]

const CONTRAINTES_SIGNALEES: [RegExp, string][] = [
  [/vegetarien/, 'végétarien'],
  [/vegan|vegetalien/, 'végétalien'],
  [/sans gluten|coeliaque/, 'sans gluten'],
  [/allergi\w*(?:\s+(?:aux?|a la|au)\s+([a-z]+))?/, 'allergie'],
  [/mobilite reduite|fauteuil|handicap|pmr/, 'mobilité réduite'],
  [/enceinte/, 'grossesse'],
]

const ZONES: [RegExp, string][] = [
  [/mediterranee/, 'Méditerranée'],
  [/europe/, 'Europe'],
  [/asie/, 'Asie'],
  [/amerique|amerique latine/, 'Amérique'],
  [/afrique|maghreb/, 'Afrique du Nord'],
  [/\biles?\b/, 'Une île'],
]

function ajouter(liste: string[], valeur: string) {
  return liste.includes(valeur) ? liste : [...liste, valeur]
}

/**
 * Extrait les exigences d'un message du client. Volontairement simple : c'est le relais de démonstration
 * de l'agent Conversation, en attendant l'appel à Claude (POST /api/chat, ticket #192).
 */
export function extraire(texte: string, avant: Demande): Demande {
  const t = normaliser(texte)
  const d: Demande = { ...avant, libres: { ...avant.libres } }

  // Durée
  const duree = new RegExp(`${NOMBRE}\\s*(jours|jour|nuits|nuit|semaines|semaine)`).exec(t)
  if (duree) {
    const n = nombre(duree[1])
    d.jours = duree[2].startsWith('semaine') ? n * 7 : duree[2].startsWith('nuit') ? n + 1 : n
  } else if (/dizaine de jours/.test(t)) d.jours = 10
  else if (/quinzaine/.test(t)) d.jours = 14
  else if (/long week-?end/.test(t)) d.jours = 4
  else if (/week-?end/.test(t)) d.jours = 3

  // Période
  const mois = MOIS_NORMALISES.findIndex((m) => new RegExp(`\\b${m}\\b`).test(t))
  if (mois >= 0) d.mois = mois
  if (/flexible|a peu pres|souple/.test(t)) d.datesFlexibles = true

  // Température
  const temperature = /(\d{1,2})\s*(?:°|degres|deg)/.exec(t)
  if (temperature) d.temperatureMin = Number.parseInt(temperature[1], 10)

  // Voyageurs
  const adultes = new RegExp(`${NOMBRE}\\s*(adultes|adulte|personnes|personne|pers\\b)`).exec(t)
  if (adultes) d.adultes = nombre(adultes[1])
  else if (
    /a deux|en couple|nous deux|ma femme|mon mari|ma compagne|mon compagnon|mon conjoint|ma conjointe|en amoureux/.test(
      t,
    )
  )
    d.adultes = 2
  else if (/\bseule?\b|en solo/.test(t)) d.adultes = 1
  const enfants = new RegExp(`${NOMBRE}\\s*enfants?`).exec(t)
  if (enfants) d.enfants = nombre(enfants[1])
  else if (/sans enfant/.test(t)) d.enfants = 0

  // Budget
  const budget = /(\d[\d\s.]*\d|\d)\s*(?:€|euros?|eur\b|k€)/.exec(t)
  if (budget) {
    const montant = Number.parseInt(budget[1].replace(/[\s.]/g, ''), 10)
    d.budget = /k€/.test(budget[0]) ? montant * 1000 : montant
    d.budgetParPersonne = /par personne|par pers|chacun/.test(t)
  }

  // Ville de départ
  const depart =
    /(?:de|depuis|au depart de)\s+(paris|lyon|bordeaux|marseille|lille|nantes|toulouse)/.exec(t)
  if (depart) d.depart = depart[1][0].toUpperCase() + depart[1].slice(1)

  // Type de voyage
  if (/noces|mariage|lune de miel/.test(t)) d.type = 'noces'
  else if (/seminaire|entreprise|incentive|collegues|convention/.test(t)) d.type = 'entreprise'
  else if (/\bgroupe\b|association|scolaire|entre amis|club/.test(t)) d.type = 'groupe'
  else if (/circuit|plusieurs etapes|road ?trip|itinerant/.test(t)) d.type = 'circuit'
  else if (/sejour|se poser/.test(t) && !d.type) d.type = 'sejour'

  // Destination précise ou zone
  const destination = DESTINATIONS.find((dest) =>
    [dest.slug, dest.ville, dest.pays, dest.region].some((nom) =>
      new RegExp(`\\b${normaliser(nom)}\\b`).test(t),
    ),
  )
  if (destination) d.destination = destination.slug
  const zone = ZONES.find(([motif]) => motif.test(t))
  if (zone) d.zone = zone[1]
  const etapes = /(?:deux|2) (?:endroits|etapes|villes) max/.test(t)
  if (etapes) d.zone = d.zone ? `${d.zone} · 2 étapes max` : '2 étapes max'

  // Envies
  for (const [motif, envie] of ENVIES) if (motif.test(t)) d.envies = ajouter(d.envies, envie)
  if (/famille|enfants/.test(t)) d.envies = ajouter(d.envies, 'en famille')

  // Contraintes
  if (/sans voiture|pas de voiture|(?:veut|veux|voulons|aime) pas conduire|sans conduire/.test(t))
    d.contraintes = ajouter(d.contraintes, 'sans voiture')
  for (const [motif, contrainte] of CONTRAINTES_SIGNALEES) {
    const trouve = motif.exec(t)
    if (trouve) {
      const libelle = trouve[1] ? `${contrainte} (${trouve[1]})` : contrainte
      d.contraintes = ajouter(d.contraintes, libelle)
      d.contraintesSignalees = ajouter(d.contraintesSignalees, libelle)
    }
  }

  return d
}

/** Vrai si le critère a une valeur, structurée ou libre. */
export function estRenseigne(d: Demande, critere: Critere): boolean {
  if (d.libres[critere]) return true
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
      if (!d.type) return d.libres.type
      const voyageurs = (d.adultes ?? 0) + (d.enfants ?? 0)
      return d.type === 'sejour' && voyageurs === 2 && !d.enfants
        ? 'Séjour à deux'
        : labelType(d.type)
    }
    case 'destination': {
      const dest = DESTINATIONS.find((x) => x.slug === d.destination)
      if (dest) return d.zone ? `${dest.nom} · ${d.zone}` : dest.nom
      return d.zone ?? d.libres.destination
    }
    case 'periode':
      if (d.mois === undefined) return d.libres.periode
      return `${majuscule(MOIS[d.mois])} 2027${d.datesFlexibles ? ', dates flexibles' : ''}`
    case 'duree':
      return d.jours ? `${d.jours} jours` : d.libres.duree
    case 'temperature':
      return d.temperatureMin !== undefined
        ? `Au moins ${d.temperatureMin} °C`
        : d.libres.temperature
    case 'voyageurs': {
      if (d.adultes === undefined) return d.libres.voyageurs
      const adultes = `${d.adultes} adulte${d.adultes > 1 ? 's' : ''}`
      const enfants = d.enfants ? ` · ${d.enfants} enfant${d.enfants > 1 ? 's' : ''}` : ''
      const chambre = d.adultes === 2 && !d.enfants ? ' · 1 chambre double' : ''
      return adultes + enfants + chambre
    }
    case 'budget': {
      if (d.budget === undefined) return d.libres.budget
      const base = `${formatEuros(d.budget)} ${d.budgetParPersonne ? 'par personne' : 'au total'}`
      return d.depart ? `${base} · départ ${d.depart}` : base
    }
    case 'envies':
      return d.envies.length ? majuscule(d.envies.join(', ')) : d.libres.envies
    case 'contraintes':
      return d.contraintes.length ? majuscule(d.contraintes.join(' · ')) : d.libres.contraintes
  }
}
