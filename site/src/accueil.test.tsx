import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MAX_VOYAGES_ACCUEIL } from './features/accueil/carte'
import { changerLangue } from './lib/langue'
import { createQueryClient } from './lib/queryClient'
import { createAppRouter } from './router'
import { simulerApi } from './test/api'
import { DESTINATIONS_DE_DEMO } from './test/destinations'

function afficher() {
  const queryClient = createQueryClient()
  const router = createAppRouter(queryClient, createMemoryHistory({ initialEntries: ['/'] }))
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
}

/** Voyages du bandeau sous la carte, par leur nom. */
async function voyagesDuBandeau() {
  const bandeau = await screen.findByRole('region', { name: 'Destinations à la une' })
  return within(bandeau)
    .getAllByRole('link')
    .map((lien) => lien.getAttribute('href'))
}

const MIS_EN_AVANT = 'GET /api/public/destinations?miseEnAvant=true'

afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
  changerLangue('fr')
})

describe('accueil : carte et bandeau des voyages', () => {
  it('ne montre que les voyages mis en avant dans le backoffice', async () => {
    simulerApi({})
    afficher()

    const liens = await voyagesDuBandeau()
    const misEnAvant = DESTINATIONS_DE_DEMO.filter((d) => d.miseEnAvant).map(
      (d) => `/destinations/${d.slug}`,
    )
    expect(liens).toEqual(misEnAvant)
    expect(liens).not.toContain('/destinations/islande')
  })

  it(`en montre ${MAX_VOYAGES_ACCUEIL} au plus`, async () => {
    const chaude = DESTINATIONS_DE_DEMO.find((d) => d.slug === 'santorin')!
    const nombreux = Array.from({ length: 14 }, (_, i) => ({
      ...chaude,
      slug: `voyage-${i}`,
      nom: `Voyage ${i}`,
      miseEnAvant: true,
    }))
    simulerApi({ [MIS_EN_AVANT]: { status: 200, body: nombreux } })
    afficher()

    expect(await voyagesDuBandeau()).toHaveLength(MAX_VOYAGES_ACCUEIL)
  })

  it('affiche les voyages même quand l’un d’eux est mal saisi dans le backoffice', async () => {
    const complet = DESTINATIONS_DE_DEMO.filter((d) => d.miseEnAvant)
    // L'API omet les champs vides : ni description ni coordonnées, aucun climat saisi.
    const { description, latitude, longitude, ...incomplet } = {
      ...complet[0],
      slug: 'chez-lolo',
      nom: 'Chez Lolo',
      periodesClimatiques: [],
      exemples: [],
    }
    void description
    void latitude
    void longitude
    const sansNom = { ...complet[1], slug: 'sans-nom', nom: undefined }
    simulerApi({ [MIS_EN_AVANT]: { status: 200, body: [...complet, incomplet, sansNom] } })
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    afficher()

    const liens = await voyagesDuBandeau()
    // La destination incomplète est là (hors carte, faute de coordonnées) ; l'invalide est écartée.
    expect(liens).toContain('/destinations/chez-lolo')
    expect(liens).not.toContain('/destinations/sans-nom')
    expect(liens).toHaveLength(complet.length + 1)
    expect(console.warn).toHaveBeenCalled()
  })

  it('prend le titre de chaque thème dans les textes du site', async () => {
    simulerApi({})
    afficher()

    const titre = await screen.findByRole('heading', {
      level: 1,
      name: 'Où irez-vous chercher le soleil ?',
    })
    // Les mots entre astérisques sont l'emphase du titre, et leur « o » fait le saut du O.
    expect(titre.querySelector('em')).toHaveTextContent('soleil')
    expect(titre.querySelector('.saut-o')).toHaveTextContent('o')

    await userEvent.setup().click(screen.getByRole('button', { name: 'Voyages froids' }))

    expect(
      screen.getByRole('heading', { level: 1, name: 'Où irez-vous chercher le froid ?' }),
    ).toBeVisible()
  })

  it('passe aux voyages froids, avec une autre accroche', async () => {
    simulerApi({
      [MIS_EN_AVANT]: {
        status: 200,
        body: DESTINATIONS_DE_DEMO.map((d) => ({
          ...d,
          miseEnAvant: d.miseEnAvant || d.slug === 'islande',
        })).filter((d) => d.miseEnAvant),
      },
    })
    afficher()

    expect(
      await screen.findByRole('heading', { level: 1, name: /chercher le soleil/ }),
    ).toBeInTheDocument()
    expect(await voyagesDuBandeau()).not.toContain('/destinations/islande')

    await userEvent.setup().click(screen.getByRole('button', { name: 'Voyages froids' }))

    expect(screen.getByRole('heading', { level: 1, name: /chercher le froid/ })).toBeVisible()
    expect(screen.getByRole('button', { name: 'Voyages froids' })).toHaveAttribute(
      'aria-pressed',
      'true',
    )
    expect(await voyagesDuBandeau()).toEqual(['/destinations/islande'])
  })

  it('annonce l’absence de voyage froid à la une', async () => {
    simulerApi({})
    afficher()

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Voyages froids' }))

    expect(await screen.findByText(/Aucun voyage au frais à la une/)).toBeInTheDocument()
  })

  it('ouvre la fiche au survol d’un point et la ferme en le quittant', async () => {
    simulerApi({})
    afficher()
    const user = userEvent.setup()
    // La fiche mobile (en bas de la carte) montre déjà Santorin, le voyage actif par défaut.
    const fiches = () => screen.queryAllByRole('heading', { level: 2, name: 'Lisbonne, Portugal' })

    const point = (await screen.findAllByRole('button', { name: /^Lisbonne, Portugal/ }))[0]
    expect(fiches()).toHaveLength(0)

    await user.hover(point)
    // La fiche s'ouvre près du point (ordinateur), et la fiche mobile suit le voyage survolé.
    await waitFor(() => expect(fiches()).toHaveLength(2))

    await user.unhover(point)
    await waitFor(() => expect(fiches()).toHaveLength(1))
  })

  it('garde la fiche ouverte quand le pointeur passe du point à la fiche', async () => {
    simulerApi({})
    afficher()
    const user = userEvent.setup()

    const point = (await screen.findAllByRole('button', { name: /^Lisbonne, Portugal/ }))[0]
    await user.hover(point)
    const fiche = (
      await screen.findAllByRole('heading', { level: 2, name: 'Lisbonne, Portugal' })
    )[0].closest('article')!

    await user.unhover(point)
    await user.hover(fiche)
    await new Promise((r) => setTimeout(r, 300))
    expect(screen.getAllByRole('heading', { level: 2, name: 'Lisbonne, Portugal' })).toHaveLength(2)

    await user.unhover(fiche)
    await waitFor(() =>
      expect(screen.getAllByRole('heading', { level: 2, name: 'Lisbonne, Portugal' })).toHaveLength(
        1,
      ),
    )
  })
})

describe('accueil : construire un voyage depuis la carte', () => {
  it('transmet la destination choisie à l’assistant, en anglais aussi', async () => {
    sessionStorage.clear()
    const api = simulerApi({
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
      'POST /api/chat/conversations': { status: 201, body: { jeton: 'j'.repeat(64), demande: 1 } },
      'POST /api/chat': { status: 200, body: 'event: fin\ndata: {"terminee":false}\n\n' },
    })
    changerLangue('en')
    afficher()
    const user = userEvent.setup()

    // La fiche mobile montre Santorin, le voyage actif par défaut.
    await user.click(await screen.findByRole('button', { name: 'Plan' }))
    const champ = await screen.findByDisplayValue(/^A trip to Santorin in /)
    await user.type(champ, 'for two{Enter}')

    await waitFor(() =>
      expect(api.envoisA('POST /api/chat/conversations')[0]).toMatchObject({
        destination: 'Santorin',
        langue: 'en',
      }),
    )
  })
})
