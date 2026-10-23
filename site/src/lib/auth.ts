import { queryOptions } from '@tanstack/react-query'
import { z } from 'zod'
import { appelerApi, nullSi, requete } from './api'

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

export { ErreurFormulaire } from './api'

/** Conseillers et administrateur ont accès au backoffice ; les clients restent sur le site. */
export function accedeAuBackoffice(profil: Profil) {
  return profil.role !== 'ROLE_CLIENT'
}

/** Utilisateur de la session, ou null si personne n'est connecté. */
export async function chargerProfil(): Promise<Profil | null> {
  return nullSi(
    401,
    requete('/moi', Profil, { messageErreur: 'Impossible de vérifier la session.' }),
  )
}

export const profilQuery = queryOptions({
  queryKey: ['profil'],
  queryFn: chargerProfil,
  retry: false,
})

export async function seConnecter(email: string, motDePasse: string): Promise<Profil> {
  return requete('/connexion', Profil, { method: 'POST', corps: { email, motDePasse } })
}

export interface Inscription {
  prenom: string
  nom: string
  email: string
  motDePasse: string
}

/** Crée un compte client et ouvre sa session. */
export async function sInscrire(inscription: Inscription): Promise<Profil> {
  return requete('/inscription', Profil, { method: 'POST', corps: inscription })
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
  return requete('/moi', Profil, { method: 'PATCH', corps: modification })
}

export async function seDeconnecter(): Promise<void> {
  await appelerApi('/deconnexion', { method: 'POST' })
}

/** Navigation pleine page (le backoffice n'est pas une route React). */
export function ouvrirBackoffice() {
  window.location.assign(URL_BACKOFFICE)
}
