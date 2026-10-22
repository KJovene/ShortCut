import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute, Link, notFound } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { IconAvion, IconCoche, IconFleche } from '@/components/ui/icons'
import { destinationQuery, type Exemple } from '@/features/catalogue/catalogue'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { TEXTES } from '@/features/pages/textes'
import { ApiError } from '@/lib/api'
import { cn } from '@/lib/cn'
import {
  duree,
  euros,
  type Langue,
  langueDuSite,
  nomDuMois,
  typeDeVoyage,
  useLangue,
} from '@/lib/langue'

export const Route = createFileRoute('/destinations/$slug')({
  // Une destination masquée dans le backoffice répond 404, comme une destination inconnue.
  loader: ({ context, params }) =>
    context.queryClient.ensureQueryData(destinationQuery(params.slug)).catch((erreur: unknown) => {
      if (erreur instanceof ApiError && erreur.status === 404) throw notFound()
      throw erreur
    }),
  head: ({ loaderData }) => ({
    meta: loaderData
      ? [
          { title: TEXTES[langueDuSite()].destination.metaTitre(loaderData.nom) },
          { name: 'description', content: loaderData.description },
        ]
      : [],
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: DestinationPage,
})

const TEMP_MAX = 32

function DestinationPage() {
  const { slug } = Route.useParams()
  const langue = useLangue()
  const t = TEXTES[langue].destination
  const { data: d } = useSuspenseQuery(destinationQuery(slug, langue))
  const ouvrirAssistant = useOuvrirAssistant()
  const construire = () =>
    ouvrirAssistant(TEXTES[langue].accueil.brouillon(d.ville, nomDuMois(d.moisIdeal, langue)))

  return (
    <>
      <section className="relative">
        <img
          src={d.photo}
          alt={d.alt}
          className="block h-[320px] w-full object-cover sm:h-[460px]"
        />
        <div className="absolute inset-0 bg-linear-to-t from-encre/80 via-encre/20 to-transparent" />
        <div className="absolute inset-x-0 bottom-0">
          <div className="mx-auto flex max-w-[1376px] flex-col gap-2 px-4 pb-6 text-creme sm:px-8 sm:pb-10">
            <nav aria-label={t.filAriane} className="text-sm font-semibold text-sable">
              <Link to="/destinations" className="underline-offset-2 hover:underline">
                Destinations
              </Link>{' '}
              / {d.nom}
            </nav>
            <h1 className="m-0 font-display text-[40px] leading-none font-semibold tracking-[-1px] sm:text-[64px]">
              {d.nom}
            </h1>
            <p className="m-0 text-base font-semibold text-sable sm:text-lg">
              {[d.region, ...d.types.map((type) => typeDeVoyage(type, langue))].join(' · ')}
            </p>
          </div>
        </div>
      </section>

      <section className="mx-auto flex max-w-[1376px] flex-wrap gap-8 px-4 py-10 sm:px-8 sm:py-14 lg:gap-12">
        <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-10">
          <div className="flex flex-col gap-4">
            <p className="m-0 text-lg leading-relaxed text-taupe sm:text-xl">{d.description}</p>
            <ul className="m-0 grid list-none grid-cols-1 gap-2.5 p-0 sm:grid-cols-2">
              {d.pointsForts.map((p) => (
                <li key={p} className="flex items-center gap-2.5 font-semibold">
                  <span
                    aria-hidden="true"
                    className="flex size-6 shrink-0 items-center justify-center rounded-full bg-vert text-white"
                  >
                    <IconCoche size={12} />
                  </span>
                  {p}
                </li>
              ))}
            </ul>
          </div>

          <Climat
            temperatures={d.temperatures}
            pluie={d.pluie}
            moisIdeal={d.moisIdeal}
            langue={langue}
          />

          <Prestations
            titre={t.hebergements}
            prestations={d.logements}
            unite="nuit"
            langue={langue}
          />
          <Prestations
            titre={t.activites}
            prestations={d.activites}
            unite="personne"
            langue={langue}
          />
        </div>

        <aside className="flex min-w-0 flex-[1_1_340px] flex-col gap-4 self-start rounded-[28px] bg-brun p-6 text-creme lg:sticky lg:top-6">
          {d.prixAPartirDe !== null && (
            <>
              <span className="text-[13px] font-extrabold tracking-[1.5px] text-ocre uppercase">
                {t.aPartirDe}
              </span>
              <div>
                <span className="font-display text-5xl font-semibold tracking-[-1px]">
                  {euros(d.prixAPartirDe, langue)}
                </span>
                <span className="text-sable">{t.parPersonne}</span>
              </div>
              <p className="m-0 text-sm leading-relaxed text-sable">{t.estimation}</p>
            </>
          )}
          <dl className="m-0 flex flex-col gap-2 border-t border-white/15 pt-4 text-sm">
            {Object.entries(d.tempsDeTrajet).map(([ville, minutes]) => (
              <div key={ville} className="flex items-center justify-between gap-3">
                <dt className="flex items-center gap-2 text-sable">
                  <IconAvion size={16} />
                  {t.depuis(ville)}
                </dt>
                <dd className="m-0 font-bold">{duree(minutes, langue)}</dd>
              </div>
            ))}
          </dl>
          <button
            type="button"
            onClick={construire}
            className="flex min-h-[52px] items-center justify-center gap-2 rounded-full bg-terre font-extrabold text-white hover:bg-rouille"
          >
            {TEXTES[langue].construireMonVoyage}
            <IconFleche size={18} strokeWidth={2.4} />
          </button>
          <p className="m-0 text-center text-xs text-sable">{t.sousBouton}</p>
        </aside>
      </section>
    </>
  )
}

function Climat({
  temperatures,
  pluie,
  moisIdeal,
  langue,
}: {
  temperatures: (number | null)[]
  pluie: (number | null)[]
  moisIdeal: number
  langue: Langue
}) {
  const t = TEXTES[langue].destination
  return (
    <section aria-labelledby="climat-titre" className="flex flex-col gap-4">
      <h2 id="climat-titre" className="m-0 font-display text-[30px] font-semibold">
        {t.climat}
      </h2>
      <div className="overflow-x-auto rounded-[22px] bg-white p-4 shadow-[0_8px_24px_rgba(43,29,20,0.08)] sm:p-6">
        <table className="w-full min-w-[560px] border-collapse text-center text-sm">
          <caption className="sr-only">{t.legendeClimat}</caption>
          <thead>
            <tr>
              <th scope="row" className="pb-2 text-left text-xs font-bold text-taupe-clair">
                {t.mois}
              </th>
              {Array.from({ length: 12 }, (_, i) => (
                <th
                  key={i}
                  scope="col"
                  className={cn(
                    'pb-2 text-xs font-bold uppercase',
                    i === moisIdeal ? 'text-terre' : 'text-taupe-clair',
                  )}
                >
                  {nomDuMois(i, langue).slice(0, 3)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            <tr>
              <th scope="row" className="text-left text-xs font-bold text-taupe-clair">
                °C
              </th>
              {temperatures.map((temp, i) => (
                <td key={i} className="h-32 px-0.5 align-bottom">
                  {temp === null ? (
                    <span className="text-xs text-taupe-clair">–</span>
                  ) : (
                    <div className="flex h-full flex-col items-center justify-end gap-1">
                      <span className="text-xs font-extrabold">{temp}°</span>
                      <span
                        aria-hidden="true"
                        className={cn(
                          'w-full max-w-7 rounded-t-md',
                          temp >= 25 ? 'bg-terre' : temp >= 18 ? 'bg-ocre' : 'bg-beige',
                        )}
                        style={{
                          height: `${Math.max(6, (Math.max(temp, 0) / TEMP_MAX) * 88)}px`,
                        }}
                      />
                    </div>
                  )}
                </td>
              ))}
            </tr>
            <tr>
              <th scope="row" className="pt-3 text-left text-xs font-bold text-taupe-clair">
                {t.pluie}
              </th>
              {pluie.map((p, i) => (
                <td key={i} className="pt-3 text-xs text-taupe">
                  {p === null ? '–' : `${p} mm`}
                </td>
              ))}
            </tr>
          </tbody>
        </table>
      </div>
      <p className="m-0 text-xs text-taupe-clair">
        {t.meilleurePeriode(nomDuMois(moisIdeal, langue))}
      </p>
    </section>
  )
}

function Prestations({
  titre,
  prestations,
  unite,
  langue,
}: {
  titre: string
  prestations: Exemple[]
  unite: 'nuit' | 'personne'
  langue: Langue
}) {
  const t = TEXTES[langue].destination
  if (prestations.length === 0) return null
  return (
    <section className="flex flex-col gap-4">
      <h2 className="m-0 font-display text-[30px] font-semibold">{titre}</h2>
      <div className="grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-4">
        {prestations.map((p) => (
          <article
            key={p.nom}
            className="overflow-hidden rounded-[22px] bg-white shadow-[0_8px_24px_rgba(43,29,20,0.08)]"
          >
            {p.photo && <img src={p.photo} alt="" className="block h-36 w-full object-cover" />}
            <div className="flex flex-col gap-1 px-4 pt-3 pb-4">
              <h3 className="m-0 text-base leading-snug font-bold">{p.nom}</h3>
              <span className="text-sm text-taupe">
                {p.categorie}
                {unite === 'nuit'
                  ? ` · ${t.pers(p.capacite)}`
                  : p.duree
                    ? ` · ${duree(p.duree, langue)}`
                    : ''}
                {p.accessible && ` · ${t.accessible}`}
              </span>
              <span className="mt-1 text-sm">
                {t.des} <strong>{euros(p.prix, langue)}</strong> / {t[unite]}
              </span>
            </div>
          </article>
        ))}
      </div>
      <p className="m-0 text-xs text-taupe-clair">{t.prixBas}</p>
    </section>
  )
}
