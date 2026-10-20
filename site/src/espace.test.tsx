import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { DemandeClient } from '@/features/espace/espace'
import type { Profil } from '@/lib/auth'
import { createQueryClient } from '@/lib/queryClient'
import { createAppRouter } from '@/router'
import { simulerApi } from '@/test/api'

const CAMILLE: Profil = {
  email: 'camille@example.com',
  prenom: 'Camille',
  nom: 'Durand',
  telephone: null,
  role: 'ROLE_CLIENT',
}

const SANTORIN: DemandeClient = {
  id: 228,
  statut: 'a_confirmer',
  typeDeVoyage: 'noces',
  lieuOuZone: 'Santorin',
  dateDebut: '2027-05-10',
  nombreDeJours: 10,
  adultes: 2,
  enfants: 0,
  creeLe: '2026-10-16T15:02:00+02:00',
  conseiller: { prenom: 'Claire', nom: 'Martin' },
  transmiseLe: '2026-10-16T15:10:00+02:00',
  proposition: {
    jeton: 'a'.repeat(64),
    version: 1,
    etat: 'estimee',
    statut: 'envoyee',
    niveauBudget: 'Équilibré',
    totalCentimes: 376200,
    totalParPersonneCentimes: 188100,
  },
  conversation: {
    debutLe: '2026-10-16T15:00:00+02:00',
    messages: [
      { role: 'client', contenu: 'Du soleil en mai, à deux.', le: '2026-10-16T15:00:00+02:00' },
      {
        role: 'assistant',
        contenu: 'Votre proposition est prête.',
        le: '2026-10-16T15:02:00+02:00',
      },
    ],
  },
}

const ISLANDE: DemandeClient = {
  ...SANTORIN,
  id: 201,
  statut: 'perdue',
  typeDeVoyage: 'circuit',
  lieuOuZone: 'Islande',
  conseiller: null,
  transmiseLe: null,
  proposition: null,
  conversation: {
    debutLe: '2026-09-14T10:00:00+02:00',
    messages: [
      { role: 'client', contenu: 'Voir les aurores boréales.', le: '2026-09-14T10:00:00+02:00' },
    ],
  },
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

describe('mon espace', () => {
  it('renvoie vers la connexion sans session', async () => {
    simulerApi({})
    const router = afficher('/espace')

    expect(await screen.findByRole('heading', { name: 'Connexion' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/connexion')
  })

  it('suit le voyage en cours étape par étape', async () => {
    simulerApi({
      'GET /api/moi': { status: 200, body: CAMILLE },
      'GET /api/moi/demandes': { status: 200, body: [SANTORIN, ISLANDE] },
    })
    afficher('/espace')

    const voyage = await screen.findByRole('region', { name: 'Santorin, 10 jours' })
    expect(within(voyage).getByText('Dossier D-0228 · 2 adultes · mai 2027')).toBeInTheDocument()
    expect(within(voyage).getByText('Pris en charge par Claire')).toBeInTheDocument()
    expect(within(voyage).getByText('3 762 €')).toBeInTheDocument()
    expect(within(voyage).getByText(/prix estimé/)).toBeInTheDocument()
    expect(within(voyage).getByText('Claire Martin')).toBeInTheDocument()

    const enCours = within(voyage)
      .getAllByRole('listitem')
      .find((etape) => etape.getAttribute('aria-current') === 'step')
    expect(enCours).toHaveTextContent('Prise en charge par Claire')
    expect(enCours).toHaveTextContent('Depuis le 16 oct.')
    expect(within(voyage).getAllByRole('listitem')).toHaveLength(4)
  })

  it('avance à la finalisation une fois la version confirmée', async () => {
    simulerApi({
      'GET /api/moi': { status: 200, body: CAMILLE },
      'GET /api/moi/demandes': {
        status: 200,
        body: [
          {
            ...SANTORIN,
            statut: 'confirmee',
            proposition: { ...SANTORIN.proposition!, version: 2, etat: 'confirmee' },
          },
        ],
      },
    })
    afficher('/espace')

    const voyage = await screen.findByRole('region', { name: 'Santorin, 10 jours' })
    expect(within(voyage).getByText('Confirmée, à valider')).toBeInTheDocument()
    expect(within(voyage).getByText(/prix confirmé par votre conseiller/)).toBeInTheDocument()
    const enCours = within(voyage)
      .getAllByRole('listitem')
      .find((etape) => etape.getAttribute('aria-current') === 'step')
    expect(enCours).toHaveTextContent('Finalisation')
  })

  it('affiche la conversation choisie', async () => {
    simulerApi({
      'GET /api/moi': { status: 200, body: CAMILLE },
      'GET /api/moi/demandes': { status: 200, body: [SANTORIN, ISLANDE] },
    })
    afficher('/espace')

    const fil = await screen.findByRole('article', { name: 'Conversation sélectionnée' })
    expect(within(fil).getByText('Votre proposition est prête.')).toBeInTheDocument()
    expect(fil).toHaveTextContent('Dossier suivi par Claire')

    await userEvent.setup().click(screen.getByRole('button', { name: /^Islande/ }))

    expect(within(fil).getByText('Voir les aurores boréales.')).toBeInTheDocument()
    expect(fil).not.toHaveTextContent('Dossier suivi par')
    expect(screen.getByRole('button', { name: /^Islande/ })).toHaveAttribute('aria-pressed', 'true')
  })

  it('invite à préparer un premier voyage', async () => {
    simulerApi({
      'GET /api/moi': { status: 200, body: CAMILLE },
      'GET /api/moi/demandes': { status: 200, body: [] },
    })
    afficher('/espace')

    expect(
      await screen.findByRole('heading', { name: 'Pas encore de voyage en cours' }),
    ).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Mes conversations' })).not.toBeInTheDocument()
  })
})
