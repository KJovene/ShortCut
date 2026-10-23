import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { changerLangue } from './lib/langue'
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

  it('passe les textes fixes en anglais, titres des pages compris', async () => {
    simulerApi({})
    afficher('/agence')
    await screen.findByRole('heading', { level: 1, name: /connaissent le terrain/ })

    await userEvent.click(screen.getByRole('button', { name: 'English' }))

    // Textes fixes du site.
    expect(await screen.findByRole('link', { name: 'Log in' })).toBeInTheDocument()
    const navigation = screen.getAllByRole('navigation', { name: 'Main navigation' })[0]!
    expect(within(navigation).getByRole('link', { name: 'The agency' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Log in' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Sign up' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'The team' })).toBeInTheDocument()
    // Contenus de la page, tirés des textes du site.
    expect(
      screen.getByRole('heading', { level: 1, name: 'Advisors who know their destinations' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Our commitments' })).toBeInTheDocument()
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

    // Visiteur non connecté : le questionnaire d'accueil, traduit.
    expect(
      await screen.findByRole('heading', { name: 'What kind of trip do you have in mind?' }),
    ).toBeInTheDocument()
    expect(screen.getByText('Question 1 of 3')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Honeymoon' })).toBeInTheDocument()
  })
})
