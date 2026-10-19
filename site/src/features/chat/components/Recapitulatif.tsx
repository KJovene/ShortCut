import { Link } from '@tanstack/react-router'
import { trouverDestination } from '@/data/catalogue'
import { cn } from '@/lib/cn'
import { useChat } from '../chatContext'
import { CRITERES, LIBELLES, nombreRenseignes, valeurCritere } from '../demande'

/** Récapitulatif des neuf critères, à côté de la conversation. */
export function Recapitulatif({
  onModifier,
  titreId,
}: {
  onModifier: (texte: string) => void
  titreId?: string
}) {
  const { conversation, propositionId, reflexion } = useChat()
  const d = conversation.demande
  const destination = d.destination ? trouverDestination(d.destination) : undefined
  const renseignes = nombreRenseignes(d)
  const mois = d.mois ?? destination?.moisIdeal

  return (
    <div className="flex flex-col overflow-hidden rounded-[28px] bg-white shadow-[0_18px_50px_rgba(43,29,20,0.12)]">
      <div className="relative h-[150px] shrink-0 bg-sable">
        {destination ? (
          <>
            <img
              src={destination.photo}
              alt={destination.alt}
              className="block size-full object-cover"
            />
            <span className="absolute bottom-3.5 left-4 rounded-full bg-white px-3 py-1.5 text-[13px] font-extrabold">
              Piste : {destination.ville}
              {mois !== undefined && ` · ${destination.temperatures[mois]} °C`}
            </span>
          </>
        ) : (
          <img
            src="/images/carte-du-monde.svg"
            alt=""
            className="block size-full object-cover opacity-80"
          />
        )}
      </div>
      <div className="flex flex-col gap-3.5 px-[22px] pt-[18px] pb-[22px]">
        <div className="flex items-baseline justify-between gap-2.5">
          <h2 id={titreId} className="m-0 font-display text-[26px] font-semibold">
            Votre voyage
          </h2>
          <span
            className={cn(
              'text-[13px] font-extrabold',
              renseignes === 9 ? 'text-vert-fonce' : 'text-rouille',
            )}
          >
            {renseignes} / 9 critères
          </span>
        </div>
        <div
          role="progressbar"
          aria-label="Critères renseignés"
          aria-valuemin={0}
          aria-valuemax={9}
          aria-valuenow={renseignes}
          className="h-1.5 rounded-full bg-sable"
        >
          <div
            className="h-1.5 rounded-full bg-vert transition-[width]"
            style={{ width: `${(renseignes / 9) * 100}%` }}
          />
        </div>
        <dl className="m-0 flex flex-col">
          {CRITERES.map((critere) => {
            const valeur = valeurCritere(d, critere)
            const signale = critere === 'contraintes' && d.contraintesSignalees.length > 0
            return (
              <div key={critere} className="flex items-start gap-3 border-b border-sable py-2.5">
                <dt className="w-[104px] shrink-0 pt-px text-[13px] font-bold text-taupe-clair">
                  {LIBELLES[critere]}
                </dt>
                <dd
                  className={cn(
                    'm-0 flex-1 text-sm leading-snug font-semibold',
                    !valeur && 'font-normal text-taupe-clair italic',
                  )}
                >
                  {valeur ?? 'À préciser'}
                  {signale && (
                    <span className="block text-xs font-bold text-rouille not-italic">
                      Signalé à votre conseiller
                    </span>
                  )}
                </dd>
                <button
                  type="button"
                  aria-label={`Modifier : ${LIBELLES[critere]}`}
                  onClick={() => onModifier(`${LIBELLES[critere]} : `)}
                  className="text-[13px] font-bold text-rouille hover:text-encre hover:underline"
                >
                  Modifier
                </button>
              </div>
            )
          })}
        </dl>
        {propositionId ? (
          <>
            <p className="m-0 text-xs leading-normal text-taupe-clair">
              Prix estimés, confirmés par votre conseiller sous 2 jours ouvrés. Rien n'est réservé
              avant.
            </p>
            <Link
              to="/proposition/$id"
              params={{ id: propositionId }}
              className="flex min-h-[50px] items-center justify-center rounded-full bg-terre font-extrabold text-white hover:bg-rouille"
            >
              Voir ma proposition
            </Link>
          </>
        ) : (
          <>
            <p className="m-0 text-xs leading-normal text-taupe-clair">
              Prochaine étape : votre proposition complète arrive ici dans moins de 2 minutes. Prix
              estimés, confirmés par votre conseiller sous 2 jours ouvrés.
            </p>
            <button
              type="button"
              disabled
              className="min-h-[50px] rounded-full bg-lin text-[15px] font-extrabold text-taupe"
            >
              {reflexion && renseignes >= 7
                ? 'Proposition en préparation…'
                : 'Proposition après vos réponses'}
            </button>
          </>
        )}
      </div>
    </div>
  )
}
