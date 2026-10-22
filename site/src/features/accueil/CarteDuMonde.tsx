import { Link } from '@tanstack/react-router'
import { useEffect, useRef, useState, type CSSProperties, type FocusEvent } from 'react'
import { IconFleche } from '@/components/ui/icons'
import { formatEuros, MOIS } from '@/data/catalogue'
import {
  libelleTypePrincipal,
  type DestinationPublique,
  type DestinationSurCarte,
} from '@/features/catalogue/catalogue'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { cn } from '@/lib/cn'
import { position } from './carte'

interface Props {
  destinations: DestinationSurCarte[]
  active: DestinationSurCarte
  onActiver: (slug: string) => void
}

/** Délai avant de fermer la fiche : le temps de passer du point à la fiche sans qu'elle disparaisse. */
const DELAI_FERMETURE = 150

interface Survol {
  onMouseEnter: () => void
  onMouseLeave: () => void
  onFocus: () => void
  onBlur: (e: FocusEvent<HTMLElement>) => void
}

function Epingle({
  d,
  active,
  survol,
  onActiver,
}: {
  d: DestinationSurCarte
  active: boolean
  survol: Survol
  onActiver: () => void
}) {
  const { x, y } = position(d)
  return (
    <button
      type="button"
      aria-label={`${d.nom}, ${libelleTypePrincipal(d).toLowerCase()}`}
      aria-pressed={active}
      {...survol}
      onClick={onActiver}
      className={cn(
        'group absolute flex -translate-1/2 cursor-pointer items-center justify-center rounded-full',
        active ? 'z-10 size-[60px] md:size-[68px]' : 'z-[2] size-11 md:size-12',
      )}
      style={{ left: `${x}%`, top: `${y}%` }}
    >
      <span
        aria-hidden="true"
        className={cn(
          'absolute animate-pin-pulse rounded-full',
          active ? 'size-14 bg-ciel md:size-16' : 'size-[38px] bg-ciel/60 md:size-[42px]',
        )}
      />
      <img
        src={d.photo}
        alt=""
        className={cn(
          'relative box-border rounded-full border-[3px] object-cover shadow-[0_6px_16px_rgba(22,35,63,0.28)] transition-transform duration-200 group-hover:scale-115',
          active ? 'size-14 border-terre md:size-16' : 'size-[38px] border-white md:size-[42px]',
        )}
      />
    </button>
  )
}

function CarteVoyage({
  d,
  className,
  style,
  survol,
}: {
  d: DestinationPublique
  className?: string
  style?: CSSProperties
  survol?: Omit<Survol, 'onFocus'> & { onFocus: () => void }
}) {
  const ouvrirAssistant = useOuvrirAssistant()
  const mois = MOIS[d.moisIdeal]
  return (
    <article
      aria-live="polite"
      style={style}
      {...survol}
      className={cn(
        'overflow-hidden rounded-3xl bg-white shadow-[0_30px_70px_rgba(22,35,63,0.28)]',
        className,
      )}
    >
      <div className="relative h-[150px] overflow-hidden md:h-[210px]">
        <img src={d.photo} alt={d.alt} className="block size-full object-cover" />
        {d.temperatures[d.moisIdeal] !== null && (
          <span className="absolute right-3 bottom-3 rounded-full bg-white px-3 py-0.5 font-display text-[22px] font-semibold md:text-[26px]">
            {d.temperatures[d.moisIdeal]} °C
          </span>
        )}
      </div>
      <div className="hidden grid-cols-3 gap-1 px-1 pt-1 md:grid">
        {['0% 70%', '50% 30%', '100% 90%'].map((pos) => (
          <img
            key={pos}
            src={d.photo}
            alt=""
            className="block h-[58px] w-full rounded-[10px] object-cover"
            style={{ objectPosition: pos }}
          />
        ))}
      </div>
      <div className="flex flex-col gap-2 px-[18px] pt-3.5 pb-[18px]">
        <span className="text-xs font-extrabold text-rouille">
          {libelleTypePrincipal(d)} · idéal en {mois}
        </span>
        <h2 className="m-0 font-display text-2xl leading-tight font-semibold md:text-[26px]">
          <Link to="/destinations/$slug" params={{ slug: d.slug }} className="hover:text-terre">
            {d.nom}
          </Link>
        </h2>
        <p className="m-0 text-sm leading-relaxed text-taupe">{d.accroche}</p>
        <div className="mt-1 flex items-center justify-between gap-2.5">
          {d.prixAPartirDe !== null && (
            <span className="text-[13px] text-taupe">
              dès <strong className="text-[17px] text-encre">{formatEuros(d.prixAPartirDe)}</strong>{' '}
              / pers.
            </span>
          )}
          <button
            type="button"
            onClick={() =>
              ouvrirAssistant(`Un voyage à ${d.ville} en ${mois}, `, { nouveauVoyage: true })
            }
            className="flex min-h-11 items-center rounded-full bg-terre px-[18px] text-sm font-extrabold text-white hover:bg-rouille"
          >
            Construire ce voyage
          </button>
        </div>
      </div>
    </article>
  )
}

