import { z } from 'zod'

export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

/**
 * Appelle l'API Symfony (proxifiée par Vite sous /api) et valide la réponse avec Zod :
 * une réponse qui ne respecte pas le schéma lève une erreur au lieu de se propager dans l'UI.
 */
export async function apiFetch<T extends z.ZodType>(
  path: string,
  schema: T,
  init?: RequestInit,
): Promise<z.infer<T>> {
  const response = await fetch(`/api${path}`, {
    ...init,
    headers: { Accept: 'application/ld+json', ...init?.headers },
  })

  if (!response.ok) {
    throw new ApiError(response.status, `Requête ${path} en échec (${response.status})`)
  }

  return schema.parse(await response.json())
}
