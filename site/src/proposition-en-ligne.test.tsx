import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { PropositionEnLigne } from './features/proposition/enLigne'
import { createQueryClient } from './lib/queryClient'
import { createAppRouter } from './router'
import { simulerApi } from './test/api'

const JETON = 'b'.repeat(64)

/** Proposition v1 envoyée par le conseiller, prix estimés (forme de PropositionClient::presenter). */
const PROPOSITION: PropositionEnLigne = {
  jeton: JETON,
  versionDemandee: null,
  version: 1,
  etat: 'estimee',
  statut: 'envoyee',
  mention: 'Première proposition, prix estimés, à confirmer par votre conseiller.',
  creeLe: '2026-10-16T15:00:00+02:00',
  confirmeeLe: null,
  niveauBudget: 'equilibre',
  budgetsParNiveau: {
    essentiel: { totalCentimes: 310000, parPersonneCentimes: 155000 },
    equilibre: { totalCentimes: 376200, parPersonneCentimes: 188100 },
    confort: { totalCentimes: 520000, parPersonneCentimes: 260000 },
  },
  totalCentimes: 376200,
  totalParPersonneCentimes: 188100,
  fraisDeDossierCentimes: 15000,
  destination: { slug: 'santorin', ville: 'Santorin' },
  pourquoi: 'Des villages blancs au-dessus de la mer, et des couchers de soleil inoubliables.',
  argumentaire: [{ exigence: 'Au moins 25 °C', reponse: '27 °C en moyenne en mai.' }],
  hypotheses: ['Vols depuis Paris au tarif moyen de mai.'],
  programme: [
    { jour: 1, date: '2027-05-10', titre: 'Arrivée à Oia', description: 'Transfert et dîner.' },
    { jour: 2, date: '2027-05-11', titre: 'Croisière', description: 'Tour de la caldeira.' },
  ],
  prestations: [
    {
      id: 7,
      type: 'logement',
      nom: 'Suite avec vue caldeira',
      pointsForts: ['Piscine privée'],
      niveaux: ['equilibre'],
    },
  ],
  lignes: [
    {
      type: 'logement',
      libelle: 'Suite avec vue caldeira',
      dateDebut: '2027-05-10',
      dateFin: '2027-05-17',
      quantite: 1,
    },
  ],
  demande: {
    statut: 'proposition_envoyee',
    typeDeVoyage: 'noces',
    lieuOuZone: 'Santorin',
    dateDebut: '2027-05-10',
    dateFin: '2027-05-17',
    adultes: 2,
    enfants: 0,
  },
  versions: [
    {
      version: 1,
      jeton: JETON,
      etat: 'estimee',
      statut: 'envoyee',
      creeLe: '2026-10-16T15:00:00+02:00',
      totalCentimes: 376200,
    },
  ],
  actions: { demanderConfirmation: true, accepter: false },
}

const JETON_V2 = 'd'.repeat(64)

/**
 * Version 2, après une modification du client (#211) : une semaine plus tard, un jour de plus, une croisière en
 * plus, 166 € de plus. Le lien de la version 1 y mène.
 */
const VERSION_2: PropositionEnLigne = {
  ...PROPOSITION,
  jeton: JETON_V2,
  versionDemandee: 1,
  version: 2,
  creeLe: '2026-10-18T10:00:00+02:00',
  budgetsParNiveau: {
    ...PROPOSITION.budgetsParNiveau,
    equilibre: { totalCentimes: 392800, parPersonneCentimes: 196400 },
  },
  totalCentimes: 392800,
  totalParPersonneCentimes: 196400,
  hypotheses: ['Vols depuis Paris au tarif moyen de mai.', 'Croisière pour 2 participants.'],
  programme: [
    { jour: 1, date: '2027-05-17', titre: 'Arrivée à Oia', description: 'Transfert et dîner.' },
    { jour: 2, date: '2027-05-18', titre: 'Croisière', description: 'Tour de la caldeira.' },
    { jour: 3, date: '2027-05-19', titre: 'Coucher de soleil', description: 'Croisière du soir.' },
  ],
  prestations: [
    ...PROPOSITION.prestations,
    {
      id: 9,
      type: 'activite',
      nom: 'Croisière au coucher du soleil',
      pointsForts: ['Dîner à bord'],
      niveaux: ['equilibre'],
    },
  ],
  lignes: [
    { ...PROPOSITION.lignes[0], dateDebut: '2027-05-17', dateFin: '2027-05-24' },
    {
      type: 'activite',
      libelle: 'Croisière au coucher du soleil',
      dateDebut: '2027-05-19',
      dateFin: '2027-05-19',
      quantite: 2,
    },
  ],
  demande: { ...PROPOSITION.demande, dateDebut: '2027-05-17', dateFin: '2027-05-24' },
  versions: [
    { ...PROPOSITION.versions[0], statut: 'remplacee' },
    {
      version: 2,
      jeton: JETON_V2,
      etat: 'estimee',
      statut: 'envoyee',
      creeLe: '2026-10-18T10:00:00+02:00',
      totalCentimes: 392800,
    },
  ],
}

