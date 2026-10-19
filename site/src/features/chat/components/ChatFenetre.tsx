import { useLocation } from '@tanstack/react-router'
import { useEffect, useRef, useState } from 'react'
import { Logo } from '@/components/layout/Logo'
import { IconBulle, IconFermer, IconPersonne } from '@/components/ui/icons'
import { useChat } from '../chatContext'
import { ID_SECTION_ASSISTANT, useOuvrirAssistant } from '../useOuvrirAssistant'
import { ChatAccueil } from './ChatAccueil'
import { ChatWorkspace } from './ChatWorkspace'

/** Fenêtre plein écran du chatbot, ouverte depuis toutes les pages sauf l'accueil. */
export function ChatFenetre() {
  const { ouvert, fermer, statut, envoyer } = useChat()
  const estAccueil = useLocation({ select: (l) => l.pathname === '/' })
  const fermerBouton = useRef<HTMLButtonElement>(null)
  const visible = ouvert && !estAccueil

  useEffect(() => {
    if (!visible) return
    const precedent = document.activeElement as HTMLElement | null
    fermerBouton.current?.focus()
    document.body.style.overflow = 'hidden'
    const surTouche = (e: KeyboardEvent) => {
      if (e.key === 'Escape') fermer()
    }
    document.addEventListener('keydown', surTouche)
    return () => {
      document.body.style.overflow = ''
      document.removeEventListener('keydown', surTouche)
      precedent?.focus()
    }
  }, [visible, fermer])

  if (!visible) return null

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label="Assistant voyage"
      className="no-print fixed inset-0 z-50 flex flex-col bg-creme"
    >
      <header className="border-b border-lin">
        <div className="mx-auto flex max-w-[1376px] items-center justify-between gap-3 px-4 py-2.5 sm:px-8 sm:py-3.5">
          <Logo className="hidden sm:inline" />
          <div className="flex min-w-0 items-center gap-3">
            <span
              aria-hidden="true"
              className="flex size-10 shrink-0 items-center justify-center rounded-full bg-terre text-white"
            >
              <IconBulle size={20} />
            </span>
            <span className="flex min-w-0 flex-col">
              <span className="text-[15px] font-extrabold">Votre assistant voyage</span>
              <span className="truncate text-xs text-taupe">
                Je suis une IA · un conseiller confirme chaque proposition
              </span>
            </span>
          </div>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => envoyer('Je voudrais parler à un conseiller')}
              className="hidden min-h-11 items-center gap-2 rounded-full border-[1.5px] border-encre px-[18px] text-sm font-bold hover:bg-sable md:flex"
            >
              <IconPersonne size={18} />
              Parler à un conseiller
            </button>
            <button
              ref={fermerBouton}
              type="button"
              onClick={fermer}
              aria-label="Fermer l'assistant"
              className="flex size-11 items-center justify-center rounded-full hover:bg-sable"
            >
              <IconFermer size={22} />
            </button>
          </div>
        </div>
      </header>
      <div className="mx-auto flex min-h-0 w-full max-w-[1376px] flex-1 flex-col overflow-y-auto px-4 sm:px-8">
        {statut === 'accueil' ? (
          <div className="flex flex-1 items-center justify-center py-10">
            <ChatAccueil compact />
          </div>
        ) : (
          <ChatWorkspace className="flex-1 py-4 lg:py-6" />
        )}
      </div>
    </div>
  )
}

/** Vrai tant que la section de l'assistant de l'accueil est visible à l'écran. */
function useSectionAssistantVisible(estAccueil: boolean) {
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    // Sans IntersectionObserver (tests, très vieux navigateurs), le bouton reste toujours affiché.
    if (!estAccueil || typeof IntersectionObserver === 'undefined') return
    let observateur: IntersectionObserver | undefined
    const brancher = () => {
      const section = document.getElementById(ID_SECTION_ASSISTANT)
      if (!section) return false
      observateur = new IntersectionObserver(([entree]) => setVisible(entree.isIntersecting), {
        threshold: 0.25,
      })
      observateur.observe(section)
      return true
    }
    // La page d'accueil est chargée à la demande : la section peut apparaître après ce composant.
    const attente = new MutationObserver(() => {
      if (brancher()) attente.disconnect()
    })
    if (!brancher()) attente.observe(document.body, { childList: true, subtree: true })
    return () => {
      attente.disconnect()
      observateur?.disconnect()
    }
  }, [estAccueil])

  // Hors de l'accueil, la section n'existe pas : la dernière valeur observée ne compte plus.
  return estAccueil && visible
}

/** Bouton flottant présent sur toutes les pages, masqué quand la section de l'assistant est déjà à l'écran. */
export function ChatLanceur() {
  const { ouvert, statut } = useChat()
  const ouvrirAssistant = useOuvrirAssistant()
  const estAccueil = useLocation({ select: (l) => l.pathname === '/' })
  const estProposition = useLocation({ select: (l) => l.pathname.startsWith('/proposition') })
  const sectionVisible = useSectionAssistantVisible(estAccueil)
  if ((ouvert && !estAccueil) || sectionVisible) return null

  return (
    <button
      type="button"
      onClick={() => ouvrirAssistant()}
      className="no-print fixed right-4 bottom-4 z-40 flex min-h-12 items-center gap-2.5 rounded-full bg-encre py-3 pr-5 pl-4 text-[15px] font-bold text-white shadow-[0_10px_30px_rgba(43,29,20,0.3)] hover:bg-brun sm:right-8 sm:bottom-8"
    >
      <IconBulle size={22} />
      {statut === 'conversation'
        ? 'Reprendre la discussion'
        : estProposition
          ? 'Modifier dans le chat'
          : "Parler à l'assistant"}
    </button>
  )
}
