import { formatEuros, labelType } from '@/data/catalogue'
import { NIVEAUX, type Niveau, type PropositionEnLigne as Proposition, voyageurs } from './enLigne'

/**
 * Ce qui a changé entre deux versions d'une proposition (ticket #212) : les jours du programme, les prestations,
 * les postes inclus et les hypothèses ajoutés, modifiés ou retirés, la demande et l'écart de budget.
 */

export type Changement = 'ajoute' | 'modifie' | 'retire'

type Jour = Proposition['programme'][number]
type Prestation = Proposition['prestations'][number]
type Ligne = Proposition['lignes'][number]

/** Écart entre deux montants en centimes ; pourcentage à une décimale, null si l'ancien montant est nul. */
export interface Ecart {
  avant: number
  apres: number
  centimes: number
  pourcentage: number | null
}

export interface Comparaison {
  /** Version à laquelle la proposition affichée est comparée. */
  version: number
  total: Ecart
  parPersonne: Ecart
  niveaux: Partial<Record<Niveau, Ecart>>
  demande: { libelle: string; avant: string; apres: string }[]
  jours: Map<number, Changement>
  joursRetires: Jour[]
  prestations: Map<number, Changement>
  prestationsRetirees: Prestation[]
  lignes: Map<string, Changement>
  lignesRetirees: Ligne[]
  hypothesesAjoutees: Set<string>
  hypothesesRetirees: string[]
  /** Nombre total de changements, budget compris. */
  nombre: number
}

/** Ce qui a changé de `avant` à `apres`, du point de vue de `apres`. */
export function comparer(avant: Proposition, apres: Proposition): Comparaison {
  const niveaux: Comparaison['niveaux'] = {}
  for (const { valeur } of NIVEAUX) {
    const a = avant.budgetsParNiveau[valeur]
    const b = apres.budgetsParNiveau[valeur]
    if (a && b) niveaux[valeur] = ecart(a.totalCentimes, b.totalCentimes)
  }

  const jours = diff(avant.programme, apres.programme, (j) => j.jour, memeJour)
  const prestations = diff(avant.prestations, apres.prestations, (p) => p.id, memePrestation)
  const lignes = diff(avant.lignes, apres.lignes, cleLigne, memeLigne)
  const hypothesesAjoutees = new Set(apres.hypotheses.filter((h) => !avant.hypotheses.includes(h)))
  const hypothesesRetirees = avant.hypotheses.filter((h) => !apres.hypotheses.includes(h))
  const demande = changementsDeLaDemande(avant, apres)
  const total = ecart(avant.totalCentimes, apres.totalCentimes)

  return {
    version: avant.version,
    total,
    parPersonne: ecart(avant.totalParPersonneCentimes, apres.totalParPersonneCentimes),
    niveaux,
    demande,
    jours: jours.etats,
    joursRetires: jours.retires,
    prestations: prestations.etats,
    prestationsRetirees: prestations.retires,
    lignes: lignes.etats,
    lignesRetirees: lignes.retires,
    hypothesesAjoutees,
    hypothesesRetirees,
    nombre:
      demande.length +
      jours.etats.size +
      jours.retires.length +
      prestations.etats.size +
      prestations.retires.length +
      lignes.etats.size +
      lignes.retires.length +
      hypothesesAjoutees.size +
      hypothesesRetirees.length +
      (total.centimes !== 0 ? 1 : 0),
  }
}

export function ecart(avant: number, apres: number): Ecart {
  const centimes = apres - avant
  return {
    avant,
    apres,
    centimes,
    pourcentage: avant === 0 ? null : Math.round((centimes * 1000) / avant) / 10,
  }
}

/** « +166 € », « −40 € », « 0 € ». */
export function formatEcart(centimes: number) {
  const signe = centimes > 0 ? '+' : centimes < 0 ? '−' : ''
  return `${signe}${formatEuros(Math.abs(centimes) / 100)}`
}

/** « +1,8 % », « −12 % ». */
export function formatPourcentage(pourcentage: number) {
  const signe = pourcentage > 0 ? '+' : pourcentage < 0 ? '−' : ''
  return `${signe}${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(Math.abs(pourcentage))} %`
}

export function cleLigne(l: Ligne) {
  return `${l.type ?? ''}|${l.libelle}`
}

/**
 * États des éléments de `apres` (absent : inchangé) et éléments de `avant` qui n'y sont plus.
 */
function diff<T, K>(
  avant: T[],
  apres: T[],
  cle: (x: T) => K,
  pareil: (a: T, b: T) => boolean,
): { etats: Map<K, Changement>; retires: T[] } {
  const anciens = new Map(avant.map((x) => [cle(x), x]))
  const nouvelles = new Set(apres.map(cle))
  const etats = new Map<K, Changement>()
  for (const x of apres) {
    const ancien = anciens.get(cle(x))
    if (ancien === undefined) etats.set(cle(x), 'ajoute')
    else if (!pareil(ancien, x)) etats.set(cle(x), 'modifie')
  }
  return { etats, retires: avant.filter((x) => !nouvelles.has(cle(x))) }
}

const memeJour = (a: Jour, b: Jour) =>
  a.date === b.date && a.titre === b.titre && a.description === b.description

const memePrestation = (a: Prestation, b: Prestation) =>
  a.pointsForts.join('|') === b.pointsForts.join('|') &&
  [...a.niveaux].sort().join('|') === [...b.niveaux].sort().join('|')

const memeLigne = (a: Ligne, b: Ligne) =>
  a.quantite === b.quantite && a.dateDebut === b.dateDebut && a.dateFin === b.dateFin

const dateCourte = (iso: string) =>
  new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }).format(
    new Date(iso),
  )

function dates({ dateDebut, dateFin }: Proposition['demande']) {
  if (!dateDebut) return 'à préciser'
  return dateFin ? `du ${dateCourte(dateDebut)} au ${dateCourte(dateFin)}` : dateCourte(dateDebut)
}

const libelleNiveau = (niveau: Niveau) => NIVEAUX.find((n) => n.valeur === niveau)!.label

/** Les changements de la demande que le client reconnaît : dates, voyageurs, destination, type, niveau. */
function changementsDeLaDemande(avant: Proposition, apres: Proposition) {
  const champs: [string, string, string][] = [
    ['Dates', dates(avant.demande), dates(apres.demande)],
    ['Voyageurs', voyageurs(avant.demande), voyageurs(apres.demande)],
    [
      'Destination',
      avant.destination?.ville ?? avant.demande.lieuOuZone ?? '—',
      apres.destination?.ville ?? apres.demande.lieuOuZone ?? '—',
    ],
    [
      'Type de voyage',
      avant.demande.typeDeVoyage ? labelType(avant.demande.typeDeVoyage) : '—',
      apres.demande.typeDeVoyage ? labelType(apres.demande.typeDeVoyage) : '—',
    ],
    ['Niveau détaillé', libelleNiveau(avant.niveauBudget), libelleNiveau(apres.niveauBudget)],
  ]
  return champs
    .filter(([, a, b]) => a !== b)
    .map(([libelle, a, b]) => ({ libelle, avant: a, apres: b }))
}
