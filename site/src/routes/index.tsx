import { useQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { useState } from 'react'
import { SiteHeader } from '@/components/layout/SiteHeader'
import { IconFlecheBas } from '@/components/ui/icons'
import { TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { BandeauVoyages } from '@/features/accueil/BandeauVoyages'
import { type Theme, voyagesDuTheme } from '@/features/accueil/carte'
import { CarteDuMonde } from '@/features/accueil/CarteDuMonde'
import { SectionAssistant } from '@/features/accueil/SectionAssistant'
import {
  destinationsMisesEnAvantQuery,
  destinationsQuery,
  estSurCarte,
} from '@/features/catalogue/catalogue'
import { metaPage, pageQuery } from '@/features/pages/pageDuSite'
import { TEXTES } from '@/features/pages/textes'
import { cn } from '@/lib/cn'
import { langueDuSite, typesDeVoyage, useLangue } from '@/lib/langue'

export const Route = createFileRoute('/')({
  // Titre et description pour les moteurs : contenus du site. L'accueil ne dépend pas de leur chargement,
  // ni de celui des destinations : sans elles, la carte reste vide mais l'assistant répond.
  loader: ({ context }) => {
    void context.queryClient.prefetchQuery(destinationsMisesEnAvantQuery())
    return context.queryClient.ensureQueryData(pageQuery('accueil')).catch(() => null)
  },
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, TEXTES[langueDuSite()].accueil.meta),
  }),
  component: HomePage,
})

