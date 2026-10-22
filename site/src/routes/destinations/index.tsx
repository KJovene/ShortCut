import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute, Link } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { z } from 'zod'
import { CarteDestination } from '@/components/ui/CarteDestination'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { MOIS, TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'
import { destinationsQuery } from '@/features/catalogue/catalogue'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { decouperContenu, metaPage, pageQuery } from '@/features/pages/pageDuSite'
import { cn } from '@/lib/cn'
import { SautDuO } from '@/components/ui/TitreMisEnValeur'


const TYPES = TYPES_DE_VOYAGE.map((t) => t.valeur) as [TypeDeVoyage, ...TypeDeVoyage[]]

const filtresSchema = z.object({
  mois: z.coerce.number().int().min(0).max(11).optional().catch(undefined),
  temperature: z.coerce.number().int().min(0).max(40).optional().catch(undefined),
  type: z.enum(TYPES).optional().catch(undefined),
})

/** Textes de la page si son contenu du backoffice manque (masqué ou API indisponible) : le catalogue reste affiché. */
const SECOURS = {
  titre: 'Où partir, *et quand* ?',
  intro:
    "Choisissez un mois et une température : nous vous montrons les destinations dont le climat convient, d'après les relevés saisis par nos conseillers.",
  seoTitre: 'Destinations · Où partir, et quand ?',
  seoDescription:
    'Toutes nos destinations, filtrables par mois, température moyenne et type de voyage.',
}

export const Route = createFileRoute('/destinations/')({
  validateSearch: filtresSchema,
  loader: async ({ context }) => {
    const [, page] = await Promise.all([
      context.queryClient.ensureQueryData(destinationsQuery()),
      context.queryClient.ensureQueryData(pageQuery('destinations')).catch(() => null),
    ])
    return page
  },
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, { titre: SECOURS.seoTitre, description: SECOURS.seoDescription }),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: DestinationsPage,
})


function DestinationsPage() {
  const { mois, temperature, type } = Route.useSearch()
  const navigate = Route.useNavigate()
  const ouvrirAssistant = useOuvrirAssistant()
  const { data: destinations } = useSuspenseQuery(destinationsQuery())
  // Titre, introduction et photo : contenus du site, modifiables dans le backoffice.
  const page = Route.useLoaderData()
  const intro = page ? decouperContenu(page.contenu).intro : [SECOURS.intro]

  const resultats = destinations.filter(
    (d) =>
      (type === undefined || d.types.includes(type)) &&
      (temperature === undefined ||
        (d.temperatures[mois ?? d.moisIdeal] ?? -Infinity) >= temperature),
  )

  const changer = (filtres: Partial<z.infer<typeof filtresSchema>>) =>
    navigate({ search: (s) => ({ ...s, ...filtres }), replace: true, resetScroll: false })

  return (
    <>
      <EnTetePage
        surtitre="Destinations"
        titre={<TitreMisEnValeur texte={page?.titre ?? SECOURS.titre} />}
        photo={page?.photos[0] ? { src: page.photos[0], alt: 'Destinations' } : undefined}
      >
        {intro.map((paragraphe) => (
          <p key={paragraphe} className="m-0 max-w-[640px]">
            {paragraphe}
          </p>
        ))}
      </EnTetePage>

      <section aria-label="Filtres" className="mx-auto max-w-[1376px] px-4 sm:px-8">
          <div role="group" aria-label="Type de voyage" className="flex flex-wrap gap-1.5">
            {[{ valeur: undefined, pluriel: 'Tous' }, ...TYPES_DE_VOYAGE].map((t) => {
              const actif = t.valeur === type
              return (
                <button
                  key={t.pluriel}
                  type="button"
                  aria-pressed={actif}
                  onClick={() => changer({ type: t.valeur })}
                  className={cn(
                    'min-h-11 rounded-full border-[1.5px] px-4 text-sm font-bold',
                    actif
                      ? 'border-encre bg-encre text-creme'
                      : 'border-beige bg-white hover:border-encre',
                  )}
                >
                  {t.pluriel}
                </button>
              )
            })}
          </div>
      </section>

      <section aria-live="polite" className="mx-auto max-w-[1376px] px-4 py-10 sm:px-8 sm:pb-20">
        <p className="mt-0 mb-6 text-sm font-bold text-taupe">
          {resultats.length} destination{resultats.length > 1 ? 's' : ''}
          {mois !== undefined && ` en ${MOIS[mois]}`}
        </p>
        {resultats.length > 0 ? (
          <div className="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-x-6 gap-y-10">
            {resultats.map((d) => (
              <CarteDestination key={d.slug} d={d} mois={mois} />
            ))}
          </div>
        ) : (
          <div className="flex flex-col items-start gap-4 rounded-[22px] bg-sable p-6 sm:p-8">
            <h2 className="m-0 font-display text-2xl font-semibold">
              Aucune destination ne correspond
            </h2>
            <p className="m-0 text-taupe">
              Essayez un autre mois ou une température plus douce, ou décrivez votre envie à
              l'assistant : il cherche aussi les destinations voisines.
            </p>
            <div className="flex flex-wrap gap-2">
              <Link
                to="/destinations"
                search={{}}
                className="flex min-h-11 items-center rounded-full border-[1.5px] border-encre px-5 text-sm font-bold"
              >
                Effacer les filtres
              </Link>
              <button
                type="button"
                onClick={() => ouvrirAssistant()}
                className="flex min-h-11 items-center rounded-full bg-terre px-5 text-sm font-extrabold text-white"
              >
                Demander à l'assistant
              </button>
            </div>
          </div>
        )}
      </section>
    </>
  )
}
