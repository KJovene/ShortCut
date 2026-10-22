import { useLocation } from '@tanstack/react-router'
import { useCallback } from 'react'
import { useChat } from './chatContext'

export const ID_SECTION_ASSISTANT = 'assistant'

/**
 * Ouvre l'assistant : sur l'accueil, fait défiler jusqu'à sa section ; ailleurs, ouvre sa fenêtre.
 * Le brouillon éventuel pré-remplit le champ de saisie. Avec `nouveauVoyage`, une conversation déjà engagée
 * (voyage en cours ou modifié) laisse place à une discussion vierge : le client construit un autre voyage.
 */
export function useOuvrirAssistant() {
  const { statut, ouvrir, recommencer, setBrouillon, setDestinationInvite } = useChat()
  const estAccueil = useLocation({ select: (l) => l.pathname === '/' })

  return useCallback(
    (brouillon?: string, { nouveauVoyage = false }: { nouveauVoyage?: boolean } = {}) => {
      if (nouveauVoyage && statut === 'conversation') recommencer()
      if (!estAccueil) {
        ouvrir(brouillon)
        return
      }
      if (brouillon !== undefined) {
        setBrouillon(brouillon)
        const destination = /^Un voyage à (.+?) en .+?, $/.exec(brouillon)?.[1]
        if (destination) setDestinationInvite(destination)
      }
      const section = document.getElementById(ID_SECTION_ASSISTANT)
      section?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      window.setTimeout(() => section?.querySelector<HTMLElement>('textarea, input')?.focus(), 400)
    },
    [estAccueil, statut, ouvrir, recommencer, setBrouillon, setDestinationInvite],
  )
}
