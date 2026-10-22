import type { Demande } from './demande'

/** Messages affichés dans le chat. Les réponses de l'assistant arrivent de l'API (POST /api/chat). */
export type Message =
  | { id: string; auteur: 'client'; texte: string }
  | { id: string; auteur: 'assistant'; texte: string }
  /** Fin de la conversation : la demande est enregistrée et transmise à un conseiller. */
  | { id: string; auteur: 'assistant'; type: 'confirmation'; texte: string }

export interface Conversation {
  /** Fiche demande tenue à jour par l'assistant, lue par le récapitulatif. */
  demande: Demande
}

let suivant = 0
export const nouvelId = () => `m${Date.now().toString(36)}${(suivant++).toString(36)}`

export const MESSAGE_ACCUEIL: Message = {
  id: 'accueil',
  auteur: 'assistant',
  texte:
    'Bonjour, je suis l’assistant de Détour, une intelligence artificielle. Racontez-moi le voyage dont vous rêvez : la plage, les montagnes ou ce petit pas de côté auquel vous n’avez pas encore pensé.',
}