/**
 * Carte du monde interactive : une épingle photo par voyage. Sur ordinateur, la fiche s'ouvre au survol (ou au
 * focus) d'un point et se ferme quand on quitte le point et la fiche ; sur mobile, la fiche du point touché
 * reste en bas de la carte.
 */
export function CarteDuMonde({ destinations, active, onActiver }: Props) {
  const { x } = position(active)
  const defilement = useRef<HTMLDivElement>(null)
  const [ouverte, setOuverte] = useState<string | null>(null)
  const minuteur = useRef<number | undefined>(undefined)
  const fiche = destinations.find((d) => d.slug === ouverte)

  const garder = () => window.clearTimeout(minuteur.current)
  const fermer = () => {
    garder()
    minuteur.current = window.setTimeout(() => setOuverte(null), DELAI_FERMETURE)
  }
  const ouvrir = (slug: string) => {
    garder()
    setOuverte(slug)
    onActiver(slug)
  }
  // Le focus qui passe du point à la fiche (ou dans la fiche) la garde ouverte.
  const quitter = (e: FocusEvent<HTMLElement>) => {
    if (!e.currentTarget.parentElement?.contains(e.relatedTarget as Node | null)) fermer()
  }
  useEffect(() => () => window.clearTimeout(minuteur.current), [])

  // Échap ferme la fiche.
  useEffect(() => {
    if (!ouverte) return
    const touche = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOuverte(null)
    }
    document.addEventListener('keydown', touche)
    return () => document.removeEventListener('keydown', touche)
  }, [ouverte])

  // Sur mobile, la carte glisse horizontalement : on centre l'épingle active.
  useEffect(() => {
    const el = defilement.current
    if (!el || el.scrollWidth <= el.clientWidth) return
    el.scrollTo({ left: (x / 100) * el.scrollWidth - el.clientWidth / 2, behavior: 'smooth' })
  }, [x])

  const epingles = destinations.map((d) => (
    <Epingle
      key={d.slug}
      d={d}
      active={d.slug === active.slug}
      survol={{
        onMouseEnter: () => ouvrir(d.slug),
        onMouseLeave: fermer,
        onFocus: () => ouvrir(d.slug),
        onBlur: quitter,
      }}
      onActiver={() => ouvrir(d.slug)}
    />
  ))

  return (
    <>
      {/* Ordinateur : carte entière, fiche à côté de l'épingle. */}
      <div className="relative hidden aspect-[360/142] w-[min(100%,calc((clamp(720px,100vh,960px)-250px)*2.535))] md:block">
        <img
          src="/images/carte-du-monde.svg"
          alt=""
          className="pointer-events-none absolute inset-0 block size-full"
        />
        {epingles}
        <BulleAssistant />
        {fiche && (
          <CarteVoyage
            d={fiche}
            className="absolute z-20 w-[360px]"
            // La fiche se place du côté où il reste de la place.
            style={(() => {
              const p = position(fiche)
              return {
                left: `${p.x}%`,
                top: `${p.y}%`,
                transform: `translate(${p.x > 55 ? 'calc(-100% - 44px)' : '44px'}, ${p.y < 35 ? '-18%' : p.y > 60 ? '-86%' : '-50%'})`,
              }
            })()}
            survol={{
              onMouseEnter: garder,
              onMouseLeave: fermer,
              onFocus: garder,
              onBlur: quitter,
            }}
          />
        )}
      </div>

      {/* Mobile : carte à faire glisser, fiche en bas. */}
      <div className="relative flex min-h-0 w-full flex-1 flex-col md:hidden">
        <div
          ref={defilement}
          aria-label="Carte des voyages, glissez pour explorer"
          className="scrollbar-none relative flex-1 overflow-x-auto bg-[#EFE8DA]"
        >
          <div className="relative aspect-[360/142] h-full min-h-[300px]">
            <img
              src="/images/carte-du-monde.svg"
              alt=""
              className="pointer-events-none absolute inset-0 block size-full"
            />
            {epingles}
          </div>
        </div>
        <span className="absolute top-2.5 left-3 rounded-full bg-creme/90 px-2.5 py-1 text-[11px] font-bold text-taupe">
          Glissez pour explorer
        </span>
        <CarteVoyageMobile d={active} />
      </div>
    </>
  )
}

