import { Link } from '@tanstack/react-router'
import { formatEuros, labelType, MOIS } from '@/data/catalogue'
import type { DestinationPublique } from '@/features/catalogue/catalogue'

/** Carte d'une destination dans les listes (photo, température du mois, prix « à partir de »). */
export function CarteDestination({ d, mois }: { d: DestinationPublique; mois?: number }) {
  const m = mois ?? d.moisIdeal
  const temperature = d.temperatures[m]
  const details = [
    ...d.types.map(labelType),
    ...(d.prixAPartirDe === null ? [] : [`dès ${formatEuros(d.prixAPartirDe)} / pers.`]),
  ]
  return (
    <Link
      to="/destinations/$slug"
      params={{ slug: d.slug }}
      className="group flex flex-col gap-3 rounded-[22px] focus-visible:outline-offset-4"
    >
      <div className="relative h-[220px] overflow-hidden rounded-[22px]">
        <img
          src={d.photo}
          alt={d.alt}
          className="block size-full object-cover transition-transform duration-300 group-hover:scale-105"
        />
        {temperature !== null && (
          <span className="absolute top-3 left-3 rounded-full bg-white px-3 py-1.5 text-xs font-extrabold">
            {temperature} °C en {MOIS[m]}
          </span>
        )}
      </div>
      <div className="flex flex-col gap-1 px-1">
        <span className="font-display text-[22px] leading-tight font-semibold group-hover:text-terre">
          {d.nom}
        </span>
        <span className="text-sm text-taupe">{details.join(' · ')}</span>
      </div>
    </Link>
  )
}
