import { z } from 'zod'
import {
  ACTIVITES,
  DESTINATIONS,
  LOGEMENTS,
  type Destination,
  type Prestation,
} from './catalogue'
import { apiFetch } from '@/lib/api'

const catalogueSchema = z.object({
  destinations: z.array(
    z.object({
      slug: z.string(),
      ville: z.string(),
      pays: z.string(),
      region: z.string().nullable(),
      description: z.string().nullable(),
      pointsForts: z.array(z.string()),
      tempsDeTrajet: z.record(z.string(), z.number()),
      photo: z.string().nullable(),
      miseEnAvant: z.boolean(),
      climat: z.array(
        z.object({
          debut: z.string(),
          fin: z.string(),
          temperatureMoyenne: z.number(),
          pluieMm: z.number().nullable(),
        }),
      ),
      prestations: z.array(
        z.object({
          id: z.number().nullable(),
          nom: z.string().nullable(),
          destination: z.string().nullable(),
          categorie: z.string().nullable(),
          capacite: z.number(),
          accessible: z.boolean(),
          duree: z.number().nullable(),
          envies: z.array(z.string()),
          photo: z.string().nullable(),
          tarifs: z.array(
            z.object({
              debut: z.string(),
              fin: z.string(),
              prixCentimes: z.number(),
              unite: z.string(),
              sejourMinimumNuits: z.number().nullable(),
            }),
          ),
        }),
      ),
    }),
  ),
})

function imageUrl(folder: 'destinations' | 'prestations', name: string | null, fallback: string) {
  return name ? `/uploads/${folder}/${name}` : fallback
}

function climatParMois(
  climat: z.infer<typeof catalogueSchema>['destinations'][number]['climat'],
  fallback: number[],
  pluieFallback: number[],
) {
  return Array.from({ length: 12 }, (_, mois) => {
    const periode = climat.find((p) => {
      const debut = Number(p.debut.slice(5, 7)) - 1
      const fin = Number(p.fin.slice(5, 7)) - 1
      return debut <= mois && mois <= fin
    })

    return {
      temperature: periode?.temperatureMoyenne ?? fallback[mois] ?? 0,
      pluie: periode?.pluieMm ?? pluieFallback[mois] ?? 0,
    }
  })
}

function mapperPrestation(
  raw: z.infer<typeof catalogueSchema>['destinations'][number]['prestations'][number],
  fallback: Prestation | undefined,
): Prestation {
  const tarif = raw.tarifs[0]
  return {
    nom: raw.nom ?? fallback?.nom ?? 'Prestation',
    destination: raw.destination ?? fallback?.destination ?? '',
    categorie: raw.categorie ?? fallback?.categorie ?? '',
    capacite: raw.capacite,
    prix: tarif ? tarif.prixCentimes / 100 : (fallback?.prix ?? 0),
    photo: imageUrl('prestations', raw.photo, fallback?.photo ?? ''),
    envies: raw.envies,
    accessible: raw.accessible,
    duree: raw.duree ?? undefined,
  }
}

function mapperDestination(
  raw: z.infer<typeof catalogueSchema>['destinations'][number],
): CatalogueDestination {
  const fallback = DESTINATIONS.find((d) => d.slug === raw.slug)
  const climat = climatParMois(raw.climat, fallback?.temperatures ?? [], fallback?.pluie ?? [])
  const temperatures = climat.map((p) => p.temperature)
  const pluie = climat.map((p) => p.pluie)
  const logements = raw.prestations.filter((p) => p.tarifs.some((t) => t.unite === 'par_nuit'))
  const activites = raw.prestations.filter((p) => !p.tarifs.some((t) => t.unite === 'par_nuit'))
  const moisIdeal = temperatures.indexOf(Math.max(...temperatures))

  return {
    ...(fallback ?? {
      types: ['sejour'],
      accroche: '',
      lon: 0,
      lat: 0,
      volParis: [0, 0],
      transfert: [0, 0],
      alt: raw.ville,
    }),
    slug: raw.slug,
    ville: raw.ville,
    pays: raw.pays,
    region: raw.region ?? fallback?.region ?? '',
    nom: `${raw.ville}, ${raw.pays}`,
    description: raw.description ?? fallback?.description ?? '',
    pointsForts: raw.pointsForts,
    tempsDeTrajet: raw.tempsDeTrajet,
    temperatures,
    pluie,
    moisIdeal: moisIdeal >= 0 ? moisIdeal : (fallback?.moisIdeal ?? 0),
    photo: imageUrl('destinations', raw.photo, fallback?.photo ?? ''),
    miseEnAvant: raw.miseEnAvant,
    _logements: logements.map((p) =>
      mapperPrestation(p, LOGEMENTS.find((f) => f.nom === p.nom)),
    ),
    _activites: activites.map((p) =>
      mapperPrestation(p, ACTIVITES.find((f) => f.nom === p.nom)),
    ),
  }
}

export type CatalogueDestination = Destination & {
  _logements: Prestation[]
  _activites: Prestation[]
}

export async function chargerCatalogue(): Promise<CatalogueDestination[]> {
  try {
    const data = await apiFetch('/catalogue/destinations', catalogueSchema)
    return data.destinations.map(mapperDestination)
  } catch {
    return DESTINATIONS.map((destination) => ({
      ...destination,
      _logements: LOGEMENTS.filter((p) => p.destination === destination.slug),
      _activites: ACTIVITES.filter((p) => p.destination === destination.slug),
    })) as CatalogueDestination[]
  }
}
