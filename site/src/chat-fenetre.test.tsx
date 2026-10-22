import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
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
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('fenêtre du chatbot, hors de l’accueil', () => {
  it('garde le focus dans le champ pendant la saisie, au premier message comme ensuite', async () => {
    sessionStorage.clear()
    simulerApi({
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
    })
    afficher('/types-de-voyages')
    const user = userEvent.setup()

    await user.click((await screen.findAllByRole('button', { name: /Construire mon voyage/ }))[0])
    const fenetre = await screen.findByRole('dialog', { name: 'Assistant voyage' })

    // Premier message : le champ est pré-rempli par le type de voyage choisi.
    const premier = within(fenetre).getByRole('textbox')
    await user.type(premier, '10 jours en mai')
    expect(premier).toHaveFocus()
    expect(premier).toHaveValue('Un séjour pour deux, 10 jours en mai')
    await user.keyboard('{Enter}')
    expect(
      await within(fenetre).findByText('Un séjour pour deux, 10 jours en mai'),
    ).toBeInTheDocument()

    // Suite de la conversation, dans le champ « Votre message ».
    const suite = within(fenetre).getByRole('textbox', { name: 'Votre message' })
    await user.type(suite, 'Budget 4 000 €')
    expect(suite).toHaveFocus()
    expect(suite).toHaveValue('Budget 4 000 €')
  })

  it('repart d’une discussion vierge quand le client construit un autre voyage', async () => {
    sessionStorage.clear()
    simulerApi({
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
    })
    afficher('/types-de-voyages')
    const user = userEvent.setup()

    await user.click((await screen.findAllByRole('button', { name: /Construire mon voyage/ }))[0])
    const fenetre = await screen.findByRole('dialog', { name: 'Assistant voyage' })
    await user.type(within(fenetre).getByRole('textbox'), '10 jours en mai{Enter}')
    expect(
      await within(fenetre).findByText('Un séjour pour deux, 10 jours en mai'),
    ).toBeInTheDocument()
    await user.click(within(fenetre).getByRole('button', { name: /Fermer/ }))

    await user.click(screen.getAllByRole('button', { name: /Construire mon voyage/ })[1])
    const nouvelle = await screen.findByRole('dialog', { name: 'Assistant voyage' })
    expect(
      within(nouvelle).queryByText('Un séjour pour deux, 10 jours en mai'),
    ).not.toBeInTheDocument()
    expect(within(nouvelle).getByRole('textbox')).toHaveValue('Un circuit en plusieurs étapes, ')
  })
})
