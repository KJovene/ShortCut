import type { TypeDeVoyage } from '@/data/catalogue'
import { labelType } from '@/data/catalogue'

/**
 * Langue du client (US15) : celle dans laquelle il dialogue avec le chatbot, reconnue par l'agent Conversation.
 * Le chatbot et la page « Ma proposition » lui parlent dans cette langue ; le reste du site reste en français.
 */
export type Langue = 'fr' | 'en'

/** Locale des dates et des nombres. */
export const LOCALE: Record<Langue, string> = { fr: 'fr-FR', en: 'en-GB' }

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

/** « juin », « June » (mois de 0 à 11). */
export const nomDuMois = (mois: number, langue: Langue) =>
  new Intl.DateTimeFormat(LOCALE[langue], { month: 'long' }).format(new Date(2027, mois, 1))
