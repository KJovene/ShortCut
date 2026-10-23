import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, waitFor, within } from '@testing-library/react'
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
  return router
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('pages vitrines (textes du site)', () => {
  it('l’agence affiche sa présentation et ses engagements', async () => {
    simulerApi({})
    afficher('/agence')

    expect(
      await screen.findByText(/Depuis plus de quinze ans, nos conseillers/),
    ).toBeInTheDocument()
    // Les mots entre astérisques du titre sont mis en valeur.
    const titre = screen.getByRole('heading', {
      level: 1,
      name: 'Des conseillers qui connaissent le terrain',
    })
    expect(titre.querySelector('em')).toHaveTextContent('connaissent le terrain')
    const engagements = screen.getByRole('region', { name: 'Nos engagements' })
    expect(within(engagements).getAllByRole('listitem')).toHaveLength(3)
    await waitFor(() =>
      expect(document.title).toBe("L'agence · Nos conseillers et nos engagements"),
    )
  })

  it('le contact affiche les horaires et l’adresse, et ouvre l’assistant', async () => {
    simulerApi({})
    afficher('/contact')

    expect(
      await screen.findByText('Du lundi au vendredi de 9 h à 18 h 30, le samedi de 10 h à 17 h.'),
    ).toBeInTheDocument()
    expect(screen.getByText(/12 rue des Voyageurs, 75000 Paris/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: "Ouvrir l'assistant" })).toBeInTheDocument()
  })

  it('chaque type de voyage a son texte et sa photo', async () => {
    simulerApi({})
    afficher('/types-de-voyages')

    const circuits = (await screen.findByRole('heading', { name: 'Circuits' })).closest('article')!
    expect(within(circuits).getByText(/Plusieurs étapes, un itinéraire pensé/)).toBeInTheDocument()
    expect(circuits.querySelector('img')).toHaveAttribute('src', '/images/pages/circuit.jpg')
    expect(within(circuits).getByRole('button', { name: /Construire mon voyage/ })).toBeVisible()
  })
})