/**
 * Bulle de l'assistant, en bas à gauche de la carte (maquette G3) : pour un voyage qui n'est pas sur la carte.
 * Les épingles et la fiche passent au-dessus d’elle. À partir de 1280 px seulement : plus étroite, la carte
 * serait en grande partie masquée (le bouton « Parler à l’assistant » reste là).
 */
function BulleAssistant() {
  const ouvrirAssistant = useOuvrirAssistant()
  return (
    <aside
      aria-labelledby="bulle-assistant-titre"
      className="absolute top-[64%] left-[1.5%] z-[6] hidden w-[316px] flex-col gap-3 xl:flex rounded-[24px_24px_24px_6px] bg-encre p-[18px] text-creme shadow-[0_22px_50px_rgba(22,35,63,0.3)]"
    >
      <div className="flex items-center gap-3">
        <img src="/new-logos/detour-avatar.svg" alt="" className="size-11 shrink-0" />
        <h2
          id="bulle-assistant-titre"
          className="m-0 font-display text-xl leading-tight font-extrabold tracking-[-0.02em]"
        >
          Votre idée n’est pas sur la carte ?
        </h2>
      </div>
      <p className="m-0 text-sm leading-normal text-ciel">
        Racontez-la à notre assistant : il compose une première proposition, un conseiller la
        vérifie et la signe.
      </p>
      <button
        type="button"
        onClick={() => ouvrirAssistant(undefined, { nouveauVoyage: true })}
        className="flex min-h-11 items-center gap-2 self-start rounded-full bg-creme px-[18px] text-[15px] font-semibold text-encre hover:bg-sable"
      >
        Démarrer la conversation
        <IconFleche size={18} strokeWidth={2.2} />
      </button>
    </aside>
  )
}

function CarteVoyageMobile({ d }: { d: DestinationPublique }) {
  const ouvrirAssistant = useOuvrirAssistant()
  return (
    <article
      aria-live="polite"
      className="relative z-10 mx-3 -mt-24 flex overflow-hidden rounded-[22px] bg-white shadow-[0_18px_44px_rgba(22,35,63,0.25)]"
    >
      <img src={d.photo} alt={d.alt} className="block w-[120px] shrink-0 object-cover" />
      <div className="flex min-w-0 flex-1 flex-col gap-1 px-3.5 py-3">
        <span className="text-[11px] font-extrabold tracking-[0.8px] text-rouille">
          {libelleTypePrincipal(d)}
          {d.temperatures[d.moisIdeal] !== null &&
            ` · ${d.temperatures[d.moisIdeal]} °C en ${MOIS[d.moisIdeal]}`}
        </span>
        <h2 className="m-0 font-display text-xl font-semibold">
          <Link to="/destinations/$slug" params={{ slug: d.slug }}>
            {d.nom}
          </Link>
        </h2>
        <p className="m-0 text-xs leading-snug text-taupe">{d.accroche}</p>
        <div className="mt-1 flex items-center justify-between gap-1.5">
          {d.prixAPartirDe !== null && (
            <span className="text-xs text-taupe">
              dès <strong className="text-encre">{formatEuros(d.prixAPartirDe)}</strong>
            </span>
          )}
          <button
            type="button"
            onClick={() =>
              ouvrirAssistant(`Un voyage à ${d.ville} en ${MOIS[d.moisIdeal]}, `, {
                nouveauVoyage: true,
              })
            }
            className="flex min-h-10 items-center rounded-full bg-terre px-3.5 text-[13px] font-extrabold text-white"
          >
            Construire
          </button>
        </div>
      </div>
    </article>
  )
}
