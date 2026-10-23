import { useEffect, useRef } from 'react'
import type { DestinationPublique } from '@/features/catalogue/catalogue'
import { useChat } from '@/features/chat/chatContext'
import { ChatAccueil } from '@/features/chat/components/ChatAccueil'
import { ChatWorkspace } from '@/features/chat/components/ChatWorkspace'
import { TEXTES as TEXTES_CHAT } from '@/features/chat/textes'
import { ID_SECTION_ASSISTANT } from '@/features/chat/useOuvrirAssistant'
import { TEXTES } from '@/features/pages/textes'
import { useLangue } from '@/lib/langue'

function Polaroid({ d, rotation }: { d: DestinationPublique | undefined; rotation: string }) {
  if (!d) return null
  return (
    <div
      className="bg-white p-2.5 pb-9 shadow-[0_14px_30px_rgba(22,35,63,0.18)]"
      style={{ transform: `rotate(${rotation})` }}
    >
      <img src={d.photo} alt="" className="block h-[150px] w-[200px] object-cover" />
    </div>
  )
}

/**
 * Section de l'assistant sur l'accueil : invitation à décrire son voyage, qui devient l'écran de discussion
 * avec récapitulatif dès le premier message envoyé.
 */
export function SectionAssistant({
  suggestion,
  photos,
}: {
  suggestion: DestinationPublique | undefined
  photos: DestinationPublique[]
}) {
  const { statut, langue: langueDuChat } = useChat()
  const t = TEXTES[useLangue()].accueil
  const tChat = TEXTES_CHAT[langueDuChat]
  const section = useRef<HTMLElement>(null)
  const precedent = useRef(statut)

  // Au premier envoi, la section se transforme : on la cale en haut de l'écran.
  useEffect(() => {
    const el = section.current
    if (precedent.current === 'accueil' && statut === 'conversation' && el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
      // Des images des sections précédentes peuvent finir de charger pendant le défilement et décaler
      // la section : on la recale en fin de défilement (scrollend, ou après 1,2 s sans cet événement).
      const recaler = () => {
        window.clearTimeout(minuteur)
        window.removeEventListener('scrollend', recaler)
        if (Math.abs(el.getBoundingClientRect().top) > 1) el.scrollIntoView({ block: 'start' })
      }
      const minuteur = window.setTimeout(recaler, 1200)
      window.addEventListener('scrollend', recaler)
      precedent.current = statut
      return () => {
        window.clearTimeout(minuteur)
        window.removeEventListener('scrollend', recaler)
      }
    }
    precedent.current = statut
  }, [statut])

  return (
    <section
      ref={section}
      id={ID_SECTION_ASSISTANT}
      aria-labelledby={statut === 'accueil' ? 'assistant-titre' : 'discussion-titre'}
      className="scroll-mt-0 bg-sable"
    >
      {statut === 'accueil' ? (
        // Trois colonnes sur une seule ligne : les polaroïds gardent leur largeur, le formulaire prend le reste.
        <div className="mx-auto flex max-w-[1376px] items-center justify-center gap-10 px-4 py-10 sm:px-8 sm:py-20">
          <div
            aria-hidden="true"
            className="hidden w-[230px] shrink-0 flex-col items-center gap-[18px] xl:flex"
          >
            <Polaroid d={photos[0]} rotation="-4deg" />
            <Polaroid d={photos[1]} rotation="3deg" />
          </div>
          <div className="flex min-w-0 flex-1 justify-center">
            <ChatAccueil suggestion={suggestion && t.suggestion(suggestion.ville)} />
          </div>
          <div
            aria-hidden="true"
            className="hidden w-[230px] shrink-0 flex-col items-center gap-[18px] xl:flex"
          >
            <Polaroid d={photos[2]} rotation="4deg" />
            <Polaroid d={photos[3]} rotation="-3deg" />
          </div>
        </div>
      ) : (
        <div className="mx-auto flex max-w-[1376px] flex-col px-4 pt-6 pb-4 sm:px-8 lg:h-[clamp(720px,100vh,960px)]">
          {/* Même en-tête que la fenêtre du chatbot : l'assistant se présente ici, pas dans le fil. */}
          <div className="flex items-center gap-3 border-b border-lin pb-3">
            {/* Avatar DÉTOUR de la charte : le seul accent jaune de l'écran de discussion. */}
            <img src="/new-logos/detour-avatar.svg" alt="" className="size-10 shrink-0" />
            <span className="flex min-w-0 flex-col">
              <h2 id="discussion-titre" className="m-0 font-sans text-[15px] font-extrabold">
                {tChat.titre}
              </h2>
              <span className="truncate text-xs text-taupe">{tChat.sousTitre}</span>
            </span>
          </div>
          <ChatWorkspace className="min-h-[70vh] flex-1 lg:min-h-0" />
        </div>
      )}
    </section>
  )
}
