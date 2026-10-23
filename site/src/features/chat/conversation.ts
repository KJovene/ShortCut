import type { Piste, RecapitulatifChat, Suggestion } from './api'
import type { Demande } from './demande'

/** Messages affichés dans le chat. Les réponses de l'assistant arrivent de l'API (POST /api/chat). */
export type Message =
  | { id: string; auteur: 'client'; texte: string }
  | { id: string; auteur: 'assistant'; texte: string }
  /** Fin de la conversation : la demande est enregistrée et transmise à un conseiller. */
  | { id: string; auteur: 'assistant'; type: 'confirmation'; texte: string }
  /** Piste retenue par la recherche, en carte : niveaux, totaux, options. */
  | { id: string; auteur: 'assistant'; type: 'piste'; texte: string; piste: Piste }
  /** Logements et activités recommandés, en cartes cliquables sous le texte de l'assistant. */
  | {
      id: string
      auteur: 'assistant'
      type: 'suggestions'
      texte: string
      suggestions: Suggestion[]
    }
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
