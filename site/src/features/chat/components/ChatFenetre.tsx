import { useLocation } from '@tanstack/react-router'
import { useEffect, useRef, useState } from 'react'
import { Logo } from '@/components/layout/Logo'
import { ID_PIED_DE_PAGE } from '@/components/layout/SiteFooter'
import { IconBulle, IconFermer, IconPersonne } from '@/components/ui/icons'
import { useChat } from '../chatContext'
import { TEXTES } from '../textes'
import { useOuvrirAssistant } from '../useOuvrirAssistant'
import { ChatAccueil } from './ChatAccueil'
import { ChatWorkspace } from './ChatWorkspace'

/** Fenêtre plein écran du chatbot, ouverte depuis toutes les pages sauf l'accueil. */
export function ChatFenetre() {
  const { ouvert, fermer, statut, envoyer, langue, estConnecte } = useChat()
  const t = TEXTES[langue]
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
      aria-label={t.fenetre}
      className="no-print fixed inset-0 z-50 flex flex-col bg-creme"
    >
      <header className="border-b border-lin">
        <div className="mx-auto flex max-w-[1376px] items-center justify-between gap-3 px-4 py-2.5 sm:px-8 sm:py-3.5">
          <Logo className="hidden sm:inline" />
          <div className="flex min-w-0 items-center gap-3">
            {/* Avatar DÉTOUR de la charte : le seul accent jaune de l'écran de discussion. */}
            <img src="/new-logos/detour-avatar.svg" alt="" className="size-10 shrink-0" />
            <span className="flex min-w-0 flex-col">
              <span className="text-[15px] font-extrabold">{t.titre}</span>
              <span className="truncate text-xs text-taupe">{t.sousTitre}</span>
            </span>
          </div>
          <div className="flex items-center gap-2">
            {estConnecte && (
              <button
                type="button"
                onClick={() => envoyer(t.messageConseiller)}
                className="hidden min-h-11 items-center gap-2 rounded-full border-[1.5px] border-encre px-[18px] text-sm font-bold hover:bg-sable md:flex"
              >
                <IconPersonne size={18} />
                {t.parlerConseiller}
              </button>
            )}
            <button
              ref={fermerBouton}
              type="button"
              onClick={fermer}
              aria-label={t.fermer}
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

/** Vrai tant que le pied de page est à l'écran : le bouton flottant masquerait ses liens. */
function usePiedDePageVisible() {
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    // Sans IntersectionObserver (tests, très vieux navigateurs), le bouton reste toujours affiché.
    const pied = document.getElementById(ID_PIED_DE_PAGE)
    if (!pied || typeof IntersectionObserver === 'undefined') return
    const observateur = new IntersectionObserver(([entree]) => setVisible(entree.isIntersecting))
    observateur.observe(pied)
    return () => observateur.disconnect()
  }, [])

  return visible
}

/** Bouton flottant présent sur toutes les pages sauf l'accueil (qui a sa propre section), masqué devant le pied de page. */
export function ChatLanceur() {
  const { ouvert, statut, langue } = useChat()
  const t = TEXTES[langue]
  const ouvrirAssistant = useOuvrirAssistant()
  const estAccueil = useLocation({ select: (l) => l.pathname === '/' })
  const estProposition = useLocation({ select: (l) => l.pathname.startsWith('/proposition') })
  const piedVisible = usePiedDePageVisible()
  if (estAccueil || ouvert || piedVisible) return null

  return (
    <button
      type="button"
      onClick={() => ouvrirAssistant()}
      className="no-print fixed right-4 bottom-4 z-40 flex min-h-12 items-center gap-2.5 rounded-full bg-encre py-3 pr-5 pl-4 text-[15px] font-bold text-white shadow-[0_10px_30px_rgba(22,35,63,0.3)] hover:bg-rouille sm:right-8 sm:bottom-8"
    >
      <IconBulle size={22} />
      {statut === 'conversation'
        ? t.reprendre
        : estProposition
          ? t.modifierDansLeChat
          : t.parlerAssistant}
    </button>
  )
}
