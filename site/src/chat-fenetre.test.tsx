import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createQueryClient } from './lib/queryClient'
import { createAppRouter } from './router'
import { simulerApi } from './test/api'

function afficher(path: string) {
  const queryClient = createQueryClient()
  const router = createAppRouter(queryClient, createMemoryHistory({ initialEntries: [path] }))
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
}

afterEach(() => {
  vi.unstubAllGlobals()
})

const CLIENT = {
  email: 'client@example.com',
  prenom: 'Camille',
  nom: 'Client',
  telephone: null,
  role: 'ROLE_CLIENT',
}

/** Un flux Server-Sent Events de POST /api/chat. */
const flux = (...evenements: [string, unknown][]) =>
  evenements
    .map(([type, donnees]) => `event: ${type}\ndata: ${JSON.stringify(donnees)}\n\n`)
    .join('')

const PISTE = {
  type: 'piste',
  destination: { slug: 'santorin', ville: 'Santorin', pays: 'Grèce' },
  dateDebut: '2027-06-12',
  dateFin: '2027-06-19',
  nuits: 7,
  voyageurs: 2,
  temperatureMoyenne: 25,
  villeDepart: 'Paris',
  tempsDeTrajetMinutes: 230,
  envies: ['romantique'],
  niveauConseille: 'equilibre',
  niveaux: {
    essentiel: {
      logements: ['Hôtel Fira'],
      activites: [],
      transport: 'minimum de la fourchette',
      totalEuros: 3200,
      parPersonneEuros: 1600,
    },
    equilibre: {
      logements: ['Suite Caldera Oia'],
      activites: ['Croisière au coucher du soleil'],
      transport: 'milieu de la fourchette',
      totalEuros: 4600,
      parPersonneEuros: 2300,
    },
    confort: {
      logements: ['Villa Imerovigli'],
      activites: ['Croisière au coucher du soleil'],
      transport: 'maximum de la fourchette',
      totalEuros: 6100,
      parPersonneEuros: 3050,
    },
  },
  options: [
    { id: 7, nom: 'Dégustation de vins volcaniques', prixEuros: 180, parPersonneEuros: 90 },
  ],
  budgetInsuffisant: false,
  arbitrages: [],
  avertissement: 'Prix estimés le jeudi 22 octobre 2026 à 9 h 40 d’après nos tarifs.',
}

const recapitulatif = (texte: string, options: unknown[]) => ({
  type: 'recapitulatif',
  texte,
  jeton: 'a'.repeat(64),
  version: 1,
  ville: 'Santorin',
  jours: 8,
  niveau: 'equilibre',
  totalCentimes: 460000,
  parPersonneCentimes: 230000,
  voyageurs: 2,
  options,
})

describe('fenêtre du chatbot, hors de l’accueil', () => {
  it('garde le focus dans le champ pendant la saisie, au premier message comme ensuite', async () => {
    sessionStorage.clear()
    simulerApi({
      'GET /api/moi': {
        status: 200,
        body: {
          email: 'client@example.com',
          prenom: 'Camille',
          nom: 'Client',
          telephone: null,
          role: 'ROLE_CLIENT',
        },
      },
    })
    afficher('/types-de-voyages')
    const user = userEvent.setup()

    await user.click((await screen.findAllByRole('button', { name: /Construire mon voyage/ }))[0])
    const fenetre = await screen.findByRole('dialog', { name: 'Assistant voyage' })

    // Premier message : le champ est pré-rempli par le type de voyage choisi.
    const premier = within(fenetre).getByRole('textbox')
    await user.type(premier, '10 jours en mai')
    expect(premier).toHaveFocus()
    expect(premier).toHaveValue('Un séjour pour deux, 10 jours en mai')
    await user.keyboard('{Enter}')
    expect(
      await within(fenetre).findByText('Un séjour pour deux, 10 jours en mai'),
    ).toBeInTheDocument()

    // Suite de la conversation, dans le champ « Votre message ».
    const suite = within(fenetre).getByRole('textbox', { name: 'Votre message' })
    await user.type(suite, 'Budget 4 000 €')
    expect(suite).toHaveFocus()
    expect(suite).toHaveValue('Budget 4 000 €')
  })

  it('repart d’une discussion vierge quand le client construit un autre voyage', async () => {
    sessionStorage.clear()
    simulerApi({
      'GET /api/moi': {
        status: 200,
        body: {
          email: 'client@example.com',
          prenom: 'Camille',
          nom: 'Client',
          telephone: null,
          role: 'ROLE_CLIENT',
        },
      },
    })
    afficher('/types-de-voyages')
    const user = userEvent.setup()

    await user.click((await screen.findAllByRole('button', { name: /Construire mon voyage/ }))[0])
    const fenetre = await screen.findByRole('dialog', { name: 'Assistant voyage' })
    await user.type(within(fenetre).getByRole('textbox'), '10 jours en mai{Enter}')
    expect(
      await within(fenetre).findByText('Un séjour pour deux, 10 jours en mai'),
    ).toBeInTheDocument()
    await user.click(within(fenetre).getByRole('button', { name: /Fermer/ }))

    await user.click(screen.getAllByRole('button', { name: /Construire mon voyage/ })[1])
    const nouvelle = await screen.findByRole('dialog', { name: 'Assistant voyage' })
    expect(
      within(nouvelle).queryByText('Un séjour pour deux, 10 jours en mai'),
    ).not.toBeInTheDocument()
    expect(within(nouvelle).getByRole('textbox')).toHaveValue('Un circuit en plusieurs étapes, ')
  })
})

