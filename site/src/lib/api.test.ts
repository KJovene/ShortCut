import { afterEach, describe, expect, it, vi } from 'vitest'
import { z } from 'zod'
import { ApiError, apiFetch } from './api'

const Destination = z.object({ nom: z.string() })

function mockFetch(status: number, body: unknown) {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status })))
}

describe('apiFetch', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renvoie la réponse validée par le schéma', async () => {
    mockFetch(200, { nom: 'Lisbonne' })
    await expect(apiFetch('/destinations/1', Destination)).resolves.toEqual({ nom: 'Lisbonne' })
  })

  it('rejette une réponse qui ne respecte pas le schéma', async () => {
    mockFetch(200, { nom: 42 })
    await expect(apiFetch('/destinations/1', Destination)).rejects.toThrow(z.ZodError)
  })

  it("lève une ApiError quand l'API répond en erreur", async () => {
    mockFetch(404, {})
    await expect(apiFetch('/destinations/1', Destination)).rejects.toBeInstanceOf(ApiError)
  })
})
