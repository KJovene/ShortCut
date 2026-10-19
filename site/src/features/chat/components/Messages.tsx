import { Link } from '@tanstack/react-router'
import { IconCoche } from '@/components/ui/icons'
import { formatEuros } from '@/data/catalogue'
import { NIVEAUX } from '@/features/proposition/proposition'
import { trouverProposition } from '@/features/proposition/store'
import { cn } from '@/lib/cn'
import type { Ambiance, EtatEtape, Message } from '../assistant'
import { useChat } from '../chatContext'

const BULLE_ASSISTANT =
  'rounded-[6px_20px_20px_20px] bg-white shadow-[0_2px_10px_rgba(43,29,20,0.06)]'

export function Bulle({ message }: { message: Message }) {
  if (message.auteur === 'client') {
    return (
      <div className="max-w-[85%] self-end rounded-[20px_6px_20px_20px] bg-encre px-4 py-3 whitespace-pre-line text-creme sm:max-w-[72%]">
        {message.texte}
      </div>
    )
  }
  if ('type' in message) {
    switch (message.type) {
      case 'ambiances':
        return (
          <ChoixAmbiances
            texte={message.texte}
            ambiances={message.ambiances}
            choix={message.choix}
          />
        )
      case 'preparation':
        return <Preparation texte={message.texte} etapes={message.etapes} />
      case 'proposition':
        return <CartePropositionChat texte={message.texte} propositionId={message.propositionId} />
      case 'confirmation':
        return <Confirmation texte={message.texte} />
    }
  }
  return (
    <div className={cn('max-w-[85%] self-start px-4 py-3 sm:max-w-[72%]', BULLE_ASSISTANT)}>
      {message.texte}
    </div>
  )
}

/** Fin de la conversation : la demande est enregistrée et transmise à un conseiller. */
function Confirmation({ texte }: { texte: string }) {
  return (
    <div
      role="status"
      className="flex max-w-full items-start gap-3 self-stretch rounded-[20px] border-2 border-vert bg-white px-4 py-3.5 shadow-[0_2px_10px_rgba(43,29,20,0.06)]"
    >
      <span
        aria-hidden="true"
        className="flex size-8 shrink-0 items-center justify-center rounded-full bg-vert text-white"
      >
        <IconCoche size={16} />
      </span>
      <span className="flex flex-col gap-0.5">
        <strong className="text-[15px] font-extrabold text-vert-fonce">Demande transmise</strong>
        <span>{texte}</span>
      </span>
    </div>
  )
}

