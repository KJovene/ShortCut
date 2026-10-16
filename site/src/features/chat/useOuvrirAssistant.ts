import { useLocation } from '@tanstack/react-router'
import { useCallback } from 'react'
import { useChat } from './chatContext'

export const ID_SECTION_ASSISTANT = 'assistant'

/**
 * Ouvre l'assistant : sur l'accueil, fait défiler jusqu'à sa section ; ailleurs, ouvre sa fenêtre.
 * Le brouillon éventuel pré-remplit le champ de saisie.
 */
export function useOuvrirAssistant() {
  const { ouvrir, setBrouillon } = useChat()
  const estAccueil = useLocation({ select: (l) => l.pathname === '/' })

  return useCallback(
    (brouillon?: string) => {
      if (!estAccueil) {
        ouvrir(brouillon)
        return
      }
      if (brouillon !== undefined) setBrouillon(brouillon)
      const section = document.getElementById(ID_SECTION_ASSISTANT)
      section?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      window.setTimeout(() => section?.querySelector<HTMLElement>('textarea, input')?.focus(), 400)
    },
    [estAccueil, ouvrir, setBrouillon],
  )
}
