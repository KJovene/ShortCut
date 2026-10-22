import { Link } from '@tanstack/react-router'
import { IconBulle } from '@/components/ui/icons'
import { useChat } from './chatContext'

const QUESTIONS = [
  {
    question: 'Quel type de voyage vous fait envie ?',
    choix: ['Séjours', 'Circuits', 'Groupes', 'Noces', 'Entreprise'],
  },
  {
    question: 'Avec qui souhaitez-vous partir ?',
    choix: ['Seul(e)', 'À deux', 'En famille', 'Entre amis'],
  },
  {
    question: 'Quel budget prévoyez-vous environ ?',
    choix: ['Moins de 1 500 €', 'De 1 500 à 3 000 €', 'Plus de 3 000 €'],
  },
] as const

export function QuestionnaireInvite() {
  const { questionnaire, repondreQuestion, fermer } = useChat()
  const question = QUESTIONS[questionnaire.reponses.length]

  if (!question) {
    return (
      <div className="mx-auto flex w-full max-w-[620px] flex-col items-stretch gap-4">
        <BullePresentation />
        <div className="flex flex-col gap-4 rounded-[24px] bg-white p-6 text-center shadow-[0_20px_50px_rgba(43,29,20,0.14)] sm:p-8">
          <h2 className="m-0 font-display text-[30px] leading-tight font-semibold">
            Votre voyage commence ici
          </h2>
          <p className="m-0 text-[15px] leading-relaxed text-taupe">
            Pour continuer la conversation et obtenir une proposition personnalisée, veuillez vous
            connecter ou vous inscrire.
          </p>
          <div className="flex flex-col justify-center gap-2 sm:flex-row">
            <Link
              to="/connexion"
              onClick={fermer}
              className="rounded-full bg-terre px-5 py-3 font-extrabold text-white hover:bg-rouille"
            >
              Se connecter
            </Link>
            <Link
              to="/inscription"
              onClick={fermer}
              className="rounded-full border-[1.5px] border-encre px-5 py-3 font-extrabold hover:bg-sable"
            >
              Créer un compte
            </Link>
          </div>
          <p className="m-0 text-xs text-taupe-clair">
            Vos réponses sont conservées pendant votre connexion.
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="mx-auto flex w-full max-w-[620px] flex-col items-stretch gap-4">
      <BullePresentation />
      <div className="flex flex-col gap-5 rounded-[24px] bg-white p-6 text-center shadow-[0_20px_50px_rgba(43,29,20,0.14)] sm:p-8">
        <div
          className="flex justify-center gap-2"
          aria-label={`Question ${questionnaire.reponses.length + 1} sur 3`}
        >
          {QUESTIONS.map((_, index) => (
            <span
              key={index}
              aria-hidden="true"
              className={`h-2 w-12 rounded-full ${index <= questionnaire.reponses.length ? 'bg-terre' : 'bg-sable'}`}
            />
          ))}
        </div>
        <p className="m-0 text-xs font-extrabold tracking-[1.5px] text-rouille uppercase">
          Question {questionnaire.reponses.length + 1} sur 3
        </p>
        <h2 className="m-0 font-display text-[30px] leading-tight font-semibold">
          {question.question}
        </h2>
        <div className="grid gap-2 text-left sm:grid-cols-2">
          {question.choix.map((choix) => (
            <button
              key={choix}
              type="button"
              onClick={() => repondreQuestion(choix)}
              className="min-h-12 rounded-2xl border-[1.5px] border-beige px-4 text-left font-bold hover:border-terre hover:bg-terre-pale"
            >
              {choix}
            </button>
          ))}
        </div>
      </div>
    </div>
  )
}

function BullePresentation() {
  return (
    <div className="flex items-start gap-3 self-start rounded-[6px_20px_20px_20px] bg-white px-4 py-3.5 text-left shadow-[0_2px_10px_rgba(43,29,20,0.06)]">
      <span
        aria-hidden="true"
        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-terre text-white"
      >
        <IconBulle size={18} />
      </span>
      <p className="m-0 text-[15px] leading-relaxed">
        Bonjour, je suis votre assistant voyage. Répondez à ces trois questions pour commencer.
        <span className="mt-1 block text-taupe">
          Après votre connexion, vous aurez accès à un chatbot plus poussé pour construire votre
          voyage en détail.
        </span>
      </p>
    </div>
  )
}
