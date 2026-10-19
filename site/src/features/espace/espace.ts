import { queryOptions } from '@tanstack/react-query'
import { z } from 'zod'
import { DESTINATIONS, labelType, MOIS, TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'
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
  proposition: z
    .object({
      version: z.number(),
      statut: z.string(),
      niveauBudget: z.string(),
      totalCentimes: z.number(),
      totalParPersonneCentimes: z.number(),
    })
    .nullable(),
  conversation: z.object({ debutLe: z.string(), messages: z.array(Message) }).nullable(),
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

/** Libellé et couleurs du statut, du point de vue du client. */
export const STATUT_CLIENT: Record<StatutDemande, { label: string; classe: string }> = {
  nouvelle: { label: 'Demande envoyée', classe: 'bg-[#E3EDF8] text-[#1E4E8C]' },
  proposition_envoyee: { label: 'Proposition reçue', classe: 'bg-[#FFF1D0] text-[#7A5200]' },
  a_confirmer: { label: 'En cours de confirmation', classe: 'bg-[#FFF1D0] text-[#7A5200]' },
  confirmee: { label: 'Confirmée, à valider', classe: 'bg-[#DDF1E4] text-[#1F6B3A]' },
  acceptee: { label: 'Voyage réservé', classe: 'bg-vert-fonce text-white' },
  perdue: { label: 'Clôturée', classe: 'bg-[#EFEAE4] text-[#5A4A3E]' },
}

/** Une demande en cours : ni réservée, ni clôturée. */
export function estEnCours(demande: DemandeClient) {
  return demande.statut !== 'acceptee' && demande.statut !== 'perdue'
}

export type EtatEtape = 'faite' | 'en-cours' | 'a-venir'

export interface Etape {
  titre: string
  detail: string
  etat: EtatEtape
}

/** Nombre d'étapes franchies selon le statut ; la suivante est l'étape en cours. */
const ETAPES_FAITES: Record<StatutDemande, number> = {
  nouvelle: 1,
  proposition_envoyee: 2,
  a_confirmer: 2,
  confirmee: 3,
  acceptee: 4,
  perdue: 0,
}

const dateCourte = (iso: string) =>
  new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(new Date(iso))

const heure = (iso: string) =>
  new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date(iso))

/** Les cinq étapes du voyage, de la demande à la réservation. */
export function etapes(demande: DemandeClient): Etape[] {
  const conseiller = prenomConseiller(demande) ?? 'votre conseiller'
  const titres: [string, string][] = [
    ['Demande envoyée', `${dateCourte(demande.creeLe)}, ${heure(demande.creeLe)}`],
    ['Proposition reçue', 'Prix estimé par l’assistant'],
    [`Confirmation par ${conseiller}`, 'Prix et disponibilités'],
    ['Votre accord', 'Vous validez la version confirmée'],
    ['Réservation', 'Avec votre conseiller'],
  ]
  const faites = demande.statut === 'perdue' ? 0 : ETAPES_FAITES[demande.statut]

  return titres.map(([titre, detail], i) => ({
    titre,
    detail,
    etat:
      i < faites ? 'faite' : i === faites && demande.statut !== 'perdue' ? 'en-cours' : 'a-venir',
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

/** Destination du catalogue citée par la demande, pour sa photo et son climat. */
export function destinationDe(demande: DemandeClient) {
  const lieu = demande.lieuOuZone?.toLowerCase().trim()
  if (!lieu) return undefined
  return DESTINATIONS.find(
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
