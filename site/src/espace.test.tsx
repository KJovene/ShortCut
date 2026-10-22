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

/** Fiche de la demande au format du chat (EnregistrerExigences::fiche). */
const FICHE: NonNullable<DemandeClient['conversation']>['fiche'] = {
  typeDeVoyage: 'noces',
  lieuOuZone: 'Santorin',
  villeDepart: 'Paris',
  dateDebut: '2027-05-10',
  dateFin: '2027-05-20',
  datesFlexibles: false,
  nombreDeJours: 10,
  temperatureMin: 25,
  temperatureMax: null,
  adultes: 2,
  enfants: 0,
  agesEnfants: [],
  budgetEuros: 4000,
  budgetParPersonne: false,
  transportInclus: true,
  envies: ['plage'],
  contraintes: [],
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
    jeton: 'j'.repeat(64),
    // Prise en charge par Claire : elle a repris la conversation, le chatbot ne répond plus.
    repriseParConseiller: true,
    fiche: FICHE,
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
    jeton: 'k'.repeat(64),
    repriseParConseiller: false,
    fiche: { ...FICHE, typeDeVoyage: 'circuit', lieuOuZone: 'Islande' },
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
  vi.unstubAllEnvs()
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
    // Photo et climat de mai lus dans le catalogue de l'API.
    expect(await within(voyage).findByText('21 °C en mai')).toBeInTheDocument()
    expect(
      within(voyage).getByRole('img', {
        name: 'Maisons blanches et dômes bleus d’Oia face à la mer',
      }),
    ).toHaveAttribute('src', '/uploads/destinations/santorin.jpg')

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

  describe('« Modifier le voyage »', () => {
    /** Demande encore entre les mains de l'assistant : aucun conseiller n'a repris la conversation. */
    const BALI: DemandeClient = {
      ...SANTORIN,
      id: 240,
      statut: 'nouvelle',
      lieuOuZone: 'Bali',
      conseiller: null,
      transmiseLe: null,
      proposition: null,
      conversation: {
        ...SANTORIN.conversation!,
        jeton: 'm'.repeat(64),
        repriseParConseiller: false,
        messages: [
          { role: 'client', contenu: 'Une villa à Bali en mai.', le: '2026-10-18T10:00:00+02:00' },
          {
            role: 'assistant',
            contenu: 'Pour combien de personnes ?',
            le: '2026-10-18T10:00:05+02:00',
          },
        ],
        fiche: { ...FICHE, lieuOuZone: 'Bali' },
      },
    }

    function afficherEspace(demandes: DemandeClient[]) {
      const api = simulerApi({
        'GET /api/moi': { status: 200, body: CAMILLE },
        'GET /api/moi/demandes': { status: 200, body: demandes },
      })
      afficher('/espace')
      return api
    }

    it('reprend la conversation avec l’assistant pour un changement léger', async () => {
      sessionStorage.clear()
      const api = afficherEspace([BALI])
      const user = userEvent.setup()

      await user.click(await screen.findByRole('button', { name: 'Modifier le voyage' }))
      await user.click(screen.getByRole('button', { name: /Continuer avec l’assistant/ }))

      const fenetre = await screen.findByRole('dialog', { name: 'Assistant voyage' })
      expect(within(fenetre).getByText('Une villa à Bali en mai.')).toBeInTheDocument()
      expect(within(fenetre).getByText('Pour combien de personnes ?')).toBeInTheDocument()

      // Le message part dans la même conversation, authentifiée par son jeton.
      await user.type(
        within(fenetre).getByRole('textbox', { name: 'Votre message' }),
        'Nous serons 4{Enter}',
      )
      await vi.waitFor(() =>
        expect(api.fetch).toHaveBeenCalledWith(
          '/api/chat',
          expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({ 'X-Conversation-Token': 'm'.repeat(64) }),
          }),
        ),
      )
    })

    it('démarre un nouveau voyage vierge après avoir modifié un voyage existant', async () => {
      sessionStorage.clear()
      afficherEspace([BALI])
      const user = userEvent.setup()

      await user.click(await screen.findByRole('button', { name: 'Modifier le voyage' }))
      await user.click(screen.getByRole('button', { name: /Continuer avec l’assistant/ }))
      const fenetre = await screen.findByRole('dialog', { name: 'Assistant voyage' })
      expect(within(fenetre).getByText('Une villa à Bali en mai.')).toBeInTheDocument()

      // Fin de la modification : la fenêtre se ferme, le chat repart à zéro.
      await user.click(within(fenetre).getByRole('button', { name: "Fermer l'assistant" }))
      await user.click(screen.getByRole('button', { name: /Nouveau voyage/ }))

      const nouvelle = await screen.findByRole('dialog', { name: 'Assistant voyage' })
      expect(within(nouvelle).queryByText('Une villa à Bali en mai.')).toBeNull()
      expect(within(nouvelle).queryByText('Pour combien de personnes ?')).toBeNull()
    })

    it('garde l’assistant pour les détails quand le dossier est simplement pris en charge', async () => {
      afficherEspace([
        {
          ...BALI,
          conseiller: { prenom: 'Hugo', nom: 'Bernard' },
          conversation: { ...BALI.conversation!, repriseParConseiller: false },
        },
      ])
      const user = userEvent.setup()

      await user.click(await screen.findByRole('button', { name: 'Modifier le voyage' }))

      expect(screen.getByRole('button', { name: /Continuer avec l’assistant/ })).toBeInTheDocument()
      expect(screen.getByText(/ajustez les détails avec l’assistant/)).toBeInTheDocument()
    })

    it('renvoie de la carte du conseiller vers « Modifier le voyage » pour les ajustements', async () => {
      afficherEspace([
        {
          ...BALI,
          conseiller: { prenom: 'Hugo', nom: 'Bernard' },
          conversation: { ...BALI.conversation!, repriseParConseiller: false },
        },
      ])

      expect(
        await screen.findByText(
          /changements importants ou particuliers passent par votre conseiller\. Pour un ajustement léger .* « Modifier le voyage »/,
        ),
      ).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: 'Continuer la conversation' })).toBeNull()
    })

    it('passe par le conseiller une fois qu’il a repris la main', async () => {
      afficherEspace([SANTORIN])
      const user = userEvent.setup()

      await user.click(await screen.findByRole('button', { name: 'Modifier le voyage' }))

      expect(screen.queryByRole('button', { name: /Continuer avec l’assistant/ })).toBeNull()
      expect(screen.getByText(/Claire suit désormais votre dossier/)).toBeInTheDocument()
    })

    it('propose un rendez-vous Calendly quand la page de l’agence est réglée', async () => {
      vi.stubEnv('VITE_CALENDLY_URL', 'https://calendly.com/detour/modifier-mon-voyage')
      afficherEspace([SANTORIN])
      const user = userEvent.setup()

      await user.click(await screen.findByRole('button', { name: 'Modifier le voyage' }))

      const lien = screen.getByRole('link', { name: /Prendre rendez-vous avec Claire/ })
      expect(lien).toHaveAttribute('href', 'https://calendly.com/detour/modifier-mon-voyage')
      expect(lien).toHaveAttribute('target', '_blank')
    })

    it('n’affiche pas de rendez-vous tant que la page Calendly n’est pas réglée', async () => {
      vi.stubEnv('VITE_CALENDLY_URL', '')
      afficherEspace([BALI])
      const user = userEvent.setup()

      await user.click(await screen.findByRole('button', { name: 'Modifier le voyage' }))

      expect(screen.queryByRole('link', { name: /Prendre rendez-vous/ })).toBeNull()
      expect(screen.getByRole('button', { name: /Continuer avec l’assistant/ })).toBeInTheDocument()
    })
  })
})

