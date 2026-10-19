import { Link } from '@tanstack/react-router'
import { formatEuros, labelType, MOIS, prixAPartirDe, type Destination } from '@/data/catalogue'

/** Carte d'une destination dans les listes (photo, température du mois, prix « à partir de »). */
export function CarteDestination({ d, mois }: { d: Destination; mois?: number }) {
  const m = mois ?? d.moisIdeal
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
        <span className="absolute top-3 left-3 rounded-full bg-white px-3 py-1.5 text-xs font-extrabold">
          {d.temperatures[m]} °C en {MOIS[m]}
        </span>
      </div>
      <div className="flex flex-col gap-1 px-1">
        <span className="font-display text-[22px] leading-tight font-semibold group-hover:text-terre">
          {d.nom}
        </span>
        <span className="text-sm text-taupe">
          {d.types.map(labelType).join(' · ')} · dès {formatEuros(prixAPartirDe(d))} / pers.
        </span>
      </div>
    </Link>
  )
}
