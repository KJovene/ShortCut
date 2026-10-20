import { ACTIVITES, DESTINATIONS, LOGEMENTS, prixAPartirDe } from '@/data/catalogue'
import type { DestinationApi } from '@/features/catalogue/catalogue'

const ANNEE = 2027
const deux = (n: number) => String(n).padStart(2, '0')
const finDuMois = (mois: number) => new Date(Date.UTC(ANNEE, mois + 1, 0)).getUTCDate()

/**
 * Destinations publiées servies par l'API simulée (GET /api/public/destinations), au format de l'API, construites
 * à partir du catalogue de démo du site (même source que les fixtures de l'API).
 */
export const DESTINATIONS_DE_DEMO: DestinationApi[] = DESTINATIONS.map((d) => ({
  slug: d.slug,
  nom: d.nom,
  ville: d.ville,
  pays: d.pays,
  region: d.region,
  description: d.description,
  accroche: d.accroche,
  pointsForts: d.pointsForts,
  tempsDeTrajet: d.tempsDeTrajet,
  photo: d.photo,
  photoAlt: d.alt,
  typesDeVoyage: d.types,
  moisIdeal: d.moisIdeal + 1,
  latitude: d.lat,
  longitude: d.lon,
  miseEnAvant: d.miseEnAvant,
  periodesClimatiques: d.temperatures.map((t, i) => ({
    debut: `${ANNEE}-${deux(i + 1)}-01`,
    fin: `${ANNEE}-${deux(i + 1)}-${finDuMois(i)}`,
    temperatureMoyenne: t,
    pluieMm: d.pluie[i],
  })),
  prixAPartirDeParPersonneEuros: prixAPartirDe(d),
  exemples: [
    ...LOGEMENTS.filter((l) => l.destination === d.slug).map((l) => ({
      type: 'logement' as const,
      unite: 'par_nuit' as const,
      ...l,
    })),
    ...ACTIVITES.filter((a) => a.destination === d.slug).map((a) => ({
      type: 'activite' as const,
      unite: 'par_personne' as const,
      ...a,
    })),
  ].map((p) => ({
    type: p.type,
    nom: p.nom,
    categorie: p.categorie,
    capacite: p.capacite,
    accessible: p.accessible ?? false,
    dureeMinutes: p.duree ?? null,
    envies: p.envies,
    photos: [p.photo],
    prixAPartirDeEuros: p.prix,
    unite: p.unite,
  })),
}))
