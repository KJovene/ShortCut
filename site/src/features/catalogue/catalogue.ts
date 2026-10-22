import { queryOptions } from '@tanstack/react-query'
import { z } from 'zod'
import { TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'
import { ApiError } from '@/lib/api'
import { type Langue, langueDuSite, typeDeVoyage } from '@/lib/langue'

/**
 * Catalogue du site public, lu dans l'API (GET /api/public/destinations) : seules les destinations publiées dans
 * le backoffice y figurent, avec des prix de vente « à partir de » (marge de l'agence comprise).
 */

const TYPES = TYPES_DE_VOYAGE.map((t) => t.valeur) as readonly string[]

const ExempleApi = z.object({
  type: z.enum(['logement', 'activite']),
  nom: z.string(),
  categorie: z.string().nullable(),
  capacite: z.number(),
  accessible: z.boolean(),
  dureeMinutes: z.number().nullable(),
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
  region: z.string().nullable(),
  description: z.string().nullable(),
  accroche: z.string().nullable(),
  pointsForts: z.array(z.string()),
  tempsDeTrajet: z.record(z.string(), z.number()),
  photo: z.string().nullable(),
  photoAlt: z.string().nullable(),
  typesDeVoyage: z.array(z.string()),
  moisIdeal: z.number().int().min(1).max(12).nullable(),
  latitude: z.number().nullable(),
  longitude: z.number().nullable(),
  miseEnAvant: z.boolean(),
  periodesClimatiques: z.array(
    z.object({
      debut: z.string(),
      fin: z.string(),
      temperatureMoyenne: z.number(),
      pluieMm: z.number().nullable(),
    }),
  ),
  prixAPartirDeParPersonneEuros: z.number().nullable(),
  exemples: z.array(ExempleApi),
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

/** En anglais, l'API traduit les textes saisis en français dans le backoffice (nom, description, points forts…). */
async function lire(chemin: string, langue: Langue) {
  const response = await fetch(`/api/public/${chemin}${langue === 'en' ? '?langue=en' : ''}`, {
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) {
    throw new ApiError(response.status, 'Impossible de charger les destinations.')
  }
  return (await response.json()) as unknown
}

export async function chargerDestinations(langue: Langue = 'fr'): Promise<DestinationPublique[]> {
  return z
    .array(DestinationApi)
    .parse(await lire('destinations', langue))
    .map(versDestination)
}

export async function chargerDestination(
  slug: string,
  langue: Langue = 'fr',
): Promise<DestinationPublique> {
  return versDestination(DestinationApi.parse(await lire(`destinations/${slug}`, langue)))
}

export const destinationsQuery = (langue: Langue = langueDuSite()) =>
  queryOptions({
    queryKey: ['catalogue', 'destinations', langue],
    queryFn: () => chargerDestinations(langue),
    staleTime: 5 * 60_000,
  })

export const destinationQuery = (slug: string, langue: Langue = langueDuSite()) =>
  queryOptions({
    queryKey: ['catalogue', 'destination', slug, langue],
    queryFn: () => chargerDestination(slug, langue),
    staleTime: 5 * 60_000,
    // Une destination masquée ou inconnue ne reviendra pas en redemandant : 404 tout de suite.
    retry: (essais, erreur) => !(erreur instanceof ApiError && erreur.status === 404) && essais < 1,
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
