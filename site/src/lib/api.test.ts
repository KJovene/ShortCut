import { afterEach, describe, expect, it, vi } from 'vitest'
import { z } from 'zod'
import { ApiError, ErreurFormulaire, nullSi, requete } from './api'

const Destination = z.object({ nom: z.string() })

function mockFetch(status: number, body: unknown) {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status })))
}

describe('requete', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renvoie la réponse validée par le schéma', async () => {
    mockFetch(200, { nom: 'Lisbonne' })
    await expect(requete('/destinations/1', Destination)).resolves.toEqual({ nom: 'Lisbonne' })
  })

  it('rejette une réponse qui ne respecte pas le schéma', async () => {
    mockFetch(200, { nom: 42 })
    await expect(requete('/destinations/1', Destination)).rejects.toThrow(z.ZodError)
  })

  it('reprend le message de l’API, sinon le message prévu', async () => {
    mockFetch(409, { erreur: 'Ce voyage est réservé.' })
    await expect(requete('/moi/demandes/1', Destination)).rejects.toThrow('Ce voyage est réservé.')

    mockFetch(500, { erreur: 'SQLSTATE[42P01]' })
    await expect(
      requete('/moi/demandes/1', Destination, {
        messageErreur: 'Impossible de charger vos voyages.',
      }),
    ).rejects.toThrow('Impossible de charger vos voyages.')
  })

  it('rend les erreurs de saisie champ par champ', async () => {
    mockFetch(422, { erreurs: { email: 'Un compte existe déjà avec cet e-mail.' } })
    const erreur = await requete('/inscription', Destination, { method: 'POST', corps: {} }).catch(
      (e: unknown) => e,
    )
    expect(erreur).toBeInstanceOf(ErreurFormulaire)
    expect((erreur as ErreurFormulaire).erreurs).toEqual({
      email: 'Un compte existe déjà avec cet e-mail.',
    })
  })

  it('donne null pour le statut attendu, une ApiError pour les autres', async () => {
    mockFetch(404, {})
    await expect(nullSi(404, requete('/destinations/1', Destination))).resolves.toBeNull()
    mockFetch(403, {})
    await expect(nullSi(404, requete('/destinations/1', Destination))).rejects.toBeInstanceOf(
      ApiError,
    )
  })
})
