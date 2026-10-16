import { QueryClientProvider } from '@tanstack/react-query'
import { createMemoryHistory, RouterProvider } from '@tanstack/react-router'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ouvrirBackoffice, type Profil } from '@/lib/auth'
import { createQueryClient } from '@/lib/queryClient'
import { createAppRouter } from '@/router'
import { simulerApi } from '@/test/api'

vi.mock('@/lib/auth', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/auth')>()),
  ouvrirBackoffice: vi.fn(),
}))

const LEA: Profil = {
  email: 'lea@shortcut.test',
  prenom: 'Léa',
  nom: 'Dubois',
  telephone: null,
  role: 'ROLE_CONSEILLER',
}
const CAMILLE: Profil = {
  email: 'camille@example.com',
  prenom: 'Camille',
  nom: 'Durand',
  telephone: null,
  role: 'ROLE_CLIENT',
}

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
  vi.clearAllMocks()
})

describe('connexion', () => {
  async function seConnecter(email: string, motDePasse: string) {
    await screen.findByRole('heading', { name: 'Connexion' })
    const user = userEvent.setup()
    await user.type(screen.getByLabelText('E-mail'), email)
    await user.type(screen.getByLabelText('Mot de passe'), motDePasse)
    await user.click(screen.getByRole('button', { name: 'Se connecter' }))
  }

  it('ouvre le backoffice pour un conseiller', async () => {
    const api = simulerApi({ 'POST /api/connexion': { status: 200, body: LEA } })
    afficher('/connexion')

    await seConnecter('lea@shortcut.test', 'shortcut')

    await vi.waitFor(() => expect(ouvrirBackoffice).toHaveBeenCalled())
    expect(api.envoisA('POST /api/connexion')).toEqual([
      { email: 'lea@shortcut.test', motDePasse: 'shortcut' },
    ])
  })

  it('emmène un client sur son profil', async () => {
    simulerApi({ 'POST /api/connexion': { status: 200, body: CAMILLE } })
    const router = afficher('/connexion')

    await seConnecter('camille@example.com', 'shortcut')

    expect(await screen.findByRole('heading', { name: 'Mon profil' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/profil')
    expect(ouvrirBackoffice).not.toHaveBeenCalled()
  })

  it('affiche un message clair pour un mauvais mot de passe', async () => {
    simulerApi({
      'POST /api/connexion': { status: 401, body: { error: 'Identifiants invalides.' } },
    })
    afficher('/connexion')

    await seConnecter('lea@shortcut.test', 'pas-le-bon')

    expect(await screen.findByRole('alert')).toHaveTextContent('Identifiants invalides.')
  })

  it("n'appelle pas l'API si le formulaire est incomplet", async () => {
    const api = simulerApi({})
    afficher('/connexion')
    await screen.findByRole('heading', { name: 'Connexion' })

    await userEvent.setup().click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByText('Saisissez une adresse e-mail valide.')).toBeInTheDocument()
    expect(api.envoisA('POST /api/connexion')).toEqual([])
  })
})

describe('inscription', () => {
  async function sInscrire() {
    await screen.findByRole('heading', { name: 'Créer un compte' })
    const user = userEvent.setup()
    await user.type(screen.getByLabelText('Prénom'), 'Camille')
    await user.type(screen.getByLabelText('Nom'), 'Durand')
    await user.type(screen.getByLabelText('E-mail'), 'camille@example.com')
    await user.type(screen.getByLabelText('Mot de passe'), 'motdepasse-solide')
    await user.click(screen.getByRole('button', { name: 'Créer mon compte' }))
  }

  it('crée un compte client et ouvre le profil', async () => {
    const api = simulerApi({ 'POST /api/inscription': { status: 201, body: CAMILLE } })
    afficher('/inscription')

    await sInscrire()

    expect(await screen.findByRole('heading', { name: 'Mon profil' })).toBeInTheDocument()
    expect(screen.getByText(/Compte client/)).toBeInTheDocument()
    expect(api.envoisA('POST /api/inscription')).toEqual([
      {
        prenom: 'Camille',
        nom: 'Durand',
        email: 'camille@example.com',
        motDePasse: 'motdepasse-solide',
      },
    ])
  })

  it("affiche sous le champ l'erreur renvoyée par l'API", async () => {
    simulerApi({
      'POST /api/inscription': {
        status: 422,
        body: { erreurs: { email: 'Un compte existe déjà avec cet e-mail.' } },
      },
    })
    afficher('/inscription')

    await sInscrire()

    expect(await screen.findByText('Un compte existe déjà avec cet e-mail.')).toBeInTheDocument()
    expect(screen.getByLabelText('E-mail')).toHaveAttribute('aria-invalid', 'true')
  })
})

describe('profil', () => {
  it('renvoie vers la connexion sans session', async () => {
    simulerApi({})
    afficher('/profil')

    expect(await screen.findByRole('heading', { name: 'Connexion' })).toBeInTheDocument()
  })

  it('enregistre les modifications', async () => {
    const api = simulerApi({
      'GET /api/moi': { status: 200, body: CAMILLE },
      'PATCH /api/moi': { status: 200, body: { ...CAMILLE, telephone: '06 12 34 56 78' } },
    })
    afficher('/profil')
    await screen.findByRole('heading', { name: 'Mon profil' })

    const user = userEvent.setup()
    await user.type(screen.getByLabelText('Téléphone'), '06 12 34 56 78')
    await user.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByRole('status')).toHaveTextContent('Profil mis à jour.')
    expect(screen.getByLabelText('Téléphone')).toHaveValue('06 12 34 56 78')
    expect(api.envoisA('PATCH /api/moi')).toEqual([
      {
        prenom: 'Camille',
        nom: 'Durand',
        email: 'camille@example.com',
        telephone: '06 12 34 56 78',
        motDePasseActuel: '',
        nouveauMotDePasse: '',
      },
    ])
  })

  it('affiche les erreurs de mot de passe renvoyées par l’API', async () => {
    simulerApi({
      'GET /api/moi': { status: 200, body: CAMILLE },
      'PATCH /api/moi': {
        status: 422,
        body: { erreurs: { motDePasseActuel: 'Mot de passe actuel incorrect.' } },
      },
    })
    afficher('/profil')
    await screen.findByRole('heading', { name: 'Mon profil' })

    const user = userEvent.setup()
    await user.type(screen.getByLabelText('Nouveau mot de passe'), 'encore-plus-solide')
    await user.type(screen.getByLabelText('Mot de passe actuel'), 'pas-le-bon')
    await user.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText('Mot de passe actuel incorrect.')).toBeInTheDocument()
  })
})

describe('déconnexion', () => {
  it("ferme la session depuis l'en-tête", async () => {
    const api = simulerApi({
      'GET /api/moi': { status: 200, body: CAMILLE },
      'POST /api/deconnexion': { status: 204 },
    })
    afficher('/')

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Déconnexion' }))

    expect(await screen.findByRole('link', { name: 'Créer un compte' })).toBeInTheDocument()
    expect(api.fetch).toHaveBeenCalledWith(
      '/api/deconnexion',
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('montre le lien du backoffice aux conseillers seulement', async () => {
    simulerApi({ 'GET /api/moi': { status: 200, body: LEA } })
    afficher('/')

    expect(await screen.findByRole('link', { name: 'Backoffice' })).toHaveAttribute(
      'href',
      '/admin',
    )
  })
})
