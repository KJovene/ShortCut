import { z } from 'zod'

export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

/** Erreurs de saisie renvoyées par l'API (422), champ par champ. */
export class ErreurFormulaire extends ApiError {
  readonly erreurs: Record<string, string>

  constructor(erreurs: Record<string, string>) {
    super(422, 'Certains champs sont à corriger.')
    this.name = 'ErreurFormulaire'
    this.erreurs = erreurs
  }
}

const MESSAGE_PAR_DEFAUT = 'Une erreur est survenue, réessayez dans un instant.'

/** Les deux formes d'erreur de l'API (D28) : un message déjà rédigé pour le client, ou des erreurs par champ. */
const CorpsErreur = z.object({ erreur: z.string() })
const CorpsErreurs = z.object({ erreurs: z.record(z.string(), z.string()) })

/**
 * L'erreur d'une réponse en échec : ErreurFormulaire pour un 422 champ par champ, sinon le message de l'API
 * (« erreur »). Une panne du serveur (5xx) ou une réponse sans message donne `messageParDefaut`.
 */
export async function lireErreur(
  response: Response,
  messageParDefaut = MESSAGE_PAR_DEFAUT,
): Promise<ApiError> {
  const corps: unknown = await response.json().catch(() => null)
  const champs = CorpsErreurs.safeParse(corps)
  if (response.status === 422 && champs.success) {
    return new ErreurFormulaire(champs.data.erreurs)
  }
  const erreur = CorpsErreur.safeParse(corps)
  return new ApiError(
    response.status,
    response.status < 500 && erreur.success ? erreur.data.erreur : messageParDefaut,
  )
}

export interface OptionsRequete {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE'
  /** Envoyé en JSON. */
  corps?: unknown
  headers?: Record<string, string>
  signal?: AbortSignal
  /** Message montré quand l'API n'en donne pas. */
  messageErreur?: string
}

/**
 * Appelle l'API Symfony (proxifiée par Vite sous /api) en JSON. Une réponse en échec lève l'erreur de lireErreur.
 */
export async function appelerApi(chemin: string, options: OptionsRequete = {}): Promise<Response> {
  const response = await fetch(`/api${chemin}`, {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/json',
      ...(options.corps === undefined ? {} : { 'Content-Type': 'application/json' }),
      ...options.headers,
    },
    body: options.corps === undefined ? undefined : JSON.stringify(options.corps),
    signal: options.signal,
  })
  if (!response.ok) {
    throw await lireErreur(response, options.messageErreur)
  }
  return response
}

/**
 * Comme appelerApi, puis valide la réponse avec Zod : une réponse qui ne respecte pas le schéma lève une erreur
 * au lieu de se propager dans l'interface.
 */
export async function requete<T extends z.ZodType>(
  chemin: string,
  schema: T,
  options?: OptionsRequete,
): Promise<z.infer<T>> {
  return schema.parse(await (await appelerApi(chemin, options)).json())
}

/** La ressource, ou null quand l'API répond avec ce statut (404 : introuvable, 401 : pas de session). */
export async function nullSi<T>(status: number, appel: Promise<T>): Promise<T | null> {
  try {
    return await appel
  } catch (erreur) {
    if (erreur instanceof ApiError && erreur.status === status) return null
    throw erreur
  }
}
