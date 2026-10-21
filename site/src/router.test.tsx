import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createQueryClient } from './lib/queryClient'
import { createAppRouter } from './router'
import { simulerApi } from './test/api'

function renderAt(path: string) {
  const queryClient = createQueryClient()
  const router = createAppRouter(queryClient, createMemoryHistory({ initialEntries: [path] }))
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
}

describe('routeur', () => {
  beforeEach(() => {
    simulerApi({})
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it("affiche la page d'accueil", async () => {
    renderAt('/')
    expect(
      await screen.findByRole('heading', { level: 1, name: /Où irez-vous chercher le soleil/ }),
    ).toBeInTheDocument()
  })

  it("transforme la section de l'assistant en discussion au premier envoi", async () => {
    sessionStorage.clear()
    renderAt('/')
    const champ = await screen.findByRole('textbox', { name: 'Décrivez votre voyage' })
    // Entrée envoie le message, comme le bouton « Construire mon voyage ».
    await userEvent.type(champ, '10 jours en mai à deux{Enter}')
    expect(await screen.findByRole('textbox', { name: 'Votre message' })).toBeInTheDocument()
    expect(screen.getByText('10 jours en mai à deux')).toBeInTheDocument()
    // Le récapitulatif existe en deux exemplaires : repliable sur mobile, en colonne sur ordinateur.
    expect(screen.getAllByRole('heading', { name: 'Votre voyage' })).toHaveLength(2)
  })

  it('affiche la fiche d’une destination', async () => {
    renderAt('/destinations/islande')
    expect(await screen.findByRole('heading', { level: 1, name: 'Islande' })).toBeInTheDocument()
    expect(screen.getByRole('table', { name: /Températures moyennes/ })).toBeInTheDocument()
  })

  it('affiche la page 404 pour une adresse inconnue', async () => {
    renderAt('/adresse-inconnue')
    expect(await screen.findByRole('heading', { name: 'Page introuvable' })).toBeInTheDocument()
  })

  it('affiche la page 404 pour une destination inconnue', async () => {
    renderAt('/destinations/atlantide')
    expect(await screen.findByRole('heading', { name: 'Page introuvable' })).toBeInTheDocument()
  })
})
