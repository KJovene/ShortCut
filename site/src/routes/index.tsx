import { useQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { useState } from 'react'
import { SiteHeader } from '@/components/layout/SiteHeader'
import { IconFlecheBas } from '@/components/ui/icons'
import { TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'
import { BandeauVoyages } from '@/features/accueil/BandeauVoyages'
import { CarteDuMonde } from '@/features/accueil/CarteDuMonde'
import { SectionAssistant } from '@/features/accueil/SectionAssistant'
import { destinationsQuery, estSurCarte } from '@/features/catalogue/catalogue'
import { metaPage, pageQuery } from '@/features/pages/pageDuSite'
import { TEXTES } from '@/features/pages/textes'
import { cn } from '@/lib/cn'
import { langueDuSite, typesDeVoyage, useLangue } from '@/lib/langue'

export const Route = createFileRoute('/')({
  // Titre et description pour les moteurs : contenus du site. L'accueil ne dépend pas de leur chargement,
  // ni de celui des destinations : sans elles, la carte reste vide mais l'assistant répond.
  loader: ({ context }) => {
    void context.queryClient.prefetchQuery(destinationsQuery())
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
                  key={f.valeur}
                  type="button"
                  aria-pressed={actif}
                  onClick={() => setType(f.valeur)}
                  className={cn(
                    'min-h-[38px] shrink-0 cursor-pointer rounded-full border-[1.5px] px-3.5 text-[13px] font-bold sm:min-h-[42px] sm:px-4 sm:text-sm',
                    actif
                      ? 'border-encre bg-encre text-creme'
                      : 'border-beige bg-white hover:border-encre',
                  )}
                >
                  {f.label}
                </button>
              )
            })}
          </div>
        </div>

        <div className="flex min-h-0 flex-1 flex-col items-center justify-center pb-14 md:px-8 md:pt-3">
          {active && (
            <CarteDuMonde destinations={surCarte} active={active} type={type} onActiver={activer} />
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

      {destinations.length > 0 && (
        <BandeauVoyages
          destinations={destinations}
          active={survolee?.slug ?? ''}
          type={type}
          onActiver={activer}
        />
      )}

      <SectionAssistant suggestion={survolee} photos={photos} />
    </>
  )
}
