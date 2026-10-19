import { describe, expect, it } from 'vitest'
import { type EvenementChat, type Fiche, ficheVersDemande, lireEvenements } from './api'
import { demandeVide } from './demande'

/** Flux découpé en morceaux de 7 octets : les coupures tombent au milieu des événements et des accents. */
function flux(texte: string) {
  const octets = new TextEncoder().encode(texte)
  return new ReadableStream<Uint8Array>({
    start(controleur) {
      for (let i = 0; i < octets.length; i += 7) controleur.enqueue(octets.slice(i, i + 7))
      controleur.close()
    },
  })
}

async function tout(source: AsyncGenerator<EvenementChat>) {
  const evenements: EvenementChat[] = []
  for await (const e of source) evenements.push(e)
  return evenements
}

const ficheVide: Fiche = {
  typeDeVoyage: null,
  lieuOuZone: null,
  villeDepart: null,
  dateDebut: null,
  dateFin: null,
  datesFlexibles: false,
  nombreDeJours: null,
  temperatureMin: null,
  temperatureMax: null,
  adultes: 1,
  enfants: 0,
  agesEnfants: [],
  budgetEuros: null,
  budgetParPersonne: false,
  transportInclus: true,
  envies: [],
  contraintes: [],
}

describe('flux du chatbot', () => {
  it('reconstitue les événements, même coupés en plein caractère', async () => {
    const evenements = await tout(
      lireEvenements(
        flux(
          'event: texte\ndata: {"texte":"Très "}\n\n' +
            'event: texte\ndata: {"texte":"bien, à quelles dates ?"}\n\n' +
            'event: outil\ndata: {"nom":"enregistrer_exigences"}\n\n' +
            'event: inconnu\ndata: {}\n\n' +
            'event: fin\ndata: {}\n\n',
        ),
      ),
    )

    expect(evenements).toEqual([
      { type: 'texte', texte: 'Très ' },
      { type: 'texte', texte: 'bien, à quelles dates ?' },
      { type: 'outil', nom: 'enregistrer_exigences' },
      { type: 'fin' },
    ])
  })
})

describe('fiche de l’API dans le récapitulatif', () => {
  it('reconnaît une destination du catalogue et convertit les dates', () => {
    const d = ficheVersDemande(
      {
        ...ficheVide,
        typeDeVoyage: 'noces',
        lieuOuZone: 'Santorin',
        dateDebut: '2027-06-12',
        nombreDeJours: 7,
        adultes: 2,
        budgetEuros: 5000,
        contraintes: ['Allergie sévère aux fruits de mer'],
      },
      demandeVide(),
    )

    expect(d).toMatchObject({
      type: 'noces',
      destination: 'santorin',
      zone: undefined,
      mois: 5,
      jours: 7,
      adultes: 2,
      budget: 5000,
      contraintesSignalees: ['Allergie sévère aux fruits de mer'],
    })
  })

  it('garde une zone libre telle quelle, et « aucune » comme réponse', () => {
    const d = ficheVersDemande(
      { ...ficheVide, lieuOuZone: 'la Méditerranée', contraintes: ['aucune'] },
      demandeVide(),
    )

    expect(d.destination).toBeUndefined()
    expect(d.zone).toBe('la Méditerranée')
    expect(d.contraintes).toEqual(['aucune'])
    expect(d.contraintesSignalees).toEqual([])
  })
})
