import { createContext, useContext } from 'react'
import type { Conversation, Message } from './assistant'

export interface EtatChat {
  /** « accueil » tant que le client n'a rien envoyé, puis « conversation ». */
  statut: 'accueil' | 'conversation'
  messages: Message[]
  conversation: Conversation
  /** L'assistant est en train d'écrire. */
  reflexion: boolean
  propositionId?: string
  /** Jeton de la conversation côté API, obtenu au premier message. */
  jeton?: string
  /** Le client a confirmé son récapitulatif : la demande est transmise, la saisie est fermée. */
  terminee?: boolean
  /** Fenêtre du chatbot ouverte (pages autres que l'accueil). */
  ouvert: boolean
  brouillon: string
}

export interface ActionsChat {
  envoyer: (texte: string) => void
  choisirAmbiance: (slug: string) => void
  ouvrir: (brouillon?: string) => void
  fermer: () => void
  setBrouillon: (texte: string) => void
  recommencer: () => void
}

export const ChatContext = createContext<(EtatChat & ActionsChat) | null>(null)

export function useChat() {
  const chat = useContext(ChatContext)
  if (!chat) throw new Error('useChat doit être utilisé sous <ChatProvider>')
  return chat
}
