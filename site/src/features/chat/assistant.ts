import { DESTINATIONS, labelType, MOIS } from '@/data/catalogue'
import { type Critere, type Demande, estRenseigne, extraire } from './demande'

export interface Ambiance {
  slug: string
  titre: string
  lieu: string
  photo: string
  temperature?: number
}

export type EtatEtape = 'fait' | 'en-cours' | 'attente'

export type Message =
  | { id: string; auteur: 'client'; texte: string }
  | { id: string; auteur: 'assistant'; texte: string }
  | {
      id: string
      auteur: 'assistant'
      type: 'ambiances'
      texte: string
      ambiances: Ambiance[]
      choix?: string
    }
  | {
      id: string
      auteur: 'assistant'
      type: 'preparation'
      texte: string
      etapes: { label: string; etat: EtatEtape }[]
    }
  | { id: string; auteur: 'assistant'; type: 'proposition'; texte: string; propositionId: string }

export interface Conversation {
  demande: Demande
  /** Critère sur lequel porte la dernière question posée. */
  enAttente?: Critere | 'ambiance'
  /** Critères déjà demandés une fois : on ne repose pas la question. */
  demandes: (Critere | 'ambiance')[]
}

let suivant = 0
export const nouvelId = () => `m${Date.now().toString(36)}${(suivant++).toString(36)}`

/** Ordre dans lequel l'assistant complète la fiche demande. */
const ORDRE: Critere[] = [
  'periode',
  'duree',
  'voyageurs',
  'budget',
  'temperature',
  'destination',
  'envies',
  'contraintes',
]

const QUESTIONS: Record<Critere, (d: Demande) => string> = {
  periode: () =>
    'À quelle période aimeriez-vous partir ? Vos dates sont-elles fixes ou flexibles ?',
  duree: () => 'Combien de jours souhaitez-vous partir ?',
  voyageurs: () => 'Combien serez-vous ? Précisez l’âge des enfants s’il y en a.',
  budget: () =>
    'Quel budget envisagez-vous ? Dites-moi s’il est par personne ou au total, et s’il comprend les vols.',
  temperature: () => 'Quand vous pensez « soleil », quelle température vous ferait plaisir ?',
  destination: () => 'Avez-vous une destination ou une région en tête ?',
  contraintes: () =>
    'Une dernière chose : avez-vous des contraintes particulières ? Régime alimentaire, mobilité, allergies…',
  type: () =>
    'Quel type de voyage imaginez-vous : séjour, circuit, groupe, voyage de noces ou entreprise ?',
  envies: () => 'Qu’aimeriez-vous faire sur place ?',
}

/** Destinations compatibles avec la demande, triées de la plus chaude à la plus fraîche aux dates. */
export function ambiancesCompatibles(d: Demande): Ambiance[] {
  const mois = d.mois ?? 5
  const compatibles = DESTINATIONS.filter(
    (dest) =>
      (d.temperatureMin === undefined || dest.temperatures[mois] >= d.temperatureMin) &&
      (d.type === undefined || d.type === 'sejour' || dest.types.includes(d.type)),
  )
  const liste = (compatibles.length ? compatibles : DESTINATIONS)
    .slice()
    .sort((a, b) => b.temperatures[mois] - a.temperatures[mois])
    .slice(0, 3)
  return liste.map((dest) => ({
    slug: dest.slug,
    titre: dest.pointsForts[0],
    lieu: `${dest.ville} · ${labelType(d.type && dest.types.includes(d.type) ? d.type : dest.types[0]).toLowerCase()}`,
    photo: dest.photo,
    temperature: dest.temperatures[mois],
  }))
}

function prochainCritere(c: Conversation): Critere | 'ambiance' | undefined {
  for (const critere of ORDRE) {
    if (critere === 'destination') {
      if (!c.demande.destination && !c.demandes.includes('ambiance')) return 'ambiance'
      continue
    }
    if (!estRenseigne(c.demande, critere) && !c.demandes.includes(critere)) return critere
  }
  return undefined
}

export interface Reponse {
  conversation: Conversation
  messages: Message[]
  /** Vrai quand la fiche est complète : l'appelant lance la préparation de la proposition. */
  complete: boolean
}

