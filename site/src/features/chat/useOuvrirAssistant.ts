import { useLocation } from '@tanstack/react-router'
import { useCallback } from 'react'
import { useChatActions } from './chatContext'

export const ID_SECTION_ASSISTANT = 'assistant'

/**
 * Ouvre l'assistant : sur l'accueil, fait défiler jusqu'à sa section ; ailleurs, ouvre sa fenêtre.
 * Le brouillon éventuel pré-remplit le champ de saisie. Avec `nouveauVoyage`, une conversation déjà engagée
 * (voyage en cours ou modifié) laisse place à une discussion vierge : le client construit un autre voyage.
 * La `destination` choisie sur la carte pré-remplit le lieu de la demande côté API, quelle que soit la langue.
 */
export function useOuvrirAssistant() {
  // Les actions seules : les nombreux boutons qui ouvrent l'assistant ne se redessinent pas pendant qu'il écrit.
  const {
    nouveauVoyage: repartirAZero,
    ouvrir,
    setBrouillon,
    setDestinationInvite,
  } = useChatActions()
  const estAccueil = useLocation({ select: (l) => l.pathname === '/' })

  return useCallback(
    (
      brouillon?: string,
      {
        nouveauVoyage = false,
        destination,
      }: { nouveauVoyage?: boolean; destination?: string } = {},
    ) => {
      if (nouveauVoyage) repartirAZero()
      if (!estAccueil) {
        ouvrir(brouillon)
        return
      }
      if (brouillon !== undefined) setBrouillon(brouillon)
      if (destination) setDestinationInvite(destination)
      const section = document.getElementById(ID_SECTION_ASSISTANT)
      section?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      window.setTimeout(() => section?.querySelector<HTMLElement>('textarea, input')?.focus(), 400)
    },
    [estAccueil, repartirAZero, ouvrir, setBrouillon, setDestinationInvite],
  )
}
