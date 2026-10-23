import { vi } from 'vitest'
import { FRAIS_DE_DOSSIER } from './catalogue'
import { DESTINATIONS_DE_DEMO } from './destinations'
import { MENU_DE_DEMO, PAGES_DE_DEMO } from './pages'

type Reponse = { status: number; body?: unknown }

/**
 * Remplace fetch par une API simulée : « POST /api/connexion » → réponse. Un corps texte est servi tel quel (flux du
 * chat), un autre corps en JSON.
 * Sans réponse prévue, GET /api/moi répond 401 (personne n'est connecté), GET /api/pages(/{slug}) et
 * GET /api/public/destinations(/{slug}) et GET /api/public/frais-de-dossier servent les données de démo et le reste
 * répond 404.
 */
export function simulerApi(routes: Record<string, Reponse | Reponse[]>) {
  const restantes = new Map(Object.entries(routes).map(([cle, r]) => [cle, [r].flat()]))

  const fetch = vi.fn(async (url: string, init?: RequestInit) => {
    const cle = `${init?.method ?? 'GET'} ${url}`
    const file = restantes.get(cle)
    // La dernière réponse prévue resservira pour les appels suivants.
    const reponse = (file && file.length > 1 ? file.shift() : file?.[0]) ?? reponseParDefaut(cle)

    // Un corps texte est servi tel quel : le flux Server-Sent Events du chat.
    const corps =
      reponse.body === undefined
        ? null
        : typeof reponse.body === 'string'
          ? reponse.body
          : JSON.stringify(reponse.body)
    return new Response(corps, { status: reponse.status })
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
  if (cle === 'GET /api/pages') {
    return { status: 200, body: MENU_DE_DEMO }
  }
  const page = /^GET \/api\/pages\/(.+)$/.exec(cle)?.[1]
  if (page && PAGES_DE_DEMO[page]) {
    return { status: 200, body: PAGES_DE_DEMO[page] }
  }
  if (cle === 'GET /api/public/frais-de-dossier') {
    return {
      status: 200,
      body: Object.fromEntries(
        Object.entries(FRAIS_DE_DOSSIER).map(([type, euros]) => [type, euros * 100]),
      ),
    }
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
