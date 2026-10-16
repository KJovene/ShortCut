import { Link } from '@tanstack/react-router'
import { useRef } from 'react'
import { IconChevronDroite, IconChevronGauche } from '@/components/ui/icons'
import {
  formatEuros,
  labelType,
  MOIS,
  prixAPartirDe,
  type Destination,
  type TypeDeVoyage,
} from '@/data/catalogue'
import { cn } from '@/lib/cn'
import { estVisible } from './carte'

interface Props {
  destinations: Destination[]
  active: string
  type: TypeDeVoyage | 'tous'
  onActiver: (slug: string) => void
}

/** Bandeau des envies : tous les voyages de la carte, à faire défiler. */
export function BandeauVoyages({ destinations, active, type, onActiver }: Props) {
  const liste = useRef<HTMLUListElement>(null)
  const defiler = (sens: 1 | -1) =>
    liste.current?.scrollBy({ left: sens * 504, behavior: 'smooth' })

  return (
    <section id="voyages" aria-labelledby="bandeau-titre" className="bg-brun text-creme">
      <div className="mx-auto flex max-w-[1376px] flex-col gap-4 py-8 pl-4 sm:gap-[22px] sm:px-8 sm:pt-12 sm:pb-[52px]">
        <div className="flex flex-wrap items-end justify-between gap-4 pr-4 sm:pr-0">
          <div className="flex flex-col gap-1.5">
            <span className="text-[13px] font-extrabold tracking-[1.5px] text-ocre uppercase">
              Le bandeau des envies
            </span>
            <h2
              id="bandeau-titre"
              className="m-0 font-display text-[28px] font-semibold tracking-[-1px] sm:text-[40px]"
            >
              Tous les voyages de la carte
            </h2>
          </div>
          <div className="hidden gap-2 sm:flex">
            <button
              type="button"
              aria-label="Voyages précédents"
              onClick={() => defiler(-1)}
              className="flex size-12 items-center justify-center rounded-full border-[1.5px] border-[#8A6A55] hover:bg-white/10"
            >
              <IconChevronGauche />
            </button>
            <button
              type="button"
              aria-label="Voyages suivants"
              onClick={() => defiler(1)}
              className="flex size-12 items-center justify-center rounded-full bg-creme text-brun hover:bg-white"
            >
              <IconChevronDroite />
            </button>
          </div>
        </div>
        <ul
          ref={liste}
          className="scrollbar-none m-0 flex list-none gap-3 overflow-x-auto p-1 pr-4 pb-1.5 sm:-mr-8 sm:gap-4"
        >
          {destinations.map((d) => (
            <li key={d.slug} className="shrink-0">
              <Link
                to="/destinations/$slug"
                params={{ slug: d.slug }}
                onMouseEnter={() => onActiver(d.slug)}
                onFocus={() => onActiver(d.slug)}
                className={cn(
                  'group relative block h-60 w-[180px] overflow-hidden rounded-[18px] text-white outline-3 outline-offset-3 transition-opacity sm:h-80 sm:w-[236px] sm:rounded-[22px]',
                  d.slug === active ? 'outline-ocre' : 'outline-transparent',
                  !estVisible(d, type) && 'opacity-40',
                )}
              >
                <img
                  src={d.photo}
                  alt=""
                  className="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-105"
                />
                <span className="absolute top-3 left-3 rounded-full bg-white px-2.5 py-1 text-xs font-extrabold text-encre">
                  {d.temperatures[d.moisIdeal]} °C en {MOIS[d.moisIdeal]}
                </span>
                <span className="absolute inset-x-0 bottom-0 flex flex-col gap-0.5 bg-linear-to-t from-encre/90 to-encre/0 px-4 pt-10 pb-4">
                  <span className="font-display text-lg font-semibold sm:text-[22px]">{d.nom}</span>
                  <span className="text-xs text-sable sm:text-[13px]">
                    {labelType(d.types[0])} · dès {formatEuros(prixAPartirDe(d))}
                  </span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </section>
  )
}
