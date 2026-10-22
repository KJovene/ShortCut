import type { Langue } from '@/lib/langue'
import type { Piste, RecapitulatifChat } from './api'
import type { Demande } from './demande'

/** Messages affichés dans le chat. Les réponses de l'assistant arrivent de l'API (POST /api/chat). */
export type Message =
  | { id: string; auteur: 'client'; texte: string }
  | { id: string; auteur: 'assistant'; texte: string }
  /** Fin de la conversation : la demande est enregistrée et transmise à un conseiller. */
  | { id: string; auteur: 'assistant'; type: 'confirmation'; texte: string }
  /** Piste retenue par la recherche, en carte : niveaux, totaux, options. */
  | { id: string; auteur: 'assistant'; type: 'piste'; texte: string; piste: Piste }
  /** Récapitulatif détaillé enregistré : son lien, et les options à ajouter d'un clic. */
  | {
      id: string
      auteur: 'assistant'
      type: 'recapitulatif'
      texte: string
      recapitulatif: RecapitulatifChat
    }

export interface Conversation {
  /** Fiche demande tenue à jour par l'assistant, lue par le récapitulatif. */
  demande: Demande
}

let suivant = 0
export const nouvelId = () => `m${Date.now().toString(36)}${(suivant++).toString(36)}`

/** Premier message de l'assistant, dans la langue du site. */
export const messageAccueil = (langue: Langue): Message => ({
  id: 'accueil',
  auteur: 'assistant',
  texte:
    langue === 'en'
      ? 'Hello, I am Détour’s assistant, an artificial intelligence. Tell me about the trip you are dreaming of: beaches, mountains or that little detour you have not thought of yet. (Vous pouvez aussi m’écrire en français.)'
      : // Un client étranger sait dès l'accueil qu'il peut écrire en anglais (US15).
        'Bonjour, je suis l’assistant de Détour, une intelligence artificielle. Racontez-moi le voyage dont vous rêvez : la plage, les montagnes ou ce petit pas de côté auquel vous n’avez pas encore pensé. (Hello! You can also write to me in English.)',
})

export const MESSAGE_ACCUEIL: Message = messageAccueil('fr')