function HomePage() {
  const langue = useLangue()
  const t = TEXTES[langue].accueil
  const [type, setType] = useState<TypeDeVoyage | 'tous'>('tous')
  /** Voyage survolé, sur la carte ou dans le bandeau ; à défaut, l'épingle active. */
  const [activeSlug, setActiveSlug] = useState<string>()
  /** Épingle active : la carte la garde quand le bandeau survole un voyage qui n'est pas sur la carte. */
  const [slugCarte, setSlugCarte] = useState<string>()
  const { data: catalogue = [] } = useQuery(destinationsQuery(langue))
  // Titre des contenus du site ; sans l'API, celui des maquettes.
  const { data: page } = useQuery(pageQuery('accueil', langue))
  const filtres: { valeur: TypeDeVoyage | 'tous'; label: string }[] = [
    { valeur: 'tous', label: t.tous },
    ...TYPES_DE_VOYAGE.map((type) => ({ valeur: type.valeur, label: typesDeVoyage(type, langue) })),
  ]
  // Destinations « mises en avant » dans le backoffice d'abord : la carte s'ouvre sur la première,
  // le bandeau et les polaroïds de l'assistant commencent par elles.
  const destinations = [
    ...catalogue.filter((d) => d.miseEnAvant),
    ...catalogue.filter((d) => !d.miseEnAvant),
  ]
  const surCarte = destinations.filter(estSurCarte)
  const active = surCarte.find((d) => d.slug === slugCarte) ?? surCarte[0]
  const activer = (slug: string) => {
    setActiveSlug(slug)
    if (surCarte.some((d) => d.slug === slug)) setSlugCarte(slug)
  }
  const survolee = destinations.find((d) => d.slug === activeSlug) ?? active
  const photos = destinations.filter((d) => d.photo).slice(0, 4)
/**
 * Les deux thèmes de la carte. Leur titre vient des contenus du site (page « accueil » : « Titre » pour les voyages
 * chauds, « Titre des voyages froids ») ; sans l'API ou sans titre saisi, celui des maquettes.
 */
const THEMES: { valeur: Theme; label: string; titreParDefaut: string }[] = [
  {
    valeur: 'chaud',
    label: 'Voyages chauds',
    titreParDefaut: 'Où irez-vous *chercher le soleil* ?',
  },
  {
    valeur: 'froid',
    label: 'Voyages froids',
    titreParDefaut: 'Où irez-vous *chercher la fraîcheur* ?',
  },
]

function HomePage() {
  const [theme, setTheme] = useState<Theme>('chaud')
  const [activeSlug, setActiveSlug] = useState('santorin')
  // Carte et bandeau : seulement les voyages mis en avant dans le backoffice, du thème choisi, 10 au plus.
  const { data: misesEnAvant = [], isPending } = useQuery(destinationsMisesEnAvantQuery())
  const voyages = voyagesDuTheme(misesEnAvant, theme)
  const surCarte = voyages.filter(estSurCarte)
  const active = surCarte.find((d) => d.slug === activeSlug) ?? surCarte[0]
  const { data: page } = useQuery(pageQuery('accueil'))
  const titre =
    (theme === 'chaud' ? page?.titre : page?.titreFroid) ||
    THEMES.find((t) => t.valeur === theme)!.titreParDefaut

  // Polaroïds de la section de l'assistant : ces destinations si elles sont publiées, sinon les premières.
  const { data: destinations = [] } = useQuery(destinationsQuery())
  const parSlug = (slug: string) => destinations.find((d) => d.slug === slug)
  const photos = ['santorin', 'bali', 'marrakech', 'islande'].map(
    (slug, i) => parSlug(slug) ?? destinations[i],
  )

  return (
    <>
      <section
        aria-labelledby="hero-titre"
        className="relative flex h-[max(640px,100svh)] flex-col md:h-[clamp(720px,100vh,960px)]"
      >
        <SiteHeader />

        <div className="mx-auto flex w-full max-w-[1376px] flex-wrap items-end justify-between gap-2.5 px-4 pb-3 sm:gap-4 sm:px-8 sm:pt-1 sm:pb-0">
          <div className="flex flex-col gap-1.5">
            <span className="hidden text-[13px] font-extrabold tracking-[1.5px] text-rouille uppercase sm:block">
              {destinations.length > 0 ? t.nbDestinations(destinations.length) : t.desDestinations}
            </span>
            <h1
              id="hero-titre"
              aria-live="polite"
              className="m-0 font-display text-[34px] leading-[1.02] font-semibold tracking-[-0.8px] sm:text-[54px] sm:tracking-[-1.5px]"
            >
              <TitreMisEnValeur texte={page?.titre ?? t.titreParDefaut} />
            </h1>
          </div>
          <div
            role="group"
            aria-label={t.filtrer}
            className="scrollbar-none -mr-4 flex gap-1.5 overflow-x-auto pr-4 sm:mr-0 sm:flex-wrap sm:pr-0"
          >
            {filtres.map((f) => {
              const actif = f.valeur === type
              return (
                <button
                  key={t.valeur}
                  type="button"
                  aria-pressed={actif}
                  onClick={() => setTheme(t.valeur)}
                  className={cn(
                    'min-h-[38px] cursor-pointer rounded-full px-4 text-[13px] font-bold sm:min-h-[42px] sm:px-5 sm:text-sm',
                    actif ? 'bg-encre text-creme' : 'text-encre hover:bg-sable',
                  )}
                >
                  {t.label}
                </button>
              )
            })}
          </div>
        </div>

        <div className="flex min-h-0 flex-1 flex-col items-center justify-center pb-14 md:px-8 md:pt-3">
          {active ? (
            <CarteDuMonde destinations={surCarte} active={active} onActiver={setActiveSlug} />
          ) : (
            // Rien à montrer (ou API injoignable) : l'assistant reste la porte d'entrée.
            !isPending && (
              <p className="m-0 max-w-md px-4 text-center text-lg text-taupe">
                Aucun voyage {theme === 'froid' ? 'au frais' : 'au soleil'} à la une pour le moment.
                Décrivez vos envies à notre assistant : il vous en propose un sur mesure.
              </p>
            )
          )}
        </div>

        <a
          href="#voyages"
          className="absolute bottom-2 left-1/2 flex min-h-11 -translate-x-1/2 items-center gap-2 px-4 text-sm font-bold text-taupe hover:text-encre sm:bottom-[18px]"
        >
          {t.tousLesVoyages}
          <IconFlecheBas size={18} />
        </a>
      </section>

      {voyages.length > 0 && (
        <BandeauVoyages
          destinations={voyages}
          active={active?.slug ?? activeSlug}
          onActiver={setActiveSlug}
        />
      )}

      <SectionAssistant suggestion={active} photos={photos} />
    </>
  )
}
