import { describe, expect, it } from 'vitest'
import { comparer, ecart, formatEcart, formatPourcentage } from './comparaison'
import type { PropositionEnLigne } from './enLigne'

const base: PropositionEnLigne = {
  jeton: 'a'.repeat(64),
  langue: 'fr',
  versionDemandee: null,
  version: 1,
  etat: 'estimee',
  statut: 'envoyee',
  mention: 'Première proposition, prix estimés, à confirmer par votre conseiller.',
  creeLe: '2026-10-16T15:00:00+02:00',
  confirmeeLe: null,
  niveauBudget: 'equilibre',
  budgetsParNiveau: { equilibre: { totalCentimes: 100000, parPersonneCentimes: 50000 } },
  totalCentimes: 100000,
  totalParPersonneCentimes: 50000,
  fraisDeDossierCentimes: 10000,
  destination: {
    slug: 'lisbonne',
    ville: 'Lisbonne',
    photo: null,
    photoAlt: null,
    temperatures: [],
  },
  pourquoi: null,
  argumentaire: [],
  hypotheses: ['Transport inclus', 'Frais de dossier séjour : 100 €'],
  programme: [
    { jour: 1, date: '2027-05-14', titre: 'Arrivée', description: 'Installation.' },
    { jour: 2, date: '2027-05-15', titre: 'Alfama', description: 'Balade.' },
  ],
  prestations: [
    {
      id: 1,
      type: 'logement',
      nom: 'Hôtel',
      pointsForts: ['Plain-pied'],
      niveaux: ['equilibre'],
      photo: null,
    },
    {
      id: 2,
      type: 'activite',
      nom: 'Atelier',
      pointsForts: ['Pastéis'],
      niveaux: ['equilibre'],
      photo: null,
    },
  ],
  options: [],
  lignes: [
    {
      type: 'logement',
      libelle: 'Hôtel',
      dateDebut: '2027-05-14',
      dateFin: '2027-05-16',
      quantite: 1,
    },
    {
      type: 'activite',
      libelle: 'Atelier',
      dateDebut: '2027-05-15',
      dateFin: '2027-05-15',
      quantite: 2,
    },
  ],
  demande: {
    statut: 'proposition_envoyee',
    typeDeVoyage: 'sejour',
    lieuOuZone: 'Lisbonne',
    dateDebut: '2027-05-14',
    dateFin: '2027-05-16',
    adultes: 2,
    enfants: 0,
  },
  versions: [],
  actions: { demanderConfirmation: true, accepter: false },
}

describe('comparer deux versions', () => {
  it('relève ce qui est ajouté, modifié et retiré', () => {
    const apres: PropositionEnLigne = {
      ...base,
      version: 2,
      totalCentimes: 90000,
      totalParPersonneCentimes: 30000,
      hypotheses: ['Transport inclus', 'Frais de dossier séjour : 120 €'],
      programme: [
        base.programme[0],
        { ...base.programme[1], description: 'Balade et tram 28.' },
        { jour: 3, date: '2027-05-16', titre: 'Départ', description: 'Retour.' },
      ],
      prestations: [base.prestations[0]],
      lignes: [{ ...base.lignes[0], quantite: 2 }],
      demande: { ...base.demande, adultes: 3, dateFin: '2027-05-17' },
    }

    const c = comparer(base, apres)

    expect(c.version).toBe(1)
    expect(c.total).toEqual({ avant: 100000, apres: 90000, centimes: -10000, pourcentage: -10 })
    expect([...c.jours]).toEqual([
      [2, 'modifie'],
      [3, 'ajoute'],
    ])
    expect(c.prestations.size).toBe(0)
    expect(c.prestationsRetirees.map((p) => p.nom)).toEqual(['Atelier'])
    expect([...c.lignes]).toEqual([['logement|Hôtel', 'modifie']])
    expect(c.lignesRetirees.map((l) => l.libelle)).toEqual(['Atelier'])
    expect([...c.hypothesesAjoutees]).toEqual(['Frais de dossier séjour : 120 €'])
    expect(c.hypothesesRetirees).toEqual(['Frais de dossier séjour : 100 €'])
    expect(c.demande.map((d) => d.libelle)).toEqual(['Dates', 'Voyageurs'])
    expect(c.demande[1]).toEqual({ libelle: 'Voyageurs', avant: '2 adultes', apres: '3 adultes' })
    expect(c.nombre).toBe(10)
  })

  it('ne relève rien entre deux versions identiques', () => {
    expect(comparer(base, { ...base, version: 2 }).nombre).toBe(0)
  })

  it('écrit les écarts avec leur signe', () => {
    expect(ecart(376200, 392800).pourcentage).toBe(4.4)
    expect(ecart(0, 5000).pourcentage).toBeNull()
    expect(formatEcart(16600)).toBe('+166 €')
    expect(formatEcart(-4000)).toBe('−40 €')
    expect(formatPourcentage(4.4)).toBe('+4,4 %')
    expect(formatPourcentage(-12)).toBe('−12 %')
  })
})