describe('Mes voyages en cours', () => {
  const MARRAKECH: DemandeClient = {
    ...SANTORIN,
    id: 229,
    statut: 'proposition_envoyee',
    typeDeVoyage: 'groupe',
    lieuOuZone: 'Marrakech',
    nombreDeJours: 5,
    proposition: { ...SANTORIN.proposition!, jeton: 'e'.repeat(64), totalCentimes: 945200 },
  }
  const COSTA_RICA: DemandeClient = {
    ...SANTORIN,
    id: 230,
    statut: 'nouvelle',
    typeDeVoyage: 'sejour',
    lieuOuZone: 'Costa Rica',
    nombreDeJours: null,
    conseiller: null,
    proposition: null,
  }

  it('passe d’un voyage en cours à l’autre et bloque un 4e voyage', async () => {
    simulerApi({
      'GET /api/moi': { status: 200, body: CAMILLE },
      'GET /api/moi/demandes': { status: 200, body: [COSTA_RICA, MARRAKECH, SANTORIN, ISLANDE] },
    })
    afficher('/espace')

    const voyages = await screen.findByRole('group', { name: 'Mes voyages en cours' })
    // Trois voyages en cours ; l'Islande, perdue, n'en fait pas partie.
    expect(within(voyages).getAllByRole('button')).toHaveLength(3)
    expect(screen.getByRole('region', { name: 'Costa Rica, Séjour' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Nouveau voyage/ })).toBeDisabled()
    expect(screen.getByText(/3 voyages en cours, le maximum/)).toBeInTheDocument()

    await userEvent.setup().click(within(voyages).getByRole('button', { name: /Marrakech/ }))

    const marrakech = screen.getByRole('region', { name: 'Marrakech, 5 jours' })
    expect(within(voyages).getByRole('button', { name: /Marrakech/ })).toHaveAttribute(
      'aria-pressed',
      'true',
    )
    expect(within(marrakech).getByRole('link', { name: 'Voir ma proposition' })).toHaveAttribute(
      'href',
      `/proposition/${'e'.repeat(64)}`,
    )
  })
})
