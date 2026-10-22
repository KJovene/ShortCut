import { createContext, useContext } from 'react'
import type { Langue } from '@/lib/langue'
import type { Fiche } from './api'
import type { Conversation, Message } from './conversation'

export interface EtatChat {
  /** « accueil » tant que le client n'a rien envoyé, puis « conversation ». */
  statut: 'accueil' | 'conversation'
  messages: Message[]
  conversation: Conversation
  /** L'assistant est en train d'écrire. */
  reflexion: boolean
  /** Jeton de la conversation côté API, obtenu au premier message. */
  jeton?: string
  /** Le client a confirmé son récapitulatif : la demande est transmise, la saisie est fermée. */
  terminee?: boolean
  /** Fenêtre du chatbot ouverte (pages autres que l'accueil). */
  ouvert: boolean
  brouillon: string
  /** Langue du client, reconnue par l'assistant (US15) : celle des textes du chatbot. */
  langue: Langue
  questionnaire: QuestionnaireEtat
  /** Réponses du visiteur à transmettre au premier message après connexion. */
  contexteInvite?: string
}

export interface QuestionnaireEtat {
  reponses: string[]
  termine: boolean
  transmis: boolean
}

/** Conversation déjà commencée, reprise depuis l'espace client (« Modifier le voyage »). */
export interface Reprise {
  jeton: string
  messages: { role: string; contenu: string }[]
  fiche: Fiche
}

export interface ActionsChat {
  envoyer: (texte: string) => void
  ouvrir: (brouillon?: string) => void
  fermer: () => void
  setBrouillon: (texte: string) => void
  recommencer: () => void
  /** Recharge une conversation existante et ouvre le chat pour la continuer. */
  reprendre: (reprise: Reprise) => void
  repondreQuestion: (reponse: string) => void
}

export const ChatContext = createContext<(EtatChat & ActionsChat & { estConnecte: boolean }) | null>(
  null,
)

export function useChat() {
  const chat = useContext(ChatContext)
  if (!chat) throw new Error('useChat doit être utilisé sous <ChatProvider>')
  return chat
}
