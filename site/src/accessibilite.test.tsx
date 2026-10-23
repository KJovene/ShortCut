import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen } from '@testing-library/react'
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
}

/** Pages publiques du site : celles du plan du site (sitemap.xml) et la page introuvable. */
const PAGES = [
  '/',
  '/destinations',
  '/destinations/islande',
  '/types-de-voyages',
  '/agence',
  '/contact',
  '/proposition/exemple',
  '/mentions-legales',
  '/cgv',
  '/confidentialite',
  '/connexion',
  '/inscription',
  '/page-inconnue',
]

/**
 * Contrôles automatisables en jsdom (#219). Les contrastes et le rendu de 360 à 1440 px se vérifient dans
 * le navigateur, avec Lighthouse et axe DevTools.
 */
describe('accessibilité des pages', () => {
  beforeEach(() => {
    sessionStorage.clear()
    simulerApi({})
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it.each(PAGES)(
    '%s : un titre principal, des images décrites, des contrôles nommés',
    async (path) => {
      afficher(path)
      await screen.findByRole('heading', { level: 1 })

      expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1)
      for (const image of document.querySelectorAll('img')) {
        expect(image.hasAttribute('alt'), image.outerHTML).toBe(true)
      }
      for (const controle of [
        ...screen.queryAllByRole('button'),
        ...screen.queryAllByRole('link'),
      ]) {
        expect(controle).toHaveAccessibleName()
      }
      for (const champ of document.querySelectorAll('input:not([type=hidden]), textarea, select')) {
        expect(champ).toHaveAccessibleName()
      }
    },
  )

  it('le premier arrêt au clavier mène au contenu principal', async () => {
    afficher('/agence')
    await screen.findByRole('heading', { level: 1 })

    await userEvent.setup().tab()

    const lien = screen.getByRole('link', { name: 'Aller au contenu' })
    expect(lien).toHaveFocus()
    expect(lien).toHaveAttribute('href', '#contenu')
    expect(document.getElementById('contenu')?.tagName).toBe('MAIN')
  })
})
