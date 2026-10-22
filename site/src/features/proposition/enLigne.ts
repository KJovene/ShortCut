import { queryOptions } from '@tanstack/react-query'
import { z } from 'zod'
import { TYPES_DE_VOYAGE } from '@/data/catalogue'
import { ApiError } from '@/lib/api'
import type { Langue } from '@/lib/langue'
import { TEXTES } from './textes'

/**
 * Proposition envoyée par le conseiller, lue par son lien unique : GET /api/propositions/{jeton}
 * (api/src/Confirmation/PropositionClient.php). Les prix d'achat ne sont jamais transmis, seulement les totaux.
 */

/** Jeton public d'une proposition : 64 caractères hexadécimaux, non devinable. */
export const estJetonProposition = (id: string) => /^[0-9a-f]{64}$/.test(id)

/** Les trois niveaux de budget d'une proposition, du moins cher au plus cher. */
export const NIVEAUX = [
  { valeur: 'essentiel', label: 'Essentiel' },
  { valeur: 'equilibre', label: 'Équilibré' },
  { valeur: 'confort', label: 'Confort' },
] as const

export type Niveau = (typeof NIVEAUX)[number]['valeur']

const NIVEAU = z.enum(NIVEAUX.map((n) => n.valeur))
const ETAT = z.enum(['estimee', 'confirmee'])
const Budget = z.object({ totalCentimes: z.number(), parPersonneCentimes: z.number() })

export const PropositionEnLigne = z.object({
  jeton: z.string(),
  /** Langue dans laquelle le client a dialogué avec le chatbot (US15) : celle de la page. */
  langue: z.enum(['fr', 'en']).default('fr'),
  /** Version du lien ouvert, quand elle a été remplacée par celle-ci. */
  versionDemandee: z.number().nullable(),
  version: z.number(),
  etat: ETAT,
  statut: z.string(),
  mention: z.string(),
  creeLe: z.string(),
  confirmeeLe: z.string().nullable(),
  niveauBudget: NIVEAU,
  budgetsParNiveau: z.partialRecord(NIVEAU, Budget),
  totalCentimes: z.number(),
  totalParPersonneCentimes: z.number(),
  fraisDeDossierCentimes: z.number(),
  /** Photo et climat de la destination, tels que le backoffice les décrit (absents des anciennes réponses). */
  destination: z
    .object({
      slug: z.string().nullable(),
      ville: z.string(),
      photo: z.string().nullable().default(null),
      photoAlt: z.string().nullable().default(null),
      /** Température moyenne de chaque mois, index 0 = janvier ; null si le mois n'est pas saisi. */
      temperatures: z.array(z.number().nullable()).default([]),
    })
    .nullable(),
  pourquoi: z.string().nullable(),
  argumentaire: z.array(z.object({ exigence: z.string(), reponse: z.string() })),
  hypotheses: z.array(z.string()),
  programme: z.array(
    z.object({ jour: z.number(), date: z.string(), titre: z.string(), description: z.string() }),
  ),
  prestations: z.array(
    z.object({
      id: z.number(),
      type: z.string(),
      nom: z.string(),
      pointsForts: z.array(z.string()),
      niveaux: z.array(z.string()),
      /** Photo principale de la prestation dans le catalogue. */
      photo: z.string().nullable().default(null),
    }),
  ),
  lignes: z.array(
    z.object({
      type: z.string().nullable(),
      libelle: z.string(),
      dateDebut: z.string().nullable(),
      dateFin: z.string().nullable(),
      quantite: z.number(),
    }),
  ),
  demande: z.object({
    statut: z.string(),
    typeDeVoyage: z.enum(TYPES_DE_VOYAGE.map((t) => t.valeur)).nullable(),
    lieuOuZone: z.string().nullable(),
    dateDebut: z.string().nullable(),
    dateFin: z.string().nullable(),
    adultes: z.number(),
    enfants: z.number(),
  }),
  /** Toutes les versions reçues par le client, de la plus ancienne à la plus récente (#211). */
  versions: z.array(
    z.object({
      version: z.number(),
      jeton: z.string(),
      etat: ETAT,
      statut: z.string(),
      creeLe: z.string(),
      totalCentimes: z.number(),
    }),
  ),
  actions: z.object({ demanderConfirmation: z.boolean(), accepter: z.boolean() }),
})
export type PropositionEnLigne = z.infer<typeof PropositionEnLigne>

async function lire(response: Response): Promise<PropositionEnLigne> {
  if (!response.ok) {
    // 409 : action refusée, avec le message à montrer au client (déjà acceptée, plus confirmable…).
    const corps: unknown = await response.json().catch(() => null)
    const erreur = z.object({ erreur: z.string() }).safeParse(corps)
    throw new ApiError(
      response.status,
      erreur.success ? erreur.data.erreur : 'Une erreur est survenue, réessayez dans un instant.',
    )
  }
  return PropositionEnLigne.parse(await response.json())
}

/** La proposition du lien, ou null si le lien ne mène à aucune proposition envoyée (404). */
export async function chargerProposition(jeton: string): Promise<PropositionEnLigne | null> {
  const response = await fetch(`/api/propositions/${jeton}`, {
    headers: { Accept: 'application/json' },
  })
  if (response.status === 404) return null
  return lire(response)
}

export const propositionQuery = (jeton: string) =>
  queryOptions({
    queryKey: ['propositions', jeton],
    queryFn: () => chargerProposition(jeton),
  })

/** Une version précise de la même proposition, même remplacée : GET /api/propositions/{jeton}/versions/{numero}. */
export async function chargerVersion(jeton: string, numero: number): Promise<PropositionEnLigne> {
  const response = await fetch(`/api/propositions/${jeton}/versions/${numero}`, {
    headers: { Accept: 'application/json' },
  })
  return lire(response)
}

export const versionQuery = (jeton: string, numero: number) =>
  queryOptions({
    queryKey: ['propositions', jeton, 'versions', numero],
    queryFn: () => chargerVersion(jeton, numero),
    // Une version reçue ne change plus : seul son statut peut évoluer, la page actuelle le montre.
    staleTime: Infinity,
  })

/** Le PDF d'une version (#213) : GET /api/propositions/{jeton}/versions/{numero}/pdf. */
export const pdfProposition = (jeton: string, numero: number) =>
  `/api/propositions/${jeton}/versions/${numero}/pdf`

export type ActionProposition = 'demande-de-confirmation' | 'acceptation'

/** « Demander la confirmation » d'une proposition estimée, ou « J'accepte » une proposition confirmée. */
export async function agirSurProposition(
  jeton: string,
  action: ActionProposition,
): Promise<PropositionEnLigne> {
  const response = await fetch(`/api/propositions/${jeton}/${action}`, {
    method: 'POST',
    headers: { Accept: 'application/json' },
  })
  return lire(response)
}

/** Nombre de voyageurs, pour l'en-tête : « 2 adultes, 1 enfant ». */
export function voyageurs(
  { adultes, enfants }: PropositionEnLigne['demande'],
  langue: Langue = 'fr',
) {
  const t = TEXTES[langue]
  return [t.adulte(adultes), enfants > 0 ? t.enfant(enfants) : null].filter(Boolean).join(', ')
}

/** « Santorin, 8 jours » : la destination retenue et la durée du programme. */
export function titreProposition(p: PropositionEnLigne) {
  const t = TEXTES[p.langue]
  const lieu = p.destination?.ville ?? p.demande.lieuOuZone ?? t.votreVoyage
  return p.programme.length > 0 ? `${lieu}, ${p.programme.length} ${t.jours}` : lieu
}
