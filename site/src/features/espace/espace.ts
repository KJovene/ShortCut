import { queryOptions } from '@tanstack/react-query'
import { z } from 'zod'
import { labelType, MOIS, TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'
import type { DestinationPublique } from '@/features/catalogue/catalogue'
import { ficheSchema } from '@/features/chat/api'
import { ApiError } from '@/lib/api'

const STATUTS = [
  'nouvelle',
  'proposition_envoyee',
  'a_confirmer',
  'confirmee',
  'acceptee',
  'perdue',
] as const
export type StatutDemande = (typeof STATUTS)[number]

const Message = z.object({ role: z.string(), contenu: z.string(), le: z.string() })

export const DemandeClient = z.object({
  id: z.number(),
  statut: z.enum(STATUTS),
  typeDeVoyage: z.enum(TYPES_DE_VOYAGE.map((t) => t.valeur)).nullable(),
  lieuOuZone: z.string().nullable(),
  dateDebut: z.string().nullable(),
  nombreDeJours: z.number().nullable(),
  adultes: z.number(),
  enfants: z.number(),
  creeLe: z.string(),
  conseiller: z.object({ prenom: z.string().nullable(), nom: z.string() }).nullable(),
  /** Date à laquelle l'assistant a passé la main à un conseiller ; null s'il ne l'a pas fait. */
  transmiseLe: z.string().nullable(),
  proposition: z
    .object({
      jeton: z.string(),
      version: z.number(),
      /** Prix estimés par l'assistant, ou confirmés par le conseiller. */
      etat: z.enum(['estimee', 'confirmee']),
      statut: z.string(),
      niveauBudget: z.string(),
      totalCentimes: z.number(),
      totalParPersonneCentimes: z.number(),
    })
    .nullable(),
  conversation: z
    .object({
      /** Jeton du chat : le client reprend sa conversation pour un changement léger. */
      jeton: z.string(),
      /** Un conseiller a repris la main : le chatbot ne répond plus. */
      repriseParConseiller: z.boolean(),
      debutLe: z.string(),
      messages: z.array(Message),
      fiche: ficheSchema,
    })
    .nullable(),
})
export type DemandeClient = z.infer<typeof DemandeClient>

/** Demandes du client connecté, les plus récentes d'abord. */
export async function chargerMesDemandes(): Promise<DemandeClient[]> {
  const response = await fetch('/api/moi/demandes', { headers: { Accept: 'application/json' } })
  if (!response.ok) {
    throw new ApiError(response.status, 'Impossible de charger vos voyages.')
  }
  return z.array(DemandeClient).parse(await response.json())
}

export const mesDemandesQuery = queryOptions({
  queryKey: ['moi', 'demandes'],
  queryFn: chargerMesDemandes,
})

const CLASSE_STATUT = {
  info: 'bg-[#BCD3EA] text-[#16233F]',
  attente: 'bg-[#E2D9C6] text-[#16233F]',
  succes: 'bg-[#BCD3EA] text-[#16233F]',
  fini: 'bg-vert-fonce text-white',
  clos: 'bg-[#EAE2D2] text-[#3B4762]',
} as const

/**
 * Libellé et couleurs du statut, du point de vue du client. Tant que la demande est entre les mains d'un
 * conseiller, c'est lui que le client voit : « Pris en charge par Claire ».
 */
export function statutClient(demande: DemandeClient): { label: string; classe: string } {
  const conseiller = prenomConseiller(demande)
  switch (demande.statut) {
    case 'confirmee':
      return { label: 'Confirmée, à valider', classe: CLASSE_STATUT.succes }
    case 'acceptee':
      return { label: 'Voyage accepté', classe: CLASSE_STATUT.fini }
    case 'perdue':
      return { label: 'Clôturée', classe: CLASSE_STATUT.clos }
    default:
      if (conseiller)
        return { label: `Pris en charge par ${conseiller}`, classe: CLASSE_STATUT.attente }
      return demande.statut === 'nouvelle'
        ? { label: 'Demande envoyée', classe: CLASSE_STATUT.info }
        : { label: 'Proposition reçue', classe: CLASSE_STATUT.attente }
  }
}

/** Une demande en cours : ni réservée, ni clôturée. */
/** Voyages en cours au plus par client (Demande::VOYAGES_EN_COURS_MAX côté API). */
export const VOYAGES_EN_COURS_MAX = 3

export function estEnCours(demande: DemandeClient) {
  return demande.statut !== 'acceptee' && demande.statut !== 'perdue'
}

export type EtatEtape = 'faite' | 'en-cours' | 'a-venir'

export interface Etape {
  titre: string
  detail: string
  etat: EtatEtape
}

const dateCourte = (iso: string) =>
  new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(new Date(iso))

/**
 * Nombre d'étapes franchies ; l'étape suivante est l'étape en cours. La prise en charge dure jusqu'à la
 * confirmation du conseiller, la finalisation jusqu'à l'accord du client, puis viennent les réservations.
 */
function etapesFaites(demande: DemandeClient) {
  if (demande.statut === 'acceptee') return 3
  if (demande.statut === 'confirmee') return 2
  return 1
}

/**
 * Le client peut ajuster sa fiche avec l'assistant (« Modifier le voyage ») : la demande vient du chat et aucun
 * conseiller n'a repris la conversation, ce qui fait taire le chatbot. Une simple prise en charge le laisse ouvert.
 */
export function assistantDisponible(demande: DemandeClient) {
  return demande.conversation !== null && !demande.conversation.repriseParConseiller
}

/** Les quatre étapes du voyage, de la demande à la réservation des prestations (maquette E-Espace). */
export function etapes(demande: DemandeClient): Etape[] {
  const conseiller = prenomConseiller(demande)
  const depuis = demande.transmiseLe ? `Depuis le ${dateCourte(demande.transmiseLe)} · ` : ''
  const titres: [string, string][] = [
    [
      'Demande envoyée',
      assistantDisponible(demande)
        ? `${dateCourte(demande.creeLe)} · vous pouvez encore l’ajuster avec l’assistant`
        : `${dateCourte(demande.creeLe)} · avec l’assistant`,
    ],
    conseiller
      ? [
          `Prise en charge par ${conseiller}`,
          `${depuis}les changements importants passent par votre conseiller`,
        ]
      : ['Prise en charge', 'Un conseiller de l’agence va suivre votre dossier'],
    [
      'Finalisation',
      conseiller
        ? `Échanges avec ${conseiller} pour finaliser votre voyage`
        : 'Échanges avec votre conseiller pour finaliser votre voyage',
    ],
    ['Réservation des prestations', 'Vols, hôtels et activités réservés'],
  ]
  const faites = etapesFaites(demande)

  return titres.map(([titre, detail], i) => ({
    titre,
    detail,
    etat: i < faites ? 'faite' : i === faites ? 'en-cours' : 'a-venir',
  }))
}

export function prenomConseiller(demande: DemandeClient) {
  return demande.conseiller ? (demande.conseiller.prenom ?? demande.conseiller.nom) : null
}

export function initiales(prenom: string | null, nom: string) {
  return [prenom, nom]
    .filter(Boolean)
    .map((mot) => mot!.trim().charAt(0).toUpperCase())
    .join('')
}

/** Destination du catalogue de l'API citée par la demande, pour sa photo et son climat. */
export function destinationDe(demande: DemandeClient, destinations: DestinationPublique[]) {
  const lieu = demande.lieuOuZone?.toLowerCase().trim()
  if (!lieu) return undefined
  return destinations.find(
    (d) =>
      d.ville.toLowerCase() === lieu || d.slug === lieu || d.nom.toLowerCase().startsWith(lieu),
  )
}

/** Mois du départ (0 à 11), si la date est connue. */
export function moisDeDepart(demande: DemandeClient) {
  return demande.dateDebut ? new Date(demande.dateDebut).getMonth() : null
}

/** « Chypre, 10 jours », « Voyage de noces, Bali » ou « Votre projet de voyage ». */
export function titreDemande(demande: DemandeClient) {
  const lieu = demande.lieuOuZone
  const duree = demande.nombreDeJours ? `${demande.nombreDeJours} jours` : null
  const type = demande.typeDeVoyage ? labelType(demande.typeDeVoyage as TypeDeVoyage) : null
  if (lieu) return [lieu, duree ?? type].filter(Boolean).join(', ')
  return type ? `Projet : ${type.toLowerCase()}` : 'Votre projet de voyage'
}

/** « Dossier D-0228 · 2 adultes · mai 2027 » */
export function metaDemande(demande: DemandeClient) {
  const voyageurs = [
    `${demande.adultes} adulte${demande.adultes > 1 ? 's' : ''}`,
    demande.enfants > 0 ? `${demande.enfants} enfant${demande.enfants > 1 ? 's' : ''}` : null,
  ]
    .filter(Boolean)
    .join(', ')
  const depart = demande.dateDebut
    ? `${MOIS[new Date(demande.dateDebut).getMonth()]} ${new Date(demande.dateDebut).getFullYear()}`
    : null
  return [`Dossier ${referenceDossier(demande.id)}`, voyageurs, depart].filter(Boolean).join(' · ')
}

export function referenceDossier(id: number) {
  return `D-${String(id).padStart(4, '0')}`
}

export type AuteurMessage = 'client' | 'assistant' | 'info'

/** Les messages du client à droite, ceux de l'assistant à gauche, les notes du suivi au centre. */
export function auteurMessage(role: string): AuteurMessage {
  if (role === 'client' || role === 'user') return 'client'
  if (role === 'systeme' || role === 'system' || role === 'conseiller') return 'info'
  return 'assistant'
}

export { dateCourte }
