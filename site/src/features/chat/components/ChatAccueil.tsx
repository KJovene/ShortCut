import { IconFleche } from '@/components/ui/icons'
import { SautDuO } from '@/components/ui/TitreMisEnValeur'
import { cn } from '@/lib/cn'
import { useChat } from '../chatContext'
import { TEXTES } from '../textes'
import { QuestionnaireInvite } from '../QuestionnaireInvite'

/**
 * Invitation à décrire son voyage (maquette E · section assistant). Le premier envoi lance la conversation.
 */
export function ChatAccueil({
  compact = false,
}: {
  suggestion?: string
  compact?: boolean
}) {
  const { brouillon, setBrouillon, envoyer, langue, estConnecte } = useChat()
  const t = TEXTES[langue].accueil

  if (!estConnecte) return <QuestionnaireInvite />

  return (
    <div className="flex w-full max-w-[820px] flex-col items-center gap-4 text-center sm:gap-5">
      <span className="flex items-center gap-2 text-[13px] font-extrabold text-rouille">
        <span aria-hidden="true" className="size-2.5 rounded-full bg-ciel" />
        {t.surtitre}
      </span>
      <h2
        id="assistant-titre"
        className={cn(
          'm-0 font-display font-semibold tracking-[-1.5px]',
          // Taille et interligne ensemble : avec cn (tailwind-merge), un text-[…] placé après un leading-[…] l'annule.
          compact
            ? 'text-[32px]/[1.05] sm:text-[44px]/[1.05]'
            : 'text-[32px]/[1.05] sm:text-[52px]/[1.05]',
        )}
      >
        {t.titreDebut}
        <SautDuO texte={t.titreAccent} />
      </h2>
      <p className="m-0 max-w-[620px] text-[15px] leading-relaxed text-taupe sm:text-lg">
        {t.texte}
      </p>
      <form
        className="flex w-full flex-col gap-2.5 rounded-[22px] bg-white p-2.5 text-left shadow-[0_20px_50px_rgba(22,35,63,0.14)] sm:rounded-[28px] sm:p-3"
        onSubmit={(e) => {
          e.preventDefault()
          envoyer(brouillon)
        }}
      >
        <label className="flex flex-col">
          <span className="sr-only">{t.decrivez}</span>
          <textarea
            rows={3}
            value={brouillon}
            onChange={(e) => setBrouillon(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault()
                envoyer(brouillon)
              }
            }}
            placeholder={t.placeholder}
            className="resize-none bg-transparent px-2.5 py-2 text-base leading-snug outline-none sm:px-3.5 sm:py-3 sm:text-[19px]"
          />
        </label>
        <div className="flex flex-wrap items-end justify-between gap-2.5 px-1 pb-1">
          <button
            type="submit"
            disabled={!brouillon.trim()}
            className="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-full bg-terre px-6 font-extrabold text-white hover:bg-rouille disabled:opacity-60 sm:ml-auto sm:w-auto"
          >
            {t.construire}
            <IconFleche size={18} strokeWidth={2.4} />
          </button>
        </div>
      </form>
      <ul className="m-0 mt-1 flex list-none flex-wrap justify-center gap-x-7 gap-y-2.5 p-0 text-sm font-bold text-taupe">
        {t.avantages.map((avantage) => (
          <li key={avantage}>{avantage}</li>
        ))}
      </ul>
      <p className="m-0 text-xs text-taupe-clair">{t.ia}</p>
    </div>
  )
}