export function AssistantEcrit() {
  return (
    <div
      className={cn('flex items-center gap-1 self-start px-4 py-3', BULLE_ASSISTANT)}
      role="status"
    >
      <span className="sr-only">L'assistant écrit…</span>
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

function ChoixAmbiances({
  texte,
  ambiances,
  choix,
}: {
  texte: string
  ambiances: Ambiance[]
  choix?: string
}) {
  const { choisirAmbiance, reflexion } = useChat()
  return (
    <div className="flex max-w-full flex-col gap-2.5 self-start sm:max-w-[92%]">
      <div className={cn('px-4 py-3', BULLE_ASSISTANT)}>{texte}</div>
      <div className="grid grid-cols-1 gap-2.5 min-[480px]:grid-cols-3">
        {ambiances.map((a) => {
          const choisie = choix === a.slug
          return (
            <button
              key={a.slug}
              type="button"
              aria-pressed={choisie}
              disabled={!!choix || reflexion}
              onClick={() => choisirAmbiance(a.slug)}
              className={cn(
                'relative overflow-hidden rounded-[18px] border-2 bg-white text-left transition-opacity enabled:cursor-pointer enabled:hover:border-terre',
                choisie ? 'border-terre' : 'border-sable',
                choix && !choisie && 'opacity-60',
              )}
            >
              <img src={a.photo} alt="" className="block h-[110px] w-full object-cover" />
              <span className="flex flex-col gap-0.5 px-3 pt-2.5 pb-3">
                <strong className="font-display text-[17px] leading-tight font-semibold">
                  {a.titre}
                </strong>
                <span className="text-xs text-taupe">
                  {a.lieu}
                  {a.temperature !== undefined && ` · ${a.temperature} °C`}
                </span>
              </span>
              {choisie && (
                <span className="absolute top-2 right-2 rounded-full bg-terre px-2.5 py-1 text-[11px] font-extrabold text-white">
                  Votre choix
                </span>
              )}
            </button>
          )
        })}
      </div>
    </div>
  )
}

function PastilleEtape({ etat }: { etat: EtatEtape }) {
  if (etat === 'fait')
    return (
      <span
        aria-hidden="true"
        className="flex size-[22px] shrink-0 items-center justify-center rounded-full bg-vert text-white"
      >
        <IconCoche size={12} />
      </span>
    )
  if (etat === 'en-cours')
    return (
      <span
        aria-hidden="true"
        className="size-[22px] shrink-0 rounded-full border-[2.5px] border-terre"
      />
    )
  return (
    <span
      aria-hidden="true"
      className="size-[22px] shrink-0 rounded-full border-2 border-dashed border-[#C9B49A]"
    />
  )
}

const ETAT_LISIBLE: Record<EtatEtape, string> = {
  fait: 'terminé',
  'en-cours': 'en cours',
  attente: 'à venir',
}

function Preparation({
  texte,
  etapes,
}: {
  texte: string
  etapes: { label: string; etat: EtatEtape }[]
}) {
  return (
    <div className={cn('max-w-[92%] self-start overflow-hidden sm:max-w-[80%]', BULLE_ASSISTANT)}>
      <p className="m-0 px-4 py-3">{texte}</p>
      <ol
        className="m-0 flex list-none flex-col gap-2 bg-[#FBF1E6] px-4 pt-3 pb-3.5 text-sm"
        aria-live="polite"
      >
        {etapes.map((e) => (
          <li
            key={e.label}
            className={cn(
              'flex items-center gap-2.5',
              e.etat === 'en-cours' && 'font-bold',
              e.etat === 'attente' && 'text-taupe-clair',
            )}
          >
            <PastilleEtape etat={e.etat} />
            {e.label}
            <span className="sr-only"> : {ETAT_LISIBLE[e.etat]}</span>
          </li>
        ))}
      </ol>
    </div>
  )
}

function CartePropositionChat({ texte, propositionId }: { texte: string; propositionId: string }) {
  const proposition = trouverProposition(propositionId)
  const { envoyer, reflexion } = useChat()
  if (!proposition) return null
  const plusCourt = Math.max(proposition.nuits - 1, 3)

  return (
    <div className="flex w-full max-w-[560px] flex-col gap-2.5 self-start">
      <div className={cn('px-4 py-3', BULLE_ASSISTANT)}>{texte}</div>
      <article className="overflow-hidden rounded-[20px] bg-white shadow-[0_6px_20px_rgba(43,29,20,0.10)]">
        <div className="flex flex-col gap-2.5 px-4 py-3.5">
          <div className="flex flex-col gap-0.5">
            <span className="text-[11px] font-extrabold tracking-[1px] text-rouille uppercase">
              Première proposition · v{proposition.version}
            </span>
            <h3 className="m-0 font-display text-xl font-semibold">{proposition.titre}</h3>
            <span className="text-[13px] text-taupe">
              {proposition.jours.length} jours · {proposition.budgets.equilibre.activites.length}{' '}
              activités · navettes incluses
            </span>
          </div>
          <div className="grid grid-cols-3 gap-1.5">
            {NIVEAUX.map((n) => {
              const actif = n.valeur === 'equilibre'
              return (
                <div
                  key={n.valeur}
                  className={cn(
                    'flex flex-col gap-0.5 rounded-xl p-2',
                    actif ? 'border-2 border-terre bg-terre-pale' : 'border border-lin',
                  )}
                >
                  <span
                    className={cn('text-[11px]', actif ? 'font-bold text-rouille' : 'text-taupe')}
                  >
                    {n.label}
                  </span>
                  <span className="text-[15px] font-bold">
                    {formatEuros(proposition.budgets[n.valeur].total)}
                  </span>
                </div>
              )
            })}
          </div>
          <p className="m-0 text-[11px] text-taupe">
            Prix estimés, à confirmer par votre conseiller sous 2 jours ouvrés.
          </p>
          <Link
            to="/proposition/$id"
            params={{ id: proposition.id }}
            className="flex min-h-11 items-center justify-center rounded-xl bg-terre text-sm font-bold text-white hover:bg-rouille"
          >
            Voir la proposition complète
          </Link>
        </div>
      </article>
      <div className="flex flex-wrap gap-1.5">
        {[`Plutôt ${plusCourt + 1} jours`, 'Nous serons 4 personnes', 'Parler à un conseiller'].map(
          (suggestion) => (
            <button
              key={suggestion}
              type="button"
              disabled={reflexion}
              onClick={() => envoyer(suggestion)}
              className="min-h-9 rounded-full border-[1.5px] border-encre bg-white px-3 text-[13px] font-semibold hover:bg-sable disabled:opacity-50"
            >
              {suggestion}
            </button>
          ),
        )}
      </div>
    </div>
  )
}
