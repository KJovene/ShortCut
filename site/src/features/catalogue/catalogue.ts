import { queryOptions } from '@tanstack/react-query'
import { z } from 'zod'
import { TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'
import { ApiError, requete } from '@/lib/api'
import { type Langue, typeDeVoyage } from '@/lib/langue'

/**
 * Catalogue du site public, lu dans l'API (GET /api/public/destinations) : seules les destinations publiées dans
 * le backoffice y figurent, avec des prix de vente « à partir de » (marge de l'agence comprise).
 */

const TYPES = TYPES_DE_VOYAGE.map((t) => t.valeur) as readonly string[]

/**
 * Un champ vide peut être absent de la réponse : l'API (API Platform) omet les valeurs nulles. Il est lu comme null,
 * pour qu'une destination incomplète saisie dans le backoffice ne casse pas l'affichage.
 */
const vide = <T>(valeur: T | null | undefined): T | null => valeur ?? null

const ExempleApi = z.object({
  type: z.enum(['logement', 'activite']),
  nom: z.string(),
  categorie: z.string().nullish().transform(vide),
  capacite: z.number(),
  accessible: z.boolean(),
  dureeMinutes: z.number().nullish().transform(vide),
  envies: z.array(z.string()),
  photos: z.array(z.string()),
  prixAPartirDeEuros: z.number(),
  unite: z.enum(['par_nuit', 'par_personne']),
})

export const DestinationApi = z.object({
  slug: z.string(),
  nom: z.string(),
  ville: z.string(),
  pays: z.string(),
  region: z.string().nullish().transform(vide),
  description: z.string().nullish().transform(vide),
  accroche: z.string().nullish().transform(vide),
  pointsForts: z.array(z.string()).default([]),
  tempsDeTrajet: z.record(z.string(), z.number()).default({}),
  photo: z.string().nullish().transform(vide),
  photoAlt: z.string().nullish().transform(vide),
  typesDeVoyage: z.array(z.string()).default([]),
  moisIdeal: z.number().int().min(1).max(12).nullish().transform(vide),
  latitude: z.number().nullish().transform(vide),
  longitude: z.number().nullish().transform(vide),
  miseEnAvant: z.boolean(),
  periodesClimatiques: z
    .array(
      z.object({
        debut: z.string(),
        fin: z.string(),
        temperatureMoyenne: z.number(),
        pluieMm: z.number().nullish().transform(vide),
      }),
    )
    .default([]),
  prixAPartirDeParPersonneEuros: z.number().nullish().transform(vide),
  exemples: z.array(ExempleApi).default([]),
})
export type DestinationApi = z.infer<typeof DestinationApi>

export interface Exemple {
  nom: string
  categorie: string
  capacite: number
  accessible: boolean
  /** Activité : durée en minutes. */
  duree: number | null
  photo: string | null
  /** Prix de vente : par nuit pour un logement entier, par personne pour une activité. */
  prix: number
}

/** Destination telle que le site l'affiche. */
export interface DestinationPublique {
  slug: string
  nom: string
  ville: string
  pays: string
  region: string
  description: string
  accroche: string
  pointsForts: string[]
  /** Minutes de vol depuis chaque ville de départ. */
  tempsDeTrajet: Record<string, number>
  photo: string
  alt: string
  /** Le principal en premier. */
  types: TypeDeVoyage[]
  /** Températures moyennes de janvier à décembre (index 0 à 11), en °C ; null si le mois n'est pas saisi. */
  temperatures: (number | null)[]
  /** Pluie de janvier à décembre, en mm. */
  pluie: (number | null)[]
  /** Mois conseillé, index 0 à 11. */
  moisIdeal: number
  /** Position sur la carte du monde ; null si le conseiller ne l'a pas saisie. */
  lon: number | null
  lat: number | null
  miseEnAvant: boolean
  /** Par personne : 7 nuits à deux, vols depuis Paris, transferts, marge et frais de dossier compris. */
  prixAPartirDe: number | null
  logements: Exemple[]
  activites: Exemple[]
}

/** Climat de chaque mois : la période qui commence ce mois-là (les conseillers saisissent un mois par période). */
function parMois(periodes: DestinationApi['periodesClimatiques']) {
  const temperatures: (number | null)[] = Array(12).fill(null)
  const pluie: (number | null)[] = Array(12).fill(null)
  for (const p of periodes) {
    const mois = Number(p.debut.slice(5, 7)) - 1
    if (temperatures[mois] === null) {
      temperatures[mois] = Math.round(p.temperatureMoyenne)
      pluie[mois] = p.pluieMm
    }
  }
  return { temperatures, pluie }
}

/** Sans mois conseillé saisi : le plus chaud. */
function moisLePlusChaud(temperatures: (number | null)[]) {
  return temperatures.reduce<number>(
    (meilleur, t, i) => ((t ?? -Infinity) > (temperatures[meilleur] ?? -Infinity) ? i : meilleur),
    0,
  )
}

function versExemple(e: z.infer<typeof ExempleApi>): Exemple {
  return {
    nom: e.nom,
    categorie: e.categorie ?? '',
    capacite: e.capacite,
    accessible: e.accessible,
    duree: e.dureeMinutes,
    photo: e.photos[0] ?? null,
    prix: e.prixAPartirDeEuros,
  }
}

export function versDestination(d: DestinationApi): DestinationPublique {
  const { temperatures, pluie } = parMois(d.periodesClimatiques)
  return {
    slug: d.slug,
    nom: d.nom,
    ville: d.ville,
    pays: d.pays,
    region: d.region ?? d.pays,
    description: d.description ?? '',
    accroche: d.accroche ?? '',
    pointsForts: d.pointsForts,
    tempsDeTrajet: d.tempsDeTrajet,
    photo: d.photo ?? '',
    alt: d.photoAlt ?? '',
    types: d.typesDeVoyage.filter((t): t is TypeDeVoyage => TYPES.includes(t)),
    temperatures,
    pluie,
    moisIdeal: d.moisIdeal === null ? moisLePlusChaud(temperatures) : d.moisIdeal - 1,
    lon: d.longitude,
    lat: d.latitude,
    miseEnAvant: d.miseEnAvant,
    prixAPartirDe: d.prixAPartirDeParPersonneEuros,
    logements: d.exemples.filter((e) => e.type === 'logement').map(versExemple),
    activites: d.exemples.filter((e) => e.type === 'activite').map(versExemple),
  }
}

async function lire(chemin: string, parametres: Record<string, string> = {}) {
  const query = new URLSearchParams(parametres)
  return requete(`/public/${chemin}${query.size > 0 ? `?${query}` : ''}`, z.unknown(), {
    messageErreur: 'Impossible de charger les destinations.',
  })
}

/**
 * Valide chaque destination séparément : une destination mal saisie est écartée (et signalée dans la console)
 * au lieu de vider toute la liste.
 */
function destinationsValides(reponse: unknown): DestinationPublique[] {
  return z
    .array(z.unknown())
    .parse(reponse)
    .flatMap((brute) => {
      const destination = DestinationApi.safeParse(brute)
      if (destination.success) return [versDestination(destination.data)]
      console.warn('Destination ignorée, données incomplètes :', brute, destination.error.issues)
      return []
    })
}

export async function chargerDestinations(): Promise<DestinationPublique[]> {
  return destinationsValides(await lire('destinations'))
}

/** Destinations « mises en avant » dans le backoffice, pour la carte et le bandeau de l'accueil. */
export async function chargerDestinationsMisesEnAvant(): Promise<DestinationPublique[]> {
  return destinationsValides(await lire('destinations', { miseEnAvant: 'true' }))
}

export async function chargerDestination(slug: string): Promise<DestinationPublique> {
  return versDestination(DestinationApi.parse(await lire(`destinations/${slug}`)))
}

export const destinationsQuery = () =>
  queryOptions({
    queryKey: ['catalogue', 'destinations'],
    queryFn: () => chargerDestinations(),
    staleTime: 5 * 60_000,
  })

export const destinationsMisesEnAvantQuery = () =>
  queryOptions({
    queryKey: ['catalogue', 'destinations', 'misesEnAvant'],
    queryFn: () => chargerDestinationsMisesEnAvant(),
    staleTime: 5 * 60_000,
  })

export const destinationQuery = (slug: string) =>
  queryOptions({
    queryKey: ['catalogue', 'destination', slug],
    queryFn: () => chargerDestination(slug),
    staleTime: 5 * 60_000,
    // Une destination masquée ou inconnue ne reviendra pas en redemandant : 404 tout de suite.
    retry: (essais, erreur) => !(erreur instanceof ApiError && erreur.status === 404) && essais < 1,
  })

/** Frais de dossier par type de voyage, en centimes : le barème fixé par l'administrateur (règles de prix). */
export const fraisDeDossierQuery = queryOptions({
  queryKey: ['catalogue', 'frais-de-dossier'],
  queryFn: async () =>
    z.record(z.string(), z.number()).parse(await lire('frais-de-dossier')),
  staleTime: 5 * 60_000,
})

/** Type de voyage principal, pour les cartes ; « Sur mesure » si le conseiller n'en a choisi aucun. */
export function libelleTypePrincipal(d: DestinationPublique, langue: Langue = 'fr') {
  if (d.types[0]) return typeDeVoyage(d.types[0], langue)
  return langue === 'en' ? 'Tailor-made' : 'Sur mesure'
}

/** Destination placée sur la carte du monde de l'accueil. */
export type DestinationSurCarte = DestinationPublique & { lon: number; lat: number }

/**
 * Seules les destinations mises en avant dans le backoffice ont leur épingle : avec tout le catalogue, elles se
 * chevaucheraient. Les autres restent dans le bandeau sous la carte et sur la page Destinations.
 */
export const estSurCarte = (d: DestinationPublique): d is DestinationSurCarte =>
  d.miseEnAvant && d.lon !== null && d.lat !== null

const sansAccents = (texte: string) =>
  texte
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase()
    .trim()

/**
 * Destination du catalogue qui correspond au lieu saisi par le client (« Nairobi », « Kenya », « Maroc ») :
 * sa photo illustre le récapitulatif du chat et le voyage dans l'espace client.
 */
export function destinationDuLieu(
  lieu: string | null | undefined,
  destinations: DestinationPublique[],
) {
  const cherche = lieu ? sansAccents(lieu) : ''
  if (!cherche) return undefined
  return (
    destinations.find(
      (d) =>
        d.slug === cherche ||
        sansAccents(d.ville) === cherche ||
        sansAccents(d.nom).startsWith(cherche),
    ) ??
    destinations.find(
      (d) =>
        sansAccents(d.pays) === cherche ||
        (cherche.length >= 4 && sansAccents(d.nom).includes(cherche)),
    )
  )
}
