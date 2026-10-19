import {
  activitesDe,
  formatDuree,
  labelType,
  logementsDe,
  MARGE,
  MOIS,
  TYPES_DE_VOYAGE,
  trouverDestination,
  type Destination,
  type Prestation,
  type TypeDeVoyage,
} from '@/data/catalogue'
import type { Demande } from '@/features/chat/demande'

export const NIVEAUX = [
  { valeur: 'essentiel', label: 'Essentiel' },
  { valeur: 'equilibre', label: 'Équilibré' },
  { valeur: 'confort', label: 'Confort' },
] as const

export type Niveau = (typeof NIVEAUX)[number]['valeur']

export interface LigneBudget {
  label: string
  montant: number
  estime?: boolean
}

export interface Budget {
  logement: Prestation
  chambres: number
  activites: Prestation[]
  lignes: LigneBudget[]
  total: number
  parPersonne: number
}

export interface Jour {
  numero: number
  titre: string
  detail: string
}

export interface Proposition {
  id: string
  version: number
  creeLe: string
  destination: string
  type: TypeDeVoyage
  titre: string
  resume: string
  jours: Jour[]
  nuits: number
  voyageurs: number
  mois: number
  depart: string
  budgetClient?: number
  budgets: Record<Niveau, Budget>
  hypotheses: string[]
  contraintesSignalees: string[]
}

const milieu = ([min, max]: [number, number]) => Math.round((min + max) / 2)
const arrondi = (n: number) => Math.round(n)

function choisirLogement(logements: Prestation[], voyageurs: number, niveau: Niveau) {
  // Les logements trop grands (riads entiers, blocs groupe) ne servent que si le groupe les remplit.
  const adaptes = logements
    .filter((l) => l.capacite <= Math.max(voyageurs * 2, 4) || l.capacite <= voyageurs)
    .sort((a, b) => a.prix - b.prix)
  const liste = adaptes.length ? adaptes : [...logements].sort((a, b) => a.prix - b.prix)
  if (niveau === 'essentiel') return liste[0]
  if (niveau === 'confort') return liste[liste.length - 1]
  return liste[Math.floor((liste.length - 1) / 2)] ?? liste[0]
}

function calculerBudget(
  destination: Destination,
  type: TypeDeVoyage,
  niveau: Niveau,
  nuits: number,
  voyageurs: number,
): Budget {
  const logement = choisirLogement(logementsDe(destination.slug), voyageurs, niveau)
  const chambres = Math.ceil(voyageurs / logement.capacite)
  const toutes = activitesDe(destination.slug)
  const nbActivites = niveau === 'essentiel' ? 1 : niveau === 'equilibre' ? 2 : toutes.length
  const activites = toutes.slice(0, Math.min(nbActivites, Math.max(nuits - 1, 1)))

  const vol =
    niveau === 'essentiel'
      ? destination.volParis[0]
      : niveau === 'equilibre'
        ? milieu(destination.volParis)
        : destination.volParis[1]
  const transfert =
    niveau === 'essentiel'
      ? destination.transfert[0]
      : niveau === 'equilibre'
        ? milieu(destination.transfert)
        : destination.transfert[1]

  // Chaque poste est affiché marge comprise (20 %), les frais de dossier s'ajoutent une fois par dossier.
  const avecMarge = (cout: number) => arrondi(cout * (1 + MARGE))
  const frais = TYPES_DE_VOYAGE.find((t) => t.valeur === type)!.fraisDossier
  const lignes: LigneBudget[] = [
    {
      label: `Vols A/R depuis Paris ×${voyageurs}`,
      montant: avecMarge(vol * voyageurs),
      estime: true,
    },
    { label: `Hébergement, ${nuits} nuits`, montant: avecMarge(logement.prix * nuits * chambres) },
    {
      label: `Activités, ${activites.length} ${activites.length > 1 ? 'sorties' : 'sortie'}`,
      montant: avecMarge(activites.reduce((s, a) => s + a.prix, 0) * voyageurs),
    },
    { label: 'Navettes et transferts', montant: avecMarge(transfert * voyageurs) },
    { label: 'Frais de dossier', montant: frais },
  ]
  const total = lignes.reduce((s, l) => s + l.montant, 0)

  return { logement, chambres, activites, lignes, total, parPersonne: arrondi(total / voyageurs) }
}

