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
import { metaPage } from '@/features/pages/meta'
import { TEXTES } from '@/features/pages/textes'
import { cn } from '@/lib/cn'
import { langueDuSite, useLangue } from '@/lib/langue'

export const Route = createFileRoute('/')({
  // L'accueil ne dépend pas du chargement des destinations : sans elles, la carte reste vide mais l'assistant répond.
  loader: ({ context }) => {
    void context.queryClient.prefetchQuery(destinationsMisesEnAvantQuery())
  },
  head: () => ({ meta: metaPage(TEXTES[langueDuSite()].accueil.meta) }),
  component: HomePage,
})

/** Les deux thèmes de la carte, chacun avec son titre (textes de l'accueil). */
const THEMES: Theme[] = ['chaud', 'froid']

function HomePage() {
  const langue = useLangue()
  const t = TEXTES[langue].accueil
  const [theme, setTheme] = useState<Theme>('chaud')
  const [activeSlug, setActiveSlug] = useState('santorin')
  // Carte et bandeau : seulement les voyages mis en avant dans le backoffice, du thème choisi, 10 au plus.
  const { data: misesEnAvant = [], isPending } = useQuery(destinationsMisesEnAvantQuery())
  const voyages = voyagesDuTheme(misesEnAvant, theme)
  const surCarte = voyages.filter(estSurCarte)
  const active = surCarte.find((d) => d.slug === activeSlug) ?? surCarte[0]

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
            <h1
              id="hero-titre"
              aria-live="polite"
              className="m-0 font-display text-[34px] leading-[1.02] font-semibold tracking-[-0.8px] sm:text-[54px] sm:tracking-[-1.5px]"
            >
              <TitreMisEnValeur texte={t.themes[theme].titre} />
            </h1>
            <p className="m-0 font-display text-lg font-semibold text-taupe sm:text-[22px]">
              <TitreMisEnValeur texte={t.accroche} />
            </p>
          </div>
          <div
            role="group"
            aria-label={t.themeDesVoyages}
            className="flex gap-1 rounded-full bg-white p-1 shadow-[0_2px_10px_rgba(22,35,63,0.08)]"
          >
            {THEMES.map((valeur) => {
              const actif = valeur === theme
              return (
                <button
                  key={valeur}
                  type="button"
                  aria-pressed={actif}
                  onClick={() => setTheme(valeur)}
                  className={cn(
                    'min-h-[38px] cursor-pointer rounded-full px-4 text-[13px] font-bold sm:min-h-[42px] sm:px-5 sm:text-sm',
                    actif ? 'bg-encre text-creme' : 'text-encre hover:bg-sable',
                  )}
                >
                  {t.themes[valeur].label}
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
                {t.themes[theme].aucunVoyage}
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
