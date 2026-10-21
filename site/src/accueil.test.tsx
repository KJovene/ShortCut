import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createQueryClient } from './lib/queryClient'
import { createAppRouter } from './router'
import { simulerApi } from './test/api'

function afficherAccueil() {
  const queryClient = createQueryClient()
  const router = createAppRouter(queryClient, createMemoryHistory({ initialEntries: ['/'] }))
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
}

/** Épingles d'une destination : la carte existe en deux exemplaires, ordinateur et mobile. */
const epingles = (nom: string) => screen.queryAllByRole('button', { name: new RegExp(`^${nom}, `) })

describe('carte du monde de l’accueil', () => {
  beforeEach(() => {
    sessionStorage.clear()
    simulerApi({})
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('n’épingle que les destinations mises en avant, le bandeau les garde toutes', async () => {
    afficherAccueil()

    const bandeau = await screen.findByRole('region', { name: 'Tous nos voyages' })
    // Les huit destinations publiées du catalogue de démo, dont quatre mises en avant.
    expect(within(bandeau).getAllByRole('link')).toHaveLength(8)
    for (const nom of [
      'Lisbonne, Portugal',
      'Marrakech, Maroc',
      'Santorin, Grèce',
      'Bali, Indonésie',
    ]) {
      expect(epingles(nom)).toHaveLength(2)
    }
    for (const nom of ['Rome, Italie', 'Tenerife, Canaries', 'Islande', 'Costa Rica']) {
      expect(epingles(nom)).toHaveLength(0)
      expect(within(bandeau).getByRole('link', { name: new RegExp(nom) })).toBeInTheDocument()
    }
  })

  it('garde l’épingle active quand le bandeau survole un voyage hors de la carte', async () => {
    afficherAccueil()
    const user = userEvent.setup()

    // La carte s'ouvre sur la première destination mise en avant.
    const bandeau = await screen.findByRole('region', { name: 'Tous nos voyages' })
    for (const epingle of epingles('Lisbonne, Portugal')) {
      expect(epingle).toHaveAttribute('aria-pressed', 'true')
    }

    await user.hover(within(bandeau).getByRole('link', { name: /Rome, Italie/ }))
    for (const epingle of epingles('Lisbonne, Portugal')) {
      expect(epingle).toHaveAttribute('aria-pressed', 'true')
    }

    await user.hover(within(bandeau).getByRole('link', { name: /Bali, Indonésie/ }))
    for (const epingle of epingles('Bali, Indonésie')) {
      expect(epingle).toHaveAttribute('aria-pressed', 'true')
    }
    expect(epingles('Lisbonne, Portugal')[0]).toHaveAttribute('aria-pressed', 'false')
  })
})
