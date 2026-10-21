import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { decouperContenu, photoNommee } from './features/pages/pageDuSite'
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
  return router
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('découpage du contenu', () => {
  it('sépare l’introduction des sections ouvertes par un intertitre', () => {
    expect(
      decouperContenu(
        '<p>Intro <strong>en gras</strong></p><h2>Engagements</h2><p>Texte</p><ul><li>Un</li><li>Deux</li></ul>',
      ),
    ).toEqual({
      intro: ['Intro en gras'],
      sections: [{ titre: 'Engagements', paragraphes: ['Texte'], elements: ['Un', 'Deux'] }],
    })
  })

  it('ne garde que le texte, sans exécuter de HTML', () => {
    const contenu = decouperContenu('<p>Bonjour<img src="x" onerror="alert(1)"></p>')
    expect(contenu.intro).toEqual(['Bonjour'])
  })

  it('lit le texte de l’éditeur du backoffice : titres, retours à la ligne et blocs', () => {
    expect(
      decouperContenu('<div>Un<br>Deux<br><br></div><h1>Engagements</h1><p>Trois</p>'),
    ).toEqual({
      intro: ['Un', 'Deux'],
      sections: [{ titre: 'Engagements', paragraphes: ['Trois'], elements: [] }],
    })
  })

  it('retrouve une photo par son nom, avec ou sans empreinte du backoffice', () => {
    const photos = ['/uploads/pages/circuit.jpg', '/uploads/pages/sejour-1a2b3c.png']
    expect(photoNommee(photos, 'sejour')).toBe('/uploads/pages/sejour-1a2b3c.png')
    expect(photoNommee(photos, 'circuit')).toBe('/uploads/pages/circuit.jpg')
    expect(photoNommee(photos, 'noces')).toBeUndefined()
  })
})

describe('pages vitrines (contenus de l’API)', () => {
  it('l’agence affiche sa présentation et ses engagements', async () => {
    simulerApi({})
    afficher('/agence')

    expect(
      await screen.findByText(/Depuis plus de quinze ans, nos conseillers/),
    ).toBeInTheDocument()
    // Titre du backoffice : les mots entre astérisques sont mis en valeur.
    const titre = screen.getByRole('heading', {
      level: 1,
      name: 'Des conseillers qui connaissent le terrain',
    })
    expect(titre.querySelector('em')).toHaveTextContent('connaissent le terrain')
    const engagements = screen.getByRole('region', { name: 'Nos engagements' })
    expect(within(engagements).getAllByRole('listitem')).toHaveLength(2)
    await waitFor(() => expect(document.title).toBe('Notre agence et nos conseillers'))
  })

  it('le contact affiche les horaires et l’adresse, et ouvre l’assistant', async () => {
    simulerApi({})
    afficher('/contact')

    expect(await screen.findByText('Du lundi au vendredi de 9 h à 18 h 30.')).toBeInTheDocument()
    expect(screen.getByText('12 rue des Voyageurs, 75000 Paris.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: "Ouvrir l'assistant" })).toBeInTheDocument()
  })

  it('chaque type de voyage reprend le texte de sa section', async () => {
    simulerApi({})
    afficher('/types-de-voyages')

    const circuits = (await screen.findByRole('heading', { name: 'Circuits' })).closest('article')!
    expect(
      within(circuits).getByText('Plusieurs étapes, un itinéraire pensé jour par jour.'),
    ).toBeInTheDocument()
    expect(within(circuits).getByRole('button', { name: /Construire mon voyage/ })).toBeVisible()
  })

  it('l’accueil prend son titre dans l’API, et s’affiche même sans elle', async () => {
    simulerApi({ 'GET /api/pages/accueil': { status: 500 } })
    afficher('/')

    // L'appel en échec est relancé une fois (retry) avant que l'accueil s'affiche sans lui.
    expect(
      await screen.findByRole(
        'heading',
        { level: 1, name: /Où irez-vous chercher le soleil/ },
        { timeout: 4000 },
      ),
    ).toBeInTheDocument()
    await waitFor(() => expect(document.title).toMatch(/Votre voyage chiffré en 2 minutes/))
  })

  it('propose de réessayer quand le contenu ne se charge pas', async () => {
    simulerApi({
      // Deux échecs : le premier appel et sa relance automatique.
      'GET /api/pages/agence': [
        { status: 500 },
        { status: 500 },
        { status: 200, body: PAGES_DE_DEMO.agence },
      ],
    })
    afficher('/agence')

    expect(await screen.findByRole('alert', {}, { timeout: 4000 })).toHaveTextContent(
      /ne s’affiche pas pour le moment/,
    )
    expect(screen.getByRole('button', { name: /Décrire mon voyage à l’assistant/ })).toBeVisible()

    await userEvent.setup().click(screen.getByRole('button', { name: 'Réessayer' }))

    expect(
      await screen.findByText(/Depuis plus de quinze ans, nos conseillers/),
    ).toBeInTheDocument()
  })
})
