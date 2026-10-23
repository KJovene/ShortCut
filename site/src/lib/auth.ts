import { queryOptions } from '@tanstack/react-query'
import { z } from 'zod'
import { ApiError } from './api'

/** Backoffice EasyAdmin, servi par Symfony (proxifié par Vite en local). */
export const URL_BACKOFFICE = '/admin'

export const Profil = z.object({
  email: z.string(),
  prenom: z.string().nullable(),
  nom: z.string(),
  telephone: z.string().nullable(),
  role: z.enum(['ROLE_CLIENT', 'ROLE_CONSEILLER', 'ROLE_ADMIN']),
})
export type Profil = z.infer<typeof Profil>

/** Erreurs de saisie renvoyées par l'API (422), champ par champ. */
export class ErreurFormulaire extends ApiError {
  readonly erreurs: Record<string, string>

  constructor(erreurs: Record<string, string>) {
    super(422, 'Certains champs sont à corriger.')
    this.name = 'ErreurFormulaire'
    this.erreurs = erreurs
  }
}

const ErreursDeChamps = z.object({ erreurs: z.record(z.string(), z.string()) })
/** Toute autre erreur de l'API : un message à montrer tel quel (D28). */
const ErreurDeLApi = z.object({ erreur: z.string() })

/** Conseillers et administrateur ont accès au backoffice ; les clients restent sur le site. */
export function accedeAuBackoffice(profil: Profil) {
  return profil.role !== 'ROLE_CLIENT'
}

async function envoyer(path: string, method: string, corps?: unknown): Promise<Response> {
  const response = await fetch(`/api${path}`, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: corps === undefined ? undefined : JSON.stringify(corps),
  })

  if (response.ok) {
    return response
  }

  const json: unknown = await response.json().catch(() => null)
  const champs = ErreursDeChamps.safeParse(json)
  if (response.status === 422 && champs.success) {
    throw new ErreurFormulaire(champs.data.erreurs)
  }
  // Mauvais identifiants (« Identifiants invalides. »), trop de tentatives, mot de passe actuel erroné…
  const erreur = ErreurDeLApi.safeParse(json)
  if (response.status < 500 && erreur.success) {
    throw new ApiError(response.status, erreur.data.erreur)
  }
  throw new ApiError(response.status, 'Une erreur est survenue, réessayez dans un instant.')
}

/** Utilisateur de la session, ou null si personne n'est connecté. */
export async function chargerProfil(): Promise<Profil | null> {
  const response = await fetch('/api/moi', { headers: { Accept: 'application/json' } })
  if (response.status === 401) {
    return null
  }
  if (!response.ok) {
    throw new ApiError(response.status, 'Impossible de vérifier la session.')
  }
  return Profil.parse(await response.json())
}

export const profilQuery = queryOptions({
  queryKey: ['profil'],
  queryFn: chargerProfil,
  retry: false,
})

export async function seConnecter(email: string, motDePasse: string): Promise<Profil> {
  const response = await envoyer('/connexion', 'POST', { email, motDePasse })
  return Profil.parse(await response.json())
}

export interface Inscription {
  prenom: string
  nom: string
  email: string
  motDePasse: string
}

/** Crée un compte client et ouvre sa session. */
export async function sInscrire(inscription: Inscription): Promise<Profil> {
  const response = await envoyer('/inscription', 'POST', inscription)
  return Profil.parse(await response.json())
}

export interface ModificationProfil {
  prenom: string
  nom: string
  email: string
  telephone: string
  /** Exigé pour changer d'e-mail ou de mot de passe. */
  motDePasseActuel: string
  /** Vide : le mot de passe ne change pas. */
  nouveauMotDePasse: string
}

export async function modifierProfil(modification: ModificationProfil): Promise<Profil> {
  const response = await envoyer('/moi', 'PATCH', modification)
  return Profil.parse(await response.json())
}

export async function seDeconnecter(): Promise<void> {
  await envoyer('/deconnexion', 'POST')
}

/** Navigation pleine page (le backoffice n'est pas une route React). */
export function ouvrirBackoffice() {
  window.location.assign(URL_BACKOFFICE)
}
