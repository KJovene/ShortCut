import { useEffect, useRef } from 'react'
import { IconFlecheHaut, IconRecommencer } from '@/components/ui/icons'
import { cn } from '@/lib/cn'
import { useChat } from '../chatContext'
import { nombreRenseignes } from '../demande'
import { AssistantEcrit, Bulle } from './Messages'
import { Recapitulatif } from './Recapitulatif'

/**
 * Écran de discussion avec l'assistant et récapitulatif (maquette E · Discussion).
 * Affiché dans la section de l'accueil une fois le premier message envoyé, et dans la fenêtre du chatbot.
 */
export function ChatWorkspace({ className }: { className?: string }) {
  const { messages, reflexion, brouillon, setBrouillon, envoyer, recommencer, conversation } =
    useChat()
  const fil = useRef<HTMLDivElement>(null)
  const champ = useRef<HTMLInputElement>(null)

  useEffect(() => {
    // Défilement instantané : un défilement doux ici interromprait celui de la page (Chrome n'en mène
    // qu'un à la fois), qui cale la section de l'accueil en haut de l'écran au premier message.
    const el = fil.current
    if (el) el.scrollTop = el.scrollHeight
  }, [messages.length, reflexion])

  const modifier = (texte: string) => {
    setBrouillon(texte)
    champ.current?.focus()
  }

  return (
    <div className={cn('flex min-h-0 flex-col gap-6 lg:flex-row lg:gap-8', className)}>
      <section
        aria-label="Conversation avec l'assistant"
        className="flex min-h-0 min-w-0 flex-[999_1_560px] flex-col"
      >
        <details className="mb-3 rounded-2xl bg-white shadow-sm lg:hidden">
          <summary className="flex min-h-12 cursor-pointer items-center justify-between px-4 font-bold">
            Votre voyage
            <span className="text-sm text-rouille">
              {nombreRenseignes(conversation.demande)} / 9 critères
            </span>
          </summary>
          <div className="p-2">
            <Recapitulatif onModifier={modifier} />
          </div>
        </details>

        <div
          ref={fil}
          className="flex min-h-[320px] flex-1 flex-col gap-3.5 overflow-y-auto py-4 pr-2 text-[15px] leading-normal"
          aria-live="polite"
          aria-relevant="additions"
        >
          <span className="self-center rounded-full bg-sable px-3 py-1 text-xs font-bold text-taupe-clair">
            Aujourd'hui · Je suis une IA, un conseiller confirme chaque proposition
          </span>
          {messages.map((m) => (
            <Bulle key={m.id} message={m} />
          ))}
          {reflexion && <AssistantEcrit />}
        </div>

        <form
          className="flex flex-col gap-2.5 border-t border-lin pt-3 pb-1"
          onSubmit={(e) => {
            e.preventDefault()
            envoyer(brouillon)
          }}
        >
          <div className="flex items-center gap-2 rounded-full bg-white py-1.5 pr-1.5 pl-2 shadow-[0_8px_24px_rgba(43,29,20,0.10)]">
            <label className="flex flex-1">
              <span className="sr-only">Votre message</span>
              <input
                ref={champ}
                type="text"
                value={brouillon}
                onChange={(e) => setBrouillon(e.target.value)}
                placeholder="Ajouter une précision ou modifier votre demande…"
                className="min-h-11 flex-1 bg-transparent px-3 text-[15px] outline-none"
              />
            </label>
            <button
              type="submit"
              aria-label="Envoyer"
              disabled={!brouillon.trim()}
              className="flex size-12 shrink-0 items-center justify-center rounded-full bg-terre text-white hover:bg-rouille disabled:opacity-50"
            >
              <IconFlecheHaut strokeWidth={2.4} />
            </button>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-2 px-2 text-xs text-taupe-clair">
            <span>
              Mes prix sont des estimations, rien n'est réservé avant la confirmation de votre
              conseiller.
            </span>
            <button
              type="button"
              onClick={recommencer}
              className="flex min-h-9 items-center gap-1.5 font-bold text-taupe hover:text-encre"
            >
              <IconRecommencer size={14} />
              Recommencer
            </button>
          </div>
        </form>
      </section>

      <aside
        aria-labelledby="recap-titre"
        className="hidden min-h-0 min-w-0 flex-[1_1_380px] overflow-y-auto lg:block"
      >
        <Recapitulatif onModifier={modifier} titreId="recap-titre" />
      </aside>
    </div>
  )
}
