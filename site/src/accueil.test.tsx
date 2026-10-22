import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MAX_VOYAGES_ACCUEIL } from './features/accueil/carte'
import { createQueryClient } from './lib/queryClient'
import { createAppRouter } from './router'
import { simulerApi } from './test/api'
import { DESTINATIONS_DE_DEMO } from './test/destinations'
import { PAGES_DE_DEMO } from './test/pages'

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

  it('prend le titre de chaque thème dans les contenus du site', async () => {
    simulerApi({
      'GET /api/pages/accueil': {
        status: 200,
        body: {
          ...PAGES_DE_DEMO.accueil,
          titre: 'Cap sur *le soleil* !',
          titreFroid: 'Cap sur *les aurores* !',
        },
      },
    })
    afficher()

    const titre = await screen.findByRole('heading', { level: 1, name: 'Cap sur le soleil !' })
    expect(within(titre).getByText('le soleil').tagName).toBe('EM')

    await userEvent.setup().click(screen.getByRole('button', { name: 'Voyages froids' }))

    expect(screen.getByRole('heading', { level: 1, name: 'Cap sur les aurores !' })).toBeVisible()
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

    expect(screen.getByRole('heading', { level: 1, name: /chercher la fraîcheur/ })).toBeVisible()
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
