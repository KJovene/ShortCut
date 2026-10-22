import { useSyncExternalStore } from 'react'
import type { TypeDeVoyage } from '@/data/catalogue'
import { formatDuree, formatEuros, labelType } from '@/data/catalogue'

/**
 * Langue du site : français par défaut, anglais quand le visiteur choisit « EN » dans l'en-tête. Le choix est
 * gardé dans le navigateur. Le chatbot démarre dans cette langue, puis suit celle dans laquelle le client écrit,
 * reconnue par l'agent Conversation (US15) ; la page « Ma proposition » garde la langue de la conversation.
 */
export type Langue = 'fr' | 'en'

/** Locale des dates et des nombres. */
export const LOCALE: Record<Langue, string> = { fr: 'fr-FR', en: 'en-GB' }

const CLE = 'langue'
const abonnes = new Set<() => void>()

function lire(): Langue {
  try {
    return localStorage.getItem(CLE) === 'en' ? 'en' : 'fr'
  } catch {
    return 'fr'
  }
}

let courante: Langue = lire()
document.documentElement.lang = courante

/** Langue choisie, pour le code hors composants (loaders des routes, requêtes). */
export const langueDuSite = (): Langue => courante

export function changerLangue(langue: Langue) {
  if (langue === courante) return
  courante = langue
  document.documentElement.lang = langue
  try {
    localStorage.setItem(CLE, langue)
  } catch {
    // Sans stockage, le choix dure le temps de la visite.
  }
  abonnes.forEach((abonne) => abonne())
}

/** Appelle `abonne` à chaque changement de langue ; renvoie de quoi se désabonner. */
export function surChangementDeLangue(abonne: () => void) {
  abonnes.add(abonne)
  return () => {
    abonnes.delete(abonne)
  }
}

/** Langue du site, mise à jour quand le visiteur la change. */
export const useLangue = () => useSyncExternalStore(surChangementDeLangue, langueDuSite)

const TYPES_EN: Record<TypeDeVoyage, string> = {
  sejour: 'Stay',
  circuit: 'Tour',
  groupe: 'Group',
  noces: 'Honeymoon',
  entreprise: 'Corporate',
}

/** « Voyage de noces », « Honeymoon ». */
export const typeDeVoyage = (type: TypeDeVoyage, langue: Langue) =>
  langue === 'en' ? TYPES_EN[type] : labelType(type)

const TYPES_PLURIEL_EN: Record<TypeDeVoyage, string> = {
  sejour: 'Stays',
  circuit: 'Tours',
  groupe: 'Groups',
  noces: 'Honeymoons',
  entreprise: 'Corporate',
}

/** « Séjours », « Stays » : filtres et titres des types de voyage. */
export const typesDeVoyage = (
  type: { valeur: TypeDeVoyage; pluriel: string },
  langue: Langue,
): string => (langue === 'en' ? TYPES_PLURIEL_EN[type.valeur] : type.pluriel)

/** « juin », « June » (mois de 0 à 11). */
export const nomDuMois = (mois: number, langue: Langue) =>
  new Intl.DateTimeFormat(LOCALE[langue], { month: 'long' }).format(new Date(2027, mois, 1))

/** « 1 250 € », « €1,250 ». */
export const euros = (montant: number, langue: Langue) =>
  langue === 'en'
    ? new Intl.NumberFormat(LOCALE.en, {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
      }).format(montant)
    : formatEuros(montant)

/** « 2 h 05 », « 2h 05 ». */
export const duree = (minutes: number, langue: Langue) =>
  langue === 'en' ? formatDuree(minutes).replace(' h', 'h') : formatDuree(minutes)

/**
 * Messages d'erreur rédigés en français par l'API (formulaires de compte, pare-feu) : traduits pour un visiteur
 * qui a choisi l'anglais. Un message inconnu reste tel quel.
 */
const MESSAGES_API_EN: Record<string, string> = {
  'Identifiants invalides.': 'Invalid email or password.',
  'Saisissez votre e-mail et votre mot de passe.': 'Enter your email and password.',
  'Un compte existe déjà avec cet e-mail.': 'An account already exists with this email.',
  'Saisissez votre e-mail.': 'Enter your email.',
  'Saisissez une adresse e-mail valide.': 'Enter a valid email address.',
  'Saisissez votre nom.': 'Enter your last name.',
  'Le mot de passe doit faire au moins 8 caractères.':
    'Your password must be at least 8 characters long.',
  "Saisissez votre mot de passe actuel pour changer d'e-mail ou de mot de passe.":
    'Enter your current password to change your email or password.',
  'Mot de passe actuel incorrect.': 'Your current password is incorrect.',
  'Certains champs sont à corriger.': 'Some fields need correcting.',
  'Une erreur est survenue, réessayez dans un instant.':
    'Something went wrong, please try again in a moment.',
}

export const messageApi = (message: string | undefined, langue: Langue) =>
  message && langue === 'en' ? (MESSAGES_API_EN[message] ?? message) : message
