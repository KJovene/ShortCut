import { IconCoche } from '@/components/ui/icons'
import { cn } from '@/lib/cn'
import { useChat } from '../chatContext'
import type { Message } from '../conversation'
import { TEXTES } from '../textes'

const BULLE_ASSISTANT =
  'rounded-[6px_20px_20px_20px] bg-white shadow-[0_2px_10px_rgba(22,35,63,0.06)]'

export function Bulle({ message }: { message: Message }) {
  if (message.auteur === 'client') {
    return (
      <div className="max-w-[85%] self-end rounded-[20px_6px_20px_20px] bg-encre px-4 py-3 whitespace-pre-line text-creme sm:max-w-[72%]">
        {message.texte}
      </div>
    )
  }
  if ('type' in message && message.type === 'confirmation') {
    return <Confirmation texte={message.texte} />
  }
  return (
    <div className={cn('max-w-[85%] self-start px-4 py-3 sm:max-w-[72%]', BULLE_ASSISTANT)}>
      {message.texte}
    </div>
  )
}

/** Fin de la conversation : la demande est enregistrée et transmise à un conseiller. */
function Confirmation({ texte }: { texte: string }) {
  const { langue } = useChat()
  return (
    <div
      role="status"
      className="flex max-w-full items-start gap-3 self-stretch rounded-[20px] border-2 border-vert bg-white px-4 py-3.5 shadow-[0_2px_10px_rgba(22,35,63,0.06)]"
    >
      <span
        aria-hidden="true"
        className="flex size-8 shrink-0 items-center justify-center rounded-full bg-vert text-white"
      >
        <IconCoche size={16} />
      </span>
      <span className="flex flex-col gap-0.5">
        <strong className="text-[15px] font-extrabold text-vert-fonce">
          {TEXTES[langue].demandeTransmise}
        </strong>
        <span>{texte}</span>
      </span>
    </div>
  )
}

export function AssistantEcrit() {
  const { langue } = useChat()
  return (
    <div
      className={cn('flex items-center gap-1 self-start px-4 py-3', BULLE_ASSISTANT)}
      role="status"
    >
      <span className="sr-only">{TEXTES[langue].assistantEcrit}</span>
      {[0, 1, 2].map((i) => (
        <span
          key={i}
          aria-hidden="true"
          className="size-2 animate-typing-dot rounded-full bg-terre"
          style={{ animationDelay: `${i * 0.2}s` }}
        />
      ))}
    </div>
  )
}