function programme(
  destination: Destination,
  jours: number,
  activites: Prestation[],
  depart: string,
): Jour[] {
  const programme: Jour[] = [
    {
      numero: 1,
      titre: `Arrivée à ${destination.ville}`,
      detail: `Vol ${depart} – ${destination.ville}, navette vers votre hébergement et première soirée sur place.`,
    },
  ]
  const libres = destination.pointsForts
  for (let n = 2; n < jours; n++) {
    const activite = activites[n - 2]
    programme.push(
      activite
        ? {
            numero: n,
            titre: activite.nom,
            detail: `${activite.categorie} · ${formatDuree(activite.duree ?? 120)}, le reste de la journée libre.`,
          }
        : {
            numero: n,
            titre: n === jours - 1 ? 'Journée libre' : libres[(n - 2) % libres.length],
            detail:
              n === jours - 1
                ? 'Dernière journée à votre rythme : plage, balade ou shopping.'
                : 'Journée à votre rythme, idées et adresses fournies par votre conseiller.',
          },
    )
  }
  if (jours > 1) {
    programme.push({
      numero: jours,
      titre: 'Retour',
      detail: `Navette vers l’aéroport, vol ${destination.ville} – ${depart}.`,
    })
  }
  return programme
}

let compteur = 0

/** Construit la première proposition (agents Recherche puis Chiffrage) à partir de la fiche demande. */
export function genererProposition(demande: Demande, slug: string): Proposition {
  const destination = trouverDestination(slug) ?? trouverDestination('santorin')!
  const type = demande.type ?? destination.types[0]
  const jours = Math.min(Math.max(demande.jours ?? 7, 2), 21)
  const nuits = jours - 1
  const voyageurs = Math.max((demande.adultes ?? 2) + (demande.enfants ?? 0), 1)
  const mois = demande.mois ?? destination.moisIdeal
  const depart = demande.depart ?? 'Paris'

  const budgets = Object.fromEntries(
    NIVEAUX.map((n) => [n.valeur, calculerBudget(destination, type, n.valeur, nuits, voyageurs)]),
  ) as Record<Niveau, Budget>

  const budgetClient =
    demande.budget !== undefined
      ? demande.budgetParPersonne
        ? demande.budget * voyageurs
        : demande.budget
      : undefined

  const contraintes = demande.contraintes.length
    ? demande.contraintes.join(', ')
    : 'aucune contrainte signalée'
  compteur += 1

  return {
    id: `${Date.now().toString(36)}${compteur}`,
    version: 1,
    creeLe: new Date().toISOString(),
    destination: destination.slug,
    type,
    titre: `${destination.ville}, ${destination.pays}`,
    resume: `${jours} jours en ${MOIS[mois]} pour ${voyageurs} voyageur${voyageurs > 1 ? 's' : ''}, ${labelType(type).toLowerCase()} · ${contraintes}.`,
    jours: programme(destination, jours, budgets.equilibre.activites, depart),
    nuits,
    voyageurs,
    mois,
    depart,
    budgetClient,
    budgets,
    hypotheses: [
      `Saison tarifaire de ${MOIS[mois]}`,
      `Hébergement : ${budgets.equilibre.logement.categorie.toLowerCase()} pour le niveau équilibré`,
      `${budgets.equilibre.chambres} chambre${budgets.equilibre.chambres > 1 ? 's' : ''} ou logement${budgets.equilibre.chambres > 1 ? 's' : ''}`,
      'Vols inclus, prix issu de la fourchette enregistrée par l’agence',
      'Navettes et transferts inclus, pas de location de voiture',
      'Marge agence (20 %) et frais de dossier inclus',
    ],
    contraintesSignalees: demande.contraintesSignalees,
  }
}
