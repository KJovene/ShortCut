import { createFileRoute, Link } from '@tanstack/react-router'
import { z } from 'zod'
import { CarteDestination } from '@/components/ui/CarteDestination'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { MOIS, TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { cn } from '@/lib/cn'
import { chargerCatalogue, type CatalogueDestination } from '@/data/catalogue-api'

const TYPES = TYPES_DE_VOYAGE.map((t) => t.valeur) as [TypeDeVoyage, ...TypeDeVoyage[]]

const filtresSchema = z.object({
  mois: z.coerce.number().int().min(0).max(11).optional().catch(undefined),
  temperature: z.coerce.number().int().min(0).max(40).optional().catch(undefined),
  type: z.enum(TYPES).optional().catch(undefined),
})

export const Route = createFileRoute('/destinations/')({
  validateSearch: filtresSchema,
  loader: () => chargerCatalogue(),
  head: () => ({
    meta: [
      { title: 'Destinations · Où partir, et quand ?' },
      {
        name: 'description',
        content:
          'Toutes nos destinations, filtrables par mois, température moyenne et type de voyage.',
      },
    ],
  }),
  component: DestinationsPage,
})

const TEMPERATURES = [15, 20, 25]

const selectClasse =
  'min-h-11 rounded-xl border-[1.5px] border-beige bg-white px-3 text-[15px] font-semibold text-encre hover:border-encre'

function DestinationsPage() {
  const catalogue = Route.useLoaderData() as CatalogueDestination[]
  const { mois, temperature, type } = Route.useSearch()
  const navigate = Route.useNavigate()
  const ouvrirAssistant = useOuvrirAssistant()

  const resultats = catalogue.filter(
    (d) =>
      (type === undefined || d.types.includes(type)) &&
      (temperature === undefined || d.temperatures[mois ?? d.moisIdeal] >= temperature),
  )

  const changer = (filtres: Partial<z.infer<typeof filtresSchema>>) =>
    navigate({ search: (s) => ({ ...s, ...filtres }), replace: true, resetScroll: false })

  return (
    <>
      <EnTetePage
        surtitre="Destinations"
        titre={
          <>
            Où partir, <em className="font-normal text-terre">et quand</em> ?
          </>
        }
      >
        <p className="m-0 max-w-[640px]">
          Choisissez un mois et une température : nous vous montrons les destinations dont le climat
          convient, d'après les relevés saisis par nos conseillers.
        </p>
      </EnTetePage>

      <section aria-label="Filtres" className="mx-auto max-w-[1376px] px-4 sm:px-8">
        <div className="flex flex-wrap items-end gap-3 rounded-[22px] bg-white p-3 shadow-[0_8px_24px_rgba(43,29,20,0.08)] sm:gap-4 sm:p-4">
          <label className="flex flex-col gap-1 text-xs font-bold text-taupe">
            Mois
            <select
              className={selectClasse}
              value={mois ?? ''}
              onChange={(e) =>
                changer({ mois: e.target.value === '' ? undefined : Number(e.target.value) })
              }
            >
              <option value="">Le meilleur mois</option>
              {MOIS.map((m, i) => (
                <option key={m} value={i}>
                  {m.charAt(0).toUpperCase() + m.slice(1)}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1 text-xs font-bold text-taupe">
            Température moyenne
            <select
              className={selectClasse}
              value={temperature ?? ''}
              onChange={(e) =>
                changer({ temperature: e.target.value === '' ? undefined : Number(e.target.value) })
              }
            >
              <option value="">Peu importe</option>
              {TEMPERATURES.map((t) => (
                <option key={t} value={t}>
                  Au moins {t} °C
                </option>
              ))}
            </select>
          </label>
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
