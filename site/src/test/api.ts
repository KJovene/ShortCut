import { vi } from 'vitest'
import { DESTINATIONS_DE_DEMO } from './destinations'
import { PAGES_DE_DEMO } from './pages'

type Reponse = { status: number; body?: unknown }

/**
 * Remplace fetch par une API simulée : « POST /api/connexion » → réponse.
 * Sans réponse prévue, GET /api/moi répond 401 (personne n'est connecté), GET /api/pages/{slug} et
 * GET /api/public/destinations(/{slug}) servent les données de démo et le reste répond 404.
 */
export function simulerApi(routes: Record<string, Reponse | Reponse[]>) {
  const restantes = new Map(Object.entries(routes).map(([cle, r]) => [cle, [r].flat()]))

  const fetch = vi.fn(async (url: string, init?: RequestInit) => {
    const cle = `${init?.method ?? 'GET'} ${url}`
    const file = restantes.get(cle)
    // La dernière réponse prévue resservira pour les appels suivants.
    const reponse = (file && file.length > 1 ? file.shift() : file?.[0]) ?? reponseParDefaut(cle)

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

function reponseParDefaut(cle: string): Reponse {
  if (cle === 'GET /api/moi') {
    return { status: 401, body: { erreur: 'Authentification requise.' } }
  }
  const page = /^GET \/api\/pages\/(.+)$/.exec(cle)?.[1]
  if (page && PAGES_DE_DEMO[page]) {
    return { status: 200, body: PAGES_DE_DEMO[page] }
  }
  if (cle === 'GET /api/public/destinations') {
    return { status: 200, body: DESTINATIONS_DE_DEMO }
  }
  if (cle === 'GET /api/public/destinations?miseEnAvant=true') {
    return { status: 200, body: DESTINATIONS_DE_DEMO.filter((d) => d.miseEnAvant) }
  }
  const slug = /^GET \/api\/public\/destinations\/(.+)$/.exec(cle)?.[1]
  const destination = DESTINATIONS_DE_DEMO.find((d) => d.slug === slug)
  if (destination) {
    return { status: 200, body: destination }
  }
  return { status: 404, body: {} }
}