const ACCUSES = ['C’est noté !', 'Parfait.', 'Très bien.', 'Entendu.']

/**
 * Agent Conversation simulé : met à jour la fiche demande et choisit la question suivante.
 * À remplacer par le flux de POST /api/chat (ticket #192).
 */
export function repondre(c: Conversation, texte: string): Reponse {
  let demande = extraire(texte, c.demande)

  // Réponse libre à la question posée : on la garde telle quelle si l'extraction n'a rien trouvé.
  const attente = c.enAttente
  if (attente && attente !== 'ambiance' && !estRenseigne(demande, attente)) {
    const sansContrainte =
      attente === 'contraintes' && /^(non|aucune?|rien|pas de)/i.test(texte.trim())
    demande = {
      ...demande,
      contraintes: sansContrainte ? ['aucune'] : demande.contraintes,
      libres: sansContrainte ? demande.libres : { ...demande.libres, [attente]: texte.trim() },
    }
  }
  if (!demande.type && (demande.adultes ?? 0) > 0) demande = { ...demande, type: 'sejour' }

  const conversation: Conversation = { ...c, demande }
  return poserSuite(
    conversation,
    demande.contraintesSignalees.length > c.demande.contraintesSignalees.length,
  )
}

/** Choix d'une des trois ambiances proposées. */
export function choisirAmbiance(c: Conversation, slug: string): Reponse {
  const demande = { ...c.demande, destination: slug }
  return poserSuite({ ...c, demande }, false)
}

function poserSuite(c: Conversation, signalee: boolean): Reponse {
  const accuse = signalee
    ? 'C’est noté, je le signale à votre conseiller.'
    : ACCUSES[c.demandes.length % ACCUSES.length]
  const critere = prochainCritere(c)

  if (critere === 'ambiance') {
    const d = c.demande
    const contexte = [
      d.mois !== undefined ? `en ${MOIS[d.mois]}` : undefined,
      d.temperatureMin !== undefined ? `au-dessus de ${d.temperatureMin} °C` : undefined,
      d.contraintes.includes('sans voiture') ? 'sans voiture' : undefined,
    ]
      .filter(Boolean)
      .join(', ')
    return {
      conversation: { ...c, enAttente: 'ambiance', demandes: [...c.demandes, 'ambiance'] },
      messages: [
        {
          id: nouvelId(),
          auteur: 'assistant',
          type: 'ambiances',
          texte: `${accuse} ${contexte ? `${contexte[0].toUpperCase()}${contexte.slice(1)}, trois` : 'Trois'} ambiances s’offrent à vous. Laquelle vous fait envie ?`,
          ambiances: ambiancesCompatibles(d),
        },
      ],
      complete: false,
    }
  }

  if (critere) {
    return {
      conversation: { ...c, enAttente: critere, demandes: [...c.demandes, critere] },
      messages: [
        {
          id: nouvelId(),
          auteur: 'assistant',
          texte: `${accuse} ${QUESTIONS[critere](c.demande)}`,
        },
      ],
      complete: false,
    }
  }

  return {
    conversation: { ...c, enAttente: undefined },
    messages: [],
    complete: true,
  }
}

export const MESSAGE_ACCUEIL: Message = {
  id: 'accueil',
  auteur: 'assistant',
  texte:
    'Bonjour ! Je suis l’assistant de l’agence, une IA. Racontez-moi le voyage dont vous rêvez.',
}

export function etapesPreparation(d: Demande): string[] {
  const mois = d.mois !== undefined ? ` en ${MOIS[d.mois]}` : ''
  const temperature = d.temperatureMin !== undefined ? ` à plus de ${d.temperatureMin} °C` : ''
  return [
    `Climat${temperature}${mois} vérifié`,
    `Hébergements pour ${(d.adultes ?? 2) + (d.enfants ?? 0)} trouvés`,
    `Calcul des 3 budgets${d.budget ? ` autour de ${new Intl.NumberFormat('fr-FR').format(d.budget)} €` : ''}`,
    'Rédaction du programme jour par jour',
  ]
}
