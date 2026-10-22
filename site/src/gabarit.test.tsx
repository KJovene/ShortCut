import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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

const description = () =>
  document.head.querySelector('meta[name="description"]')?.getAttribute('content')

/** Les neuf pages du cahier des charges (section 4), plus celles du compte. */
const PAGES = [
  ['/', /Où irez-vous chercher le soleil/],
  ['/destinations', /Où partir/],
  ['/destinations/islande', 'Islande'],
  ['/types-de-voyages', /voyage/],
  ['/agence', /conseillers/],
  ['/contact', /Parlons-en/],
  // Sans jeton, aucune proposition : la page le dit.
  ['/proposition/exemple', 'Proposition introuvable'],
  ['/mentions-legales', 'Mentions légales'],
  ['/cgv', 'Conditions générales de vente'],
  ['/confidentialite', /confidentialité/],
  ['/connexion', 'Connexion'],
  ['/inscription', 'Créer un compte'],
  ['/une-page-qui-n-existe-pas', 'Page introuvable'],
] as const

const titres = new Map<string, string>()

describe('gabarit du site', () => {
  beforeEach(() => {
    sessionStorage.clear()
    simulerApi({})
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it.each(PAGES)('%s répond avec son titre et sa description', async (path, titre) => {
    afficher(path)

    expect(await screen.findByRole('heading', { level: 1, name: titre })).toBeInTheDocument()
    await waitFor(() => expect(document.title).not.toBe(''))
    expect(description()).toBeTruthy()
    titres.set(path, document.title)
  })

  it('donne à chaque page un titre différent', () => {
    // Relevés par les tests précédents, exécutés dans l'ordre du fichier.
    expect(new Set(titres.values()).size).toBe(PAGES.length)
  })

  it('propose le chatbot hors de l’accueil, qui a sa propre section', async () => {
    afficher('/agence')

    expect(await screen.findByRole('button', { name: "Parler à l'assistant" })).toBeInTheDocument()
  })

  it('met les liens légaux dans le pied de page', async () => {
    afficher('/contact')

    const legaux = await screen.findByRole('navigation', { name: 'Liens légaux' })
    for (const lien of ['Mentions légales', 'CGV', 'Confidentialité et cookies']) {
      expect(within(legaux).getByRole('link', { name: lien })).toBeInTheDocument()
    }
  })

  it('ouvre le menu mobile, le referme avec Échap et après une navigation', async () => {
    const router = afficher('/contact')
    const user = userEvent.setup()

    const bouton = await screen.findByRole('button', { name: 'Ouvrir le menu' })
    await user.click(bouton)
    const menu = document.getElementById('menu-mobile')!
    expect(within(menu).getByRole('link', { name: 'Accueil' })).toBeInTheDocument()

    await user.keyboard('{Escape}')
    expect(document.getElementById('menu-mobile')).toBeNull()
    expect(bouton).toHaveFocus()

    await user.click(bouton)
    await user.click(
      within(document.getElementById('menu-mobile')!).getByRole('link', { name: "L'agence" }),
    )
    await waitFor(() => expect(router.state.location.pathname).toBe('/agence'))
    expect(document.getElementById('menu-mobile')).toBeNull()
  })
})
