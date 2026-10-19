import { createFileRoute } from '@tanstack/react-router'
import { useState } from 'react'
import { SiteHeader } from '@/components/layout/SiteHeader'
import { IconFlecheBas } from '@/components/ui/icons'
import {
  TYPES_DE_VOYAGE,
  type TypeDeVoyage,
} from '@/data/catalogue'
import { chargerCatalogue, type CatalogueDestination } from '@/data/catalogue-api'
import { BandeauVoyages } from '@/features/accueil/BandeauVoyages'
import { CarteDuMonde } from '@/features/accueil/CarteDuMonde'
import { SectionAssistant } from '@/features/accueil/SectionAssistant'
import { metaPage, pageQuery } from '@/features/pages/pageDuSite'
import { cn } from '@/lib/cn'

export const Route = createFileRoute('/')({
  loader: async ({ context }) => ({
    catalogue: await chargerCatalogue(),
    page: await context.queryClient.ensureQueryData(pageQuery('accueil')).catch(() => null),
  }),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData?.page ?? null, {
      titre: 'Agence de voyage sur mesure · Votre voyage chiffré en 2 minutes',
      description:
        "Décrivez vos envies à notre assistant : il vous propose un voyage complet et chiffré, qu'un conseiller confirme ensuite.",
    }),
  }),
  component: HomePage,
})

const FILTRES: { valeur: TypeDeVoyage | 'tous'; label: string }[] = [
  { valeur: 'tous', label: 'Tous' },
  ...TYPES_DE_VOYAGE.map((t) => ({ valeur: t.valeur, label: t.pluriel })),
]

function HomePage() {
  const { catalogue } = Route.useLoaderData() as { catalogue: CatalogueDestination[] }
  const [type, setType] = useState<TypeDeVoyage | 'tous'>('tous')
  const [activeSlug, setActiveSlug] = useState('santorin')
  const active = catalogue.find((d) => d.slug === activeSlug) ?? catalogue[0]

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
              {catalogue.length} destinations sur mesure, partout dans le monde
            </span>
            <h1
              id="hero-titre"
              className="m-0 font-display text-[34px] leading-[1.02] font-semibold tracking-[-0.8px] sm:text-[54px] sm:tracking-[-1.5px]"
            >
              Où irez-vous <em className="font-normal text-terre">chercher le soleil</em> ?
            </h1>
          </div>
          <div
            role="group"
            aria-label="Filtrer par type de voyage"
            className="scrollbar-none -mr-4 flex gap-1.5 overflow-x-auto pr-4 sm:mr-0 sm:flex-wrap sm:pr-0"
          >
            {FILTRES.map((f) => {
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
          <CarteDuMonde
            destinations={catalogue}
            active={active}
            type={type}
            onActiver={setActiveSlug}
          />
        </div>

        <a
          href="#voyages"
          className="absolute bottom-2 left-1/2 flex min-h-11 -translate-x-1/2 items-center gap-2 px-4 text-sm font-bold text-taupe hover:text-encre sm:bottom-[18px]"
        >
          Tous les voyages
          <IconFlecheBas size={18} />
        </a>
      </section>

      <BandeauVoyages
        destinations={catalogue}
        active={active.slug}
        type={type}
        onActiver={setActiveSlug}
      />

      <SectionAssistant
        suggestion={active}
        photos={['santorin', 'bali', 'marrakech', 'islande']
          .map((slug) => catalogue.find((d) => d.slug === slug))
          .filter((d): d is CatalogueDestination => d !== undefined)}
      />
    </>
  )
}
