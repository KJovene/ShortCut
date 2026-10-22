import { useQuery, useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute, Link } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { z } from 'zod'
import { CarteDestination } from '@/components/ui/CarteDestination'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'
import { destinationsQuery } from '@/features/catalogue/catalogue'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { decouperContenu, metaPage, pageQuery } from '@/features/pages/pageDuSite'
import { TEXTES } from '@/features/pages/textes'
import { cn } from '@/lib/cn'
import { langueDuSite, nomDuMois, typesDeVoyage, useLangue } from '@/lib/langue'

const TYPES = TYPES_DE_VOYAGE.map((t) => t.valeur) as [TypeDeVoyage, ...TypeDeVoyage[]]

const filtresSchema = z.object({
  mois: z.coerce.number().int().min(0).max(11).optional().catch(undefined),
  temperature: z.coerce.number().int().min(0).max(40).optional().catch(undefined),
  type: z.enum(TYPES).optional().catch(undefined),
})

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
    meta: metaPage(loaderData, TEXTES[langueDuSite()].destinations.meta),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: DestinationsPage,
})

const TEMPERATURES = [15, 20, 25]

const selectClasse =
  'min-h-11 rounded-xl border-[1.5px] border-beige bg-white px-3 text-[15px] font-semibold text-encre hover:border-encre'

const majuscule = (texte: string) => texte.charAt(0).toUpperCase() + texte.slice(1)

function DestinationsPage() {
  const langue = useLangue()
  const t = TEXTES[langue].destinations
  const { mois, temperature, type } = Route.useSearch()
  const navigate = Route.useNavigate()
  const ouvrirAssistant = useOuvrirAssistant()
  const { data: destinations } = useSuspenseQuery(destinationsQuery(langue))
  // Titre, introduction et photo : contenus du site, modifiables dans le backoffice (traduits par l'API en anglais).
  // Contenu masqué ou API indisponible : les textes par défaut prennent le relais, le catalogue reste affiché.
  const { data: page } = useQuery(pageQuery('destinations', langue))
  const intro = page ? decouperContenu(page.contenu).intro : [t.intro]

  const resultats = destinations.filter(
    (d) =>
      (type === undefined || d.types.includes(type)) &&
      (temperature === undefined ||
        (d.temperatures[mois ?? d.moisIdeal] ?? -Infinity) >= temperature),
  )

  const changer = (filtres: Partial<z.infer<typeof filtresSchema>>) =>
    navigate({ search: (s) => ({ ...s, ...filtres }), replace: true, resetScroll: false })

  const filtresType = [
    { valeur: undefined, label: TEXTES[langue].accueil.tous },
    ...TYPES_DE_VOYAGE.map((t) => ({ valeur: t.valeur, label: typesDeVoyage(t, langue) })),
  ]

  return (
    <>
      <EnTetePage
        surtitre={t.surtitre}
        titre={
          <TitreMisEnValeur
            texte={page?.titre ?? `${t.titreDebut}*${t.titreAccent}*${t.titreFin}`}
          />
        }
        photo={page?.photos[0] ? { src: page.photos[0], alt: t.surtitre } : undefined}
      >
        {intro.map((paragraphe) => (
          <p key={paragraphe} className="m-0 max-w-[640px]">
            {paragraphe}
          </p>
        ))}
      </EnTetePage>

      <section aria-label={t.filtres} className="mx-auto max-w-[1376px] px-4 sm:px-8">
        <div className="flex flex-wrap items-end gap-3 rounded-[22px] bg-white p-3 shadow-[0_8px_24px_rgba(43,29,20,0.08)] sm:gap-4 sm:p-4">
          <label className="flex flex-col gap-1 text-xs font-bold text-taupe">
            {t.mois}
            <select
              className={selectClasse}
              value={mois ?? ''}
              onChange={(e) =>
                changer({ mois: e.target.value === '' ? undefined : Number(e.target.value) })
              }
            >
              <option value="">{t.meilleurMois}</option>
              {Array.from({ length: 12 }, (_, i) => (
                <option key={i} value={i}>
                  {majuscule(nomDuMois(i, langue))}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1 text-xs font-bold text-taupe">
            {t.temperature}
            <select
              className={selectClasse}
              value={temperature ?? ''}
              onChange={(e) =>
                changer({ temperature: e.target.value === '' ? undefined : Number(e.target.value) })
              }
            >
              <option value="">{t.peuImporte}</option>
              {TEMPERATURES.map((temp) => (
                <option key={temp} value={temp}>
                  {t.auMoins(temp)}
                </option>
              ))}
            </select>
          </label>
          <div role="group" aria-label={t.typeDeVoyage} className="flex flex-wrap gap-1.5">
            {filtresType.map((f) => {
              const actif = f.valeur === type
              return (
                <button
                  key={f.label}
                  type="button"
                  aria-pressed={actif}
                  onClick={() => changer({ type: f.valeur })}
                  className={cn(
                    'min-h-11 rounded-full border-[1.5px] px-4 text-sm font-bold',
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
      </section>

      <section aria-live="polite" className="mx-auto max-w-[1376px] px-4 py-10 sm:px-8 sm:pb-20">
        <p className="mt-0 mb-6 text-sm font-bold text-taupe">
          {t.resultats(resultats.length)}
          {mois !== undefined && t.enMois(nomDuMois(mois, langue))}
        </p>
        {resultats.length > 0 ? (
          <div className="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-x-6 gap-y-10">
            {resultats.map((d) => (
              <CarteDestination key={d.slug} d={d} mois={mois} />
            ))}
          </div>
        ) : (
          <div className="flex flex-col items-start gap-4 rounded-[22px] bg-sable p-6 sm:p-8">
            <h2 className="m-0 font-display text-2xl font-semibold">{t.aucune}</h2>
            <p className="m-0 text-taupe">{t.aucuneTexte}</p>
            <div className="flex flex-wrap gap-2">
              <Link
                to="/destinations"
                search={{}}
                className="flex min-h-11 items-center rounded-full border-[1.5px] border-encre px-5 text-sm font-bold"
              >
                {t.effacer}
              </Link>
              <button
                type="button"
                onClick={() => ouvrirAssistant()}
                className="flex min-h-11 items-center rounded-full bg-terre px-5 text-sm font-extrabold text-white"
              >
                {t.demander}
              </button>
            </div>
          </div>
        )}
      </section>
    </>
  )
}
