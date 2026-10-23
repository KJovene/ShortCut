import { Link } from '@tanstack/react-router'
import { IconBulle } from '@/components/ui/icons'
import { useChat } from './chatContext'
import { TEXTES } from './textes'

/** Réponses gardées en français quelle que soit la langue du site : la fiche demande et l'assistant les lisent. */
const CHOIX = TEXTES.fr.questionnaire.choix

export function QuestionnaireInvite() {
  const { questionnaire, repondreQuestion, fermer, langue } = useChat()
  const t = TEXTES[langue].questionnaire
  const rang = questionnaire.reponses.length
  const question = t.questions[rang]

  if (!question) {
    return (
      <div className="mx-auto flex w-full max-w-[620px] flex-col items-stretch gap-4">
        <BullePresentation />
        <div className="flex flex-col gap-4 rounded-[24px] bg-white p-6 text-center shadow-[0_20px_50px_rgba(43,29,20,0.14)] sm:p-8">
          <h2 className="m-0 font-display text-[30px] leading-tight font-semibold">
            {t.titreFin}
          </h2>
          <p className="m-0 text-[15px] leading-relaxed text-taupe">{t.texteFin}</p>
          <div className="flex flex-col justify-center gap-2 sm:flex-row">
            <Link
              to="/connexion"
              onClick={fermer}
              className="rounded-full bg-terre px-5 py-3 font-extrabold text-white hover:bg-rouille"
            >
              {t.connexion}
            </Link>
            <Link
              to="/inscription"
              onClick={fermer}
              className="rounded-full border-[1.5px] border-encre px-5 py-3 font-extrabold hover:bg-sable"
            >
              {t.inscription}
            </Link>
          </div>
          <p className="m-0 text-xs text-taupe-clair">{t.conservees}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="mx-auto flex w-full max-w-[620px] flex-col items-stretch gap-4">
      <BullePresentation />
      <div className="flex flex-col gap-5 rounded-[24px] bg-white p-6 text-center shadow-[0_20px_50px_rgba(43,29,20,0.14)] sm:p-8">
        <div className="flex justify-center gap-2" aria-label={t.numero(rang + 1)}>
          {t.questions.map((_, index) => (
            <span
              key={index}
              aria-hidden="true"
              className={`h-2 w-12 rounded-full ${index <= rang ? 'bg-terre' : 'bg-sable'}`}
            />
          ))}
        </div>
        <p className="m-0 text-xs font-extrabold tracking-[1.5px] text-rouille uppercase">
          {t.numero(rang + 1)}
        </p>
        <h2 className="m-0 font-display text-[30px] leading-tight font-semibold">{question}</h2>
        <div className="grid gap-2 text-left sm:grid-cols-2">
          {CHOIX[rang].map((valeur, index) => (
            <button
              key={valeur}
              type="button"
              onClick={() => repondreQuestion(valeur)}
              className="min-h-12 rounded-2xl border-[1.5px] border-beige px-4 text-left font-bold hover:border-terre hover:bg-terre-pale"
            >
              {t.choix[rang][index]}
            </button>
          ))}
        </div>
      </div>
    </div>
  )
}

function BullePresentation() {
  const { langue } = useChat()
  const t = TEXTES[langue].questionnaire
  return (
    <div className="flex items-start gap-3 self-start rounded-[6px_20px_20px_20px] bg-white px-4 py-3.5 text-left shadow-[0_2px_10px_rgba(43,29,20,0.06)]">
      <span
        aria-hidden="true"
        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-terre text-white"
      >
        <IconBulle size={18} />
      </span>
      <p className="m-0 text-[15px] leading-relaxed">
        {t.presentation}
        <span className="mt-1 block text-taupe">{t.apresConnexion}</span>
      </p>
    </div>
  )
}
