import { useEffect, useRef } from 'react'
import type { Destination } from '@/data/catalogue'
import { useChat } from '@/features/chat/chatContext'
import { ChatAccueil } from '@/features/chat/components/ChatAccueil'
import { ChatWorkspace } from '@/features/chat/components/ChatWorkspace'
import { ID_SECTION_ASSISTANT } from '@/features/chat/useOuvrirAssistant'

function Polaroid({ d, rotation }: { d: Destination; rotation: string }) {
  return (
    <div
      className="bg-white p-2.5 pb-9 shadow-[0_14px_30px_rgba(43,29,20,0.18)]"
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
  suggestion: Destination
  photos: Destination[]
}) {
  const { statut } = useChat()
  const section = useRef<HTMLElement>(null)
  const precedent = useRef(statut)

  // Au premier envoi, la section se transforme : on la cale en haut de l'écran.
  useEffect(() => {
    if (precedent.current === 'accueil' && statut === 'conversation') {
      section.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
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
        <div className="mx-auto flex max-w-[1376px] flex-wrap items-center justify-center gap-10 px-4 py-10 sm:px-8 sm:py-20">
          <div
            aria-hidden="true"
            className="hidden flex-[0_1_230px] flex-col items-center gap-[18px] xl:flex"
          >
            <Polaroid d={photos[0]} rotation="-4deg" />
            <Polaroid d={photos[1]} rotation="3deg" />
          </div>
          <ChatAccueil suggestion={`Comme ${suggestion.ville}, à mes dates`} />
          <div
            aria-hidden="true"
            className="hidden flex-[0_1_230px] flex-col items-center gap-[18px] xl:flex"
          >
            <Polaroid d={photos[2]} rotation="4deg" />
            <Polaroid d={photos[3]} rotation="-3deg" />
          </div>
        </div>
      ) : (
        <div className="mx-auto flex max-w-[1376px] flex-col px-4 pt-6 pb-4 sm:px-8 lg:h-[clamp(720px,100vh,960px)]">
          <h2 id="discussion-titre" className="sr-only">
            Discussion avec l'assistant
          </h2>
          <ChatWorkspace className="min-h-[70vh] flex-1 lg:min-h-0" />
        </div>
      )}
    </section>
  )
}
