import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { changerLangue } from './lib/langue'
import { createQueryClient } from './lib/queryClient'
import { createAppRouter } from './router'
import { simulerApi } from './test/api'
import { PAGES_DE_DEMO } from './test/pages'

function afficher(path: string) {
  const queryClient = createQueryClient()
  const router = createAppRouter(queryClient, createMemoryHistory({ initialEntries: [path] }))
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
}

/** La page de l'agence telle que l'API la traduit (TraductionSubscriber). */
const AGENCE_EN = {
  ...PAGES_DE_DEMO.agence,
  titre: 'Advisors who *know the ground*',
  seoTitre: 'Our agency and our advisors',
  contenu:
    '<p>For more than fifteen years, our advisors have been building tailor-made trips.</p>' +
    '<h2>Our commitments</h2><ul><li>A first complete proposal in a few minutes.</li>' +
    '<li>A dedicated advisor who confirms the prices.</li></ul>',
}

describe('bouton FR | EN', () => {
  beforeEach(() => {
    sessionStorage.clear()
    localStorage.clear()
  })

  afterEach(() => {
    changerLangue('fr')
    vi.unstubAllGlobals()
  })

  it('garde le site en français par défaut', async () => {
    simulerApi({})
    afficher('/agence')

    expect(await screen.findByRole('heading', { level: 1, name: /connaissent le terrain/ }))
    expect(screen.getByRole('button', { name: 'Français' })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('link', { name: 'Connexion' })).toBeInTheDocument()
    expect(document.documentElement.lang).toBe('fr')
  })

  it('passe tout le site en anglais, contenus du backoffice compris', async () => {
    const api = simulerApi({ 'GET /api/pages/agence?langue=en': { status: 200, body: AGENCE_EN } })
    afficher('/agence')
    await screen.findByRole('heading', { level: 1, name: /connaissent le terrain/ })

    await userEvent.click(screen.getByRole('button', { name: 'English' }))

    // Contenu saisi dans le backoffice, traduit par l'API.
    expect(
      await screen.findByRole('heading', { level: 1, name: /know the ground/ }),
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Our commitments' })).toBeInTheDocument()
    // Textes fixes du site.
    const navigation = screen.getAllByRole('navigation', { name: 'Main navigation' })[0]!
    expect(within(navigation).getByRole('link', { name: 'The agency' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Log in' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Sign up' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'The team' })).toBeInTheDocument()
    expect(api.fetch).toHaveBeenCalledWith('/api/pages/agence?langue=en', expect.anything())
    await waitFor(() => expect(document.title).toBe('Our agency and our advisors'))
    expect(document.documentElement.lang).toBe('en')
    // Le choix est gardé pour la prochaine visite.
    expect(localStorage.getItem('langue')).toBe('en')

    await userEvent.click(screen.getByRole('button', { name: 'Français' }))
    expect(
      await screen.findByRole('heading', { level: 1, name: /connaissent le terrain/ }),
    ).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Connexion' })).toBeInTheDocument()
  })

  it('ouvre le chatbot en anglais', async () => {
    simulerApi({})
    changerLangue('en')
    afficher('/agence')

    await userEvent.click(await screen.findByRole('button', { name: 'Talk to the assistant' }))

    expect(
      await screen.findByRole('heading', { name: 'Tell us about your next holiday' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Plan my trip/ })).toBeInTheDocument()
  })
})
