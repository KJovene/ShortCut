import { IconFleche } from '@/components/ui/icons'
import { cn } from '@/lib/cn'
import { useChat } from '../chatContext'

const SUGGESTIONS = ['Voyage de noces', 'En famille', 'Entre amis']

/**
 * Invitation à décrire son voyage (maquette E · section assistant). Le premier envoi lance la conversation.
 */
export function ChatAccueil({
  suggestion,
  compact = false,
}: {
  suggestion?: string
  compact?: boolean
}) {
  const { brouillon, setBrouillon, envoyer } = useChat()

  const ajouterSuggestion = (texte: string) =>
    setBrouillon(brouillon.trim() ? `${brouillon.trim()}, ${texte.toLowerCase()}` : texte)

  return (
    <div className="flex w-full max-w-[820px] flex-col items-center gap-4 text-center sm:gap-5">
      <span className="flex items-center gap-2 text-[13px] font-extrabold tracking-[1.5px] text-rouille uppercase">
        <span aria-hidden="true" className="size-2.5 rounded-full bg-vert" />
        Votre assistant voyage, disponible maintenant
      </span>
      <h2
        id="assistant-titre"
        className={cn(
          'm-0 font-display leading-[1.05] font-semibold tracking-[-1.5px]',
          compact ? 'text-[32px] sm:text-[44px]' : 'text-[32px] sm:text-[52px]',
        )}
      >
        Racontez-nous vos <em className="font-normal text-terre">prochaines vacances</em>
      </h2>
      <p className="m-0 max-w-[620px] text-[15px] leading-relaxed text-taupe sm:text-lg">
        Vos envies, vos dates, votre budget : notre assistant vous remet un voyage complet et
        chiffré en moins de 2 minutes. Votre conseiller le confirme ensuite.
      </p>
      <form
        className="flex w-full flex-col gap-2.5 rounded-[22px] bg-white p-2.5 text-left shadow-[0_20px_50px_rgba(43,29,20,0.14)] sm:rounded-[28px] sm:p-3"
        onSubmit={(e) => {
          e.preventDefault()
          envoyer(brouillon)
        }}
      >
        <label className="flex flex-col">
          <span className="sr-only">Décrivez votre voyage</span>
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
            placeholder="Ex. : 10 jours en mai, au moins 25 °C, 4 000 € pour deux, sans voiture…"
            className="resize-none bg-transparent px-2.5 py-2 text-base leading-snug outline-none sm:px-3.5 sm:py-3 sm:text-[19px]"
          />
        </label>
        <div className="flex flex-wrap items-center justify-between gap-2.5 px-1 pb-1">
          <div className="flex flex-wrap gap-1.5">
            {suggestion && (
              <button
                type="button"
                onClick={() => ajouterSuggestion(suggestion)}
                className="min-h-10 rounded-full border-[1.5px] border-terre bg-terre-pale px-3.5 text-sm font-bold text-rouille"
              >
                {suggestion}
              </button>
            )}
            {SUGGESTIONS.map((s) => (
              <button
                key={s}
                type="button"
                onClick={() => ajouterSuggestion(s)}
                className="min-h-10 rounded-full border-[1.5px] border-beige px-3.5 text-sm font-semibold hover:border-encre"
              >
                {s}
              </button>
            ))}
          </div>
          <button
            type="submit"
            disabled={!brouillon.trim()}
            className="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-full bg-terre px-6 font-extrabold text-white hover:bg-rouille disabled:opacity-60 sm:w-auto"
          >
            Construire mon voyage
            <IconFleche size={18} strokeWidth={2.4} />
          </button>
        </div>
      </form>
      <ul className="m-0 mt-1 flex list-none flex-wrap justify-center gap-x-7 gap-y-2.5 p-0 text-sm font-bold text-taupe">
        <li>Proposition chiffrée en 2 min</li>
        <li>3 budgets au choix</li>
        <li>Confirmée par un conseiller sous 2 jours</li>
      </ul>
      <p className="m-0 text-xs text-taupe-clair">
        Je suis une IA. Mes prix sont des estimations, rien n'est réservé avant la confirmation de
        votre conseiller.
      </p>
    </div>
  )
}
