import { vi } from 'vitest'

type Reponse = { status: number; body?: unknown }

/**
 * Remplace fetch par une API simulée : « POST /api/connexion » → réponse.
 * Sans réponse prévue, GET /api/moi répond 401 (personne n'est connecté) et le reste 404.
 */
export function simulerApi(routes: Record<string, Reponse | Reponse[]>) {
  const restantes = new Map(Object.entries(routes).map(([cle, r]) => [cle, [r].flat()]))

  const fetch = vi.fn(async (url: string, init?: RequestInit) => {
    const cle = `${init?.method ?? 'GET'} ${url}`
    const file = restantes.get(cle)
    // La dernière réponse prévue resservira pour les appels suivants.
    const reponse =
      (file && file.length > 1 ? file.shift() : file?.[0]) ??
      (cle === 'GET /api/moi'
        ? { status: 401, body: { erreur: 'Authentification requise.' } }
        : { status: 404, body: {} })

    return new Response(reponse.body === undefined ? null : JSON.stringify(reponse.body), {
      status: reponse.status,
    })
  })
  vi.stubGlobal('fetch', fetch)

  return {
    fetch,
    /** Corps JSON envoyés à une route. */
    envoisA: (cle: string) =>
      fetch.mock.calls
        .filter(([url, init]) => `${init?.method ?? 'GET'} ${url}` === cle)
        .map(([, init]) => JSON.parse(String(init?.body ?? 'null')) as unknown),
  }
}