describe('proposition dans le chat', () => {
  it('montre la piste en carte, puis le récapitulatif et ses options, et ferme la saisie une fois répondu', async () => {
    sessionStorage.clear()
    const api = simulerApi({
      'GET /api/moi': { status: 200, body: CLIENT },
      'POST /api/chat/conversations': { status: 201, body: { jeton: 'j'.repeat(64), demande: 1 } },
      'POST /api/chat': [
        {
          status: 200,
          body: flux(
            ['texte', { texte: 'Merci Camille, je prépare votre proposition.' }],
            ['texte', { texte: '\n\nSantorin à deux, en juin.' }],
            ['piste', PISTE],
            ['outil', { nom: 'enregistrer_proposition' }],
            [
              'recapitulatif',
              recapitulatif('Votre récapitulatif est prêt. Une option vous tente-t-elle ?', [
                { id: 7, nom: 'Dégustation de vins volcaniques', prixCentimes: 18000 },
              ]),
            ],
            ['fin', { terminee: false }],
          ),
        },
        {
          status: 200,
          body: flux(
            ['texte', { texte: 'Votre voyage reste tel quel.' }],
            ['recapitulatif', recapitulatif('Votre récapitulatif est prêt.', [])],
            ['fin', { terminee: true }],
          ),
        },
      ],
    })
    afficher('/types-de-voyages')
    const user = userEvent.setup()

    await user.click((await screen.findAllByRole('button', { name: /Construire mon voyage/ }))[0])
    const fenetre = await screen.findByRole('dialog', { name: 'Assistant voyage' })
    await user.type(within(fenetre).getByRole('textbox'), ', c’est tout bon{Enter}')

    // La piste : les trois niveaux et leur total, le niveau conseillé, l'option et son prix.
    const piste = await within(fenetre).findByRole('article', { name: 'Votre piste : Santorin' })
    expect(within(piste).getByText('Conseillé')).toBeInTheDocument()
    expect(within(piste).getByText('4 600 €')).toBeInTheDocument()
    expect(within(piste).getByText('Dégustation de vins volcaniques · +180 €')).toBeInTheDocument()

    // Le récapitulatif : son lien, et les options à ajouter d'un clic tant que le client n'a pas répondu.
    const recap = await within(fenetre).findByRole('article', { name: 'Votre récapitulatif' })
    expect(within(recap).getByRole('link', { name: 'Voir mon récapitulatif' })).toHaveAttribute(
      'href',
      `/proposition/${'a'.repeat(64)}`,
    )
    expect(within(fenetre).getByRole('textbox', { name: 'Votre message' })).toBeInTheDocument()
    await user.click(within(recap).getByRole('button', { name: 'Garder le voyage tel quel' }))

    await waitFor(() =>
      expect(api.envoisA('POST /api/chat').at(-1)).toEqual({
        message: 'Je garde le voyage tel quel, sans option.',
      }),
    )
    expect(await within(fenetre).findByText('Transmis à votre conseiller')).toBeInTheDocument()
    expect(
      within(fenetre).queryByRole('textbox', { name: 'Votre message' }),
    ).not.toBeInTheDocument()
  })
})
