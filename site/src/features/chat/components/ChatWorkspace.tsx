import { useEffect, useRef } from 'react'
import { IconFlecheHaut, IconRecommencer } from '@/components/ui/icons'
import { cn } from '@/lib/cn'
import { useChat } from '../chatContext'
import { nombreRenseignes } from '../demande'
import { TEXTES } from '../textes'
import { QuestionnaireInvite } from '../QuestionnaireInvite'
import { AssistantEcrit, Bulle } from './Messages'
import { Recapitulatif } from './Recapitulatif'

/**
 * Écran de discussion avec l'assistant et récapitulatif (maquette E · Discussion).
 * Affiché dans la section de l'accueil une fois le premier message envoyé, et dans la fenêtre du chatbot.
 */
export function ChatWorkspace({ className }: { className?: string }) {
  const {
    messages,
    reflexion,
    brouillon,
    setBrouillon,
    envoyer,
    recommencer,
    conversation,
    terminee,
    langue,
    estConnecte,
  } = useChat()
  const t = TEXTES[langue]
  const fil = useRef<HTMLDivElement>(null)
  // Seul le dernier récapitulatif propose ses options : les précédents sont dépassés.
  const dernierRecapitulatif = messages.findLast(
    (m) => 'type' in m && m.type === 'recapitulatif',
  )?.id
  const champ = useRef<HTMLInputElement>(null)
  /** Le client lit la fin du fil : il suit ce qui s'y ajoute. Remonté plus haut, il n'est pas dérangé. */
  const enBas = useRef(true)

  useEffect(() => {
    const el = fil.current
    if (!el) return
    // Défilement instantané : un défilement doux ici interromprait celui de la page (Chrome n'en mène
    // qu'un à la fois), qui cale la section de l'accueil en haut de l'écran au premier message.
    const descendre = () => {
      if (enBas.current) el.scrollTop = el.scrollHeight
    }
    const surDefilement = () => {
      enBas.current = el.scrollHeight - el.scrollTop - el.clientHeight < 80
    }
    // Le fil grandit sans nouveau message : réponse écrite au fil du flux, photos des cartes chargées après.
    const observateur = new MutationObserver(descendre)
    observateur.observe(el, { childList: true, subtree: true, characterData: true })
    el.addEventListener('scroll', surDefilement)
    el.addEventListener('load', descendre, true)
    descendre()
    return () => {
      observateur.disconnect()
      el.removeEventListener('scroll', surDefilement)
      el.removeEventListener('load', descendre, true)
    }
  }, [estConnecte])

  // Le client envoie un message : le fil revient en bas pour la réponse, même s'il était remonté.
  const dernierAuteur = messages.at(-1)?.auteur
  useEffect(() => {
    if (dernierAuteur !== 'client') return
    enBas.current = true
    const el = fil.current
    if (el) el.scrollTop = el.scrollHeight
  }, [messages.length, dernierAuteur])

  if (!estConnecte) return <QuestionnaireInvite />

  const modifier = (texte: string) => {
    setBrouillon(texte)
    champ.current?.focus()
  }

  return (
    <div
      lang={langue}
      className={cn('flex min-h-0 flex-col gap-6 lg:flex-row lg:gap-8', className)}
    >
      <section
        aria-label={t.conversation}
        className="flex min-h-0 min-w-0 flex-[999_1_560px] flex-col"
      >
        <details className="mb-3 rounded-2xl bg-white shadow-sm lg:hidden">
          <summary className="flex min-h-12 cursor-pointer items-center justify-between px-4 font-bold">
            {t.votreVoyage}
            <span className="text-sm text-rouille">
              {t.criteres(nombreRenseignes(conversation.demande))}
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
            {t.aujourdhui}
          </span>
          {messages.map((m) => (
            <Bulle key={m.id} message={m} actif={!terminee && m.id === dernierRecapitulatif} />
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
          {terminee ? (
            <p className="m-0 rounded-full bg-white px-5 py-3.5 text-center text-sm text-taupe shadow-[0_8px_24px_rgba(22,35,63,0.10)]">
              {t.transmise}
            </p>
          ) : (
            <div className="flex items-center gap-2 rounded-full bg-white py-1.5 pr-1.5 pl-2 shadow-[0_8px_24px_rgba(22,35,63,0.10)]">
              <label className="flex flex-1">
                <span className="sr-only">{t.votreMessage}</span>
                <input
                  ref={champ}
                  type="text"
                  value={brouillon}
                  onChange={(e) => setBrouillon(e.target.value)}
                  placeholder={t.placeholder}
                  className="min-h-11 flex-1 bg-transparent px-3 text-[15px] outline-none"
                />
              </label>
              <button
                type="submit"
                aria-label={t.envoyer}
                disabled={!brouillon.trim()}
                className="flex size-12 shrink-0 items-center justify-center rounded-full bg-terre text-white hover:bg-rouille disabled:opacity-50"
              >
                <IconFlecheHaut strokeWidth={2.4} />
              </button>
            </div>
          )}
          <div className="flex flex-wrap items-center justify-between gap-2 px-2 text-xs text-taupe-clair">
            <span>{t.estimations}</span>
            <button
              type="button"
              onClick={recommencer}
              className="flex min-h-9 items-center gap-1.5 font-bold text-taupe hover:text-encre"
            >
              <IconRecommencer size={14} />
              {t.recommencer}
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
