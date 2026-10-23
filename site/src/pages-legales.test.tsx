import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
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
  return router
}

afterEach(() => {
  changerLangue('fr')
  vi.unstubAllGlobals()
})

describe('pages légales (#218)', () => {
  it.each([
    ['/mentions-legales', 'Mentions légales', 'Éditeur'],
    ['/cgv', 'Conditions générales de vente', 'Frais de dossier'],
    ['/confidentialite', 'Politique de confidentialité et cookies', 'Cookies'],
  ])('%s affiche son texte', async (path, titre, section) => {
    simulerApi({})
    afficher(path)

    expect(await screen.findByRole('heading', { level: 1, name: titre })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 2, name: section })).toBeInTheDocument()
  })

  it('signale un modèle non validé par l’agence', async () => {
    simulerApi({})
    afficher('/mentions-legales')

    expect(await screen.findByRole('note')).toHaveTextContent("Modèle à valider par l'agence")
    // La mention n'apparaît qu'une fois, dans le bandeau, et pas répétée dans le texte.
    expect(screen.getAllByText(/à valider/)).toHaveLength(1)
  })

  it('passe en anglais avec le reste du site', async () => {
    changerLangue('en')
    simulerApi({})
    afficher('/cgv')

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Terms and conditions of sale' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 2, name: 'Booking fee' })).toBeInTheDocument()
  })

  it('mène aux trois pages depuis le pied de page', async () => {
    simulerApi({})
    const router = afficher('/agence')
    const user = userEvent.setup()

    for (const [lien, path, titre] of [
      ['Mentions légales', '/mentions-legales', 'Mentions légales'],
      ['CGV', '/cgv', 'Conditions générales de vente'],
      ['Confidentialité et cookies', '/confidentialite', 'Politique de confidentialité et cookies'],
    ] as const) {
      const pied = await screen.findByRole('navigation', { name: 'Liens légaux' })
      await user.click(within(pied).getByRole('link', { name: lien }))
      expect(await screen.findByRole('heading', { level: 1, name: titre })).toBeInTheDocument()
      expect(router.state.location.pathname).toBe(path)
    }
  })
})