const VERSION_1_REMPLACEE: PropositionEnLigne = {
  ...PROPOSITION,
  statut: 'remplacee',
  versions: VERSION_2.versions,
  actions: { demanderConfirmation: false, accepter: false },
}

function afficher(path: string) {
  const queryClient = createQueryClient()
  const router = createAppRouter(queryClient, createMemoryHistory({ initialEntries: [path] }))
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
  return router
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('« Ma proposition » par son lien unique (#208)', () => {
  it('affiche la proposition envoyée, avec la mention des prix estimés en tête', async () => {
    simulerApi({ [`GET /api/propositions/${JETON}`]: { status: 200, body: PROPOSITION } })
    afficher(`/proposition/${JETON}`)

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Santorin, 2 jours' }),
    ).toBeInTheDocument()
    // La mention est le premier texte de la page, avant l'en-tête.
    const mention = screen.getByText(/prix estimés, à confirmer par votre conseiller/)
    expect(
      mention.compareDocumentPosition(screen.getByRole('heading', { level: 1 })) &
        Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy()
    expect(screen.getByText('Arrivée à Oia')).toBeInTheDocument()
    expect(screen.getByText('Au moins 25 °C')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Suite avec vue caldeira' })).toBeInTheDocument()
    expect(screen.getAllByText('3 762 €').length).toBeGreaterThan(0)
    expect(document.title).toBe('Ma proposition · Santorin, 2 jours')
  })

  it('transmet la demande de confirmation au conseiller', async () => {
    const api = simulerApi({
      [`GET /api/propositions/${JETON}`]: { status: 200, body: PROPOSITION },
      [`POST /api/propositions/${JETON}/demande-de-confirmation`]: {
        status: 200,
        body: {
          ...PROPOSITION,
          demande: { ...PROPOSITION.demande, statut: 'a_confirmer' },
          actions: { demanderConfirmation: false, accepter: false },
        },
      },
    })
    afficher(`/proposition/${JETON}`)

    await userEvent
      .setup()
      .click(await screen.findByRole('button', { name: 'Demander la confirmation' }))

    expect(await screen.findByText(/demande de confirmation est transmise/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Demander la confirmation' })).toBeNull()
    expect(api.fetch).toHaveBeenCalledWith(
      `/api/propositions/${JETON}/demande-de-confirmation`,
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('affiche le refus de l’API', async () => {
    simulerApi({
      [`GET /api/propositions/${JETON}`]: { status: 200, body: PROPOSITION },
      [`POST /api/propositions/${JETON}/demande-de-confirmation`]: {
        status: 409,
        body: { erreur: 'Votre demande de confirmation est déjà transmise à votre conseiller.' },
      },
    })
    afficher(`/proposition/${JETON}`)

    await userEvent
      .setup()
      .click(await screen.findByRole('button', { name: 'Demander la confirmation' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('déjà transmise')
  })

  it('propose d’accepter une version confirmée', async () => {
    simulerApi({
      [`GET /api/propositions/${JETON}`]: {
        status: 200,
        body: {
          ...PROPOSITION,
          etat: 'confirmee',
          statut: 'confirmee',
          mention:
            'Proposition confirmée par votre conseiller : prix vérifiés auprès des prestataires.',
          actions: { demanderConfirmation: false, accepter: true },
        },
      },
    })
    afficher(`/proposition/${JETON}`)

    expect(await screen.findByText(/prix vérifiés auprès des prestataires/)).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Votre budget confirmé' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'J’accepte cette proposition' })).toBeInTheDocument()
  })

  it('signale un lien qui ne mène à aucune proposition', async () => {
    simulerApi({})
    afficher(`/proposition/${'c'.repeat(64)}`)

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Proposition introuvable' }),
    ).toBeInTheDocument()
  })

  it('est accessible depuis l’espace client', async () => {
    simulerApi({
      'GET /api/moi': {
        status: 200,
        body: {
          email: 'camille@example.com',
          prenom: 'Camille',
          nom: 'Durand',
          telephone: null,
          role: 'ROLE_CLIENT',
        },
      },
      'GET /api/moi/demandes': {
        status: 200,
        body: [
          {
            id: 228,
            statut: 'proposition_envoyee',
            typeDeVoyage: 'noces',
            lieuOuZone: 'Santorin',
            dateDebut: '2027-05-10',
            nombreDeJours: 7,
            adultes: 2,
            enfants: 0,
            creeLe: '2026-10-16T15:02:00+02:00',
            conseiller: null,
            transmiseLe: null,
            proposition: {
              jeton: JETON,
              version: 1,
              etat: 'estimee',
              statut: 'envoyee',
              niveauBudget: 'Équilibré',
              totalCentimes: 376200,
              totalParPersonneCentimes: 188100,
            },
            conversation: null,
          },
        ],
      },
      [`GET /api/propositions/${JETON}`]: { status: 200, body: PROPOSITION },
    })
    const router = afficher('/espace')

    const voyage = await screen.findByRole('region', { name: 'Santorin, 7 jours' })
    await userEvent.setup().click(within(voyage).getByRole('link', { name: 'Voir ma proposition' }))

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Santorin, 2 jours' }),
    ).toBeVisible()
    expect(router.state.location.pathname).toBe(`/proposition/${JETON}`)
  })
})

describe('Comparer deux versions (#212)', () => {
  function simulerDeuxVersions() {
    return simulerApi({
      [`GET /api/propositions/${JETON}`]: { status: 200, body: VERSION_2 },
      [`GET /api/propositions/${JETON}/versions/1`]: { status: 200, body: VERSION_1_REMPLACEE },
    })
  }

  it('compare d’emblée la version actuelle à la précédente et surligne les changements', async () => {
    simulerDeuxVersions()
    afficher(`/proposition/${JETON}`)

    const resume = await screen.findByRole('region', {
      name: 'Ce qui a changé depuis la version 1',
    })
    expect(screen.getByRole('combobox', { name: 'Comparer avec' })).toHaveValue('1')
    expect(resume).toHaveTextContent(/Dates : du 17 mai 2027 au 24 mai 2027/)
    expect(resume).toHaveTextContent(/avant : du 10 mai 2027 au 17 mai 2027/)
    expect(resume).toHaveTextContent(/Budget : \+166 € \(\+4,4 %\)/)
    expect(resume).toHaveTextContent(/soit \+83 € par personne/)

    // Le jour ajouté et les jours décalés, la croisière ajoutée, la nouvelle hypothèse.
    const jour3 = screen.getByText('Coucher de soleil').closest('li')!
    expect(within(jour3).getByText('Nouveau')).toBeInTheDocument()
    const jour1 = screen.getByText('Arrivée à Oia').closest('li')!
    expect(within(jour1).getByText('Modifié')).toBeInTheDocument()
    const croisiere = screen.getByRole('heading', { name: 'Croisière au coucher du soleil' })
    expect(within(croisiere.closest('article')!).getByText('Nouveau')).toBeInTheDocument()
    expect(
      within(screen.getByText('Croisière pour 2 participants.').closest('li')!).getByText(
        'Nouveau',
      ),
    ).toBeInTheDocument()
    // L'écart de budget, aussi à côté du total.
    expect(screen.getByText(/par rapport à la version 1/)).toHaveTextContent('+166 € (+4,4 %)')
  })

  it('n’affiche plus rien de surligné sans version à comparer', async () => {
    simulerDeuxVersions()
    afficher(`/proposition/${JETON}`)
    await screen.findByRole('region', { name: 'Ce qui a changé depuis la version 1' })

    await userEvent
      .setup()
      .selectOptions(screen.getByRole('combobox', { name: 'Comparer avec' }), '')

    expect(screen.queryByRole('region', { name: /Ce qui a changé/ })).toBeNull()
    expect(screen.queryByText('Nouveau')).toBeNull()
    expect(screen.queryByText('Modifié')).toBeNull()
  })

  it('affiche la version précédente telle qu’elle était, sans action possible', async () => {
    simulerDeuxVersions()
    afficher(`/proposition/${JETON}`)
    await screen.findByRole('region', { name: 'Ce qui a changé depuis la version 1' })

    const utilisateur = userEvent.setup()
    await utilisateur.selectOptions(screen.getByRole('combobox', { name: 'Version affichée' }), '1')

    expect(
      await screen.findByText(/Vous consultez la version 1, remplacée par la version 2/),
    ).toBeInTheDocument()
    expect(screen.queryByText('Coucher de soleil')).toBeNull()
    expect(screen.queryByRole('button', { name: 'Demander la confirmation' })).toBeNull()
    // Comparée à la version 2, la croisière apparaît retirée.
    await utilisateur.selectOptions(screen.getByRole('combobox', { name: 'Comparer avec' }), '2')
    const differences = await screen.findByRole('region', {
      name: 'Différences avec la version 2',
    })
    expect(differences).toHaveTextContent('−166 €')
    const croisiere = screen.getByRole('heading', { name: 'Croisière au coucher du soleil' })
    expect(within(croisiere.closest('article')!).getByText('Retiré')).toBeInTheDocument()

    await utilisateur.click(screen.getByRole('button', { name: 'Voir la version actuelle' }))
    expect(screen.getByText('Coucher de soleil')).toBeInTheDocument()
  })
})
