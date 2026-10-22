import { useQuery } from '@tanstack/react-query'
import { destinationsQuery } from '@/features/catalogue/catalogue'
import { cn } from '@/lib/cn'
import { useChat } from '../chatContext'
import { CRITERES, nombreRenseignes, valeurCritere } from '../demande'
import { TEXTES } from '../textes'

/** Récapitulatif des neuf critères, à côté de la conversation. */
export function Recapitulatif({
  onModifier,
  titreId,
}: {
  onModifier: (texte: string) => void
  titreId?: string
}) {
  const { conversation, reflexion, terminee, langue } = useChat()
  const t = TEXTES[langue]
  const d = conversation.demande
  // Photo et climat de la destination reconnue, lus dans le catalogue de l'API.
  const { data: destinations = [] } = useQuery(destinationsQuery())
  const destination = destinations.find((x) => x.slug === d.destination?.slug)
  const renseignes = nombreRenseignes(d)
  const mois = d.mois ?? destination?.moisIdeal
  const temperature = mois === undefined ? null : (destination?.temperatures[mois] ?? null)

  return (
    <div className="flex flex-col overflow-hidden rounded-[28px] bg-white shadow-[0_18px_50px_rgba(22,35,63,0.12)]">
      <div className="relative h-[150px] shrink-0 bg-sable">
        {destination ? (
          <>
            <img
              src={destination.photo}
              alt={destination.alt}
              className="block size-full object-cover"
            />
            <span className="absolute bottom-3.5 left-4 rounded-full bg-white px-3 py-1.5 text-[13px] font-extrabold">
              {t.piste}
              {langue === 'en' ? ': ' : ' : '}
              {destination.ville}
              {temperature !== null && ` · ${temperature} °C`}
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
            {t.votreVoyage}
          </h2>
          <span
            className={cn(
              'text-[13px] font-extrabold',
              renseignes === 9 ? 'text-vert-fonce' : 'text-rouille',
            )}
          >
            {t.criteres(renseignes)}
          </span>
        </div>
        <div
          role="progressbar"
          aria-label={t.criteresRenseignes}
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
            const valeur = valeurCritere(d, critere, langue)
            const libelle = t.libelles[critere]
            const signale = critere === 'contraintes' && d.contraintesSignalees.length > 0
            return (
              <div key={critere} className="flex items-start gap-3 border-b border-sable py-2.5">
                <dt className="w-[104px] shrink-0 pt-px text-[13px] font-bold text-taupe-clair">
                  {libelle}
                </dt>
                <dd
                  className={cn(
                    'm-0 flex-1 text-sm leading-snug font-semibold',
                    !valeur && 'font-normal text-taupe-clair italic',
                  )}
                >
                  {valeur ?? t.aPreciser}
                  {signale && (
                    <span className="block text-xs font-bold text-rouille not-italic">
                      {t.signale}
                    </span>
                  )}
                </dd>
                <button
                  type="button"
                  aria-label={`${t.modifier}${langue === 'en' ? ': ' : ' : '}${libelle}`}
                  onClick={() => onModifier(`${libelle}${langue === 'en' ? ': ' : ' : '}`)}
                  className="text-[13px] font-bold text-rouille hover:text-encre hover:underline"
                >
                  {t.modifier}
                </button>
              </div>
            )
          })}
        </dl>
        {terminee ? (
          <>
            <p className="m-0 text-xs leading-normal text-taupe-clair">{t.rappelTerminee}</p>
            <p
              role="status"
              className="m-0 flex min-h-[50px] items-center justify-center rounded-full bg-vert text-[15px] font-extrabold text-white"
            >
              {t.transmiseConseiller}
            </p>
          </>
        ) : (
          <>
            <p className="m-0 text-xs leading-normal text-taupe-clair">{t.prochaineEtape}</p>
            <button
              type="button"
              disabled
              className="min-h-[50px] rounded-full bg-lin text-[15px] font-extrabold text-taupe"
            >
              {reflexion && renseignes >= 7 ? t.enPreparation : t.apresVosReponses}
            </button>
          </>
        )}
      </div>
    </div>
  )
}
