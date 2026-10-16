import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { createQueryClient } from './lib/queryClient'
import { createAppRouter } from './router'

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
  it("affiche la page d'accueil", async () => {
    renderAt('/')
    expect(
      await screen.findByRole('heading', { name: 'Votre voyage sur mesure' }),
    ).toBeInTheDocument()
  })

  it('affiche la page 404 pour une adresse inconnue', async () => {
    renderAt('/adresse-inconnue')
    expect(await screen.findByRole('heading', { name: 'Page introuvable' })).toBeInTheDocument()
  })
})
