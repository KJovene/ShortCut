import { createFileRoute, Link, notFound } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { IconAvion, IconCoche, IconFleche } from '@/components/ui/icons'
import { formatDuree, formatEuros, labelType, MOIS } from '@/data/catalogue'
import { destinationQuery, type Exemple } from '@/features/catalogue/catalogue'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { ApiError } from '@/lib/api'
import { cn } from '@/lib/cn'

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
          { title: `${loaderData.nom} · Climat, hébergements et prix` },
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
  const d = Route.useLoaderData()
  const ouvrirAssistant = useOuvrirAssistant()
  const construire = () => ouvrirAssistant(`Un voyage à ${d.ville} en ${MOIS[d.moisIdeal]}, `)

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
            <nav aria-label="Fil d'Ariane" className="text-sm font-semibold text-sable">
              <Link to="/destinations" className="underline-offset-2 hover:underline">
                Destinations
              </Link>{' '}
              / {d.nom}
            </nav>
            <h1 className="m-0 font-display text-[40px] leading-none font-semibold tracking-[-1px] sm:text-[64px]">
              {d.nom}
            </h1>
            <p className="m-0 text-base font-semibold text-sable sm:text-lg">
              {[d.region, ...d.types.map(labelType)].join(' · ')}
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

          <Climat temperatures={d.temperatures} pluie={d.pluie} moisIdeal={d.moisIdeal} />

          <Prestations titre="Exemples d'hébergements" prestations={d.logements} unite="nuit" />
          <Prestations titre="Exemples d'activités" prestations={d.activites} unite="personne" />
        </div>

        <aside className="flex min-w-0 flex-[1_1_340px] flex-col gap-4 self-start rounded-[28px] bg-brun p-6 text-creme lg:sticky lg:top-6">
          {d.prixAPartirDe !== null && (
            <>
              <span className="text-[13px] font-extrabold tracking-[1.5px] text-ocre uppercase">
                À partir de
              </span>
              <div>
                <span className="font-display text-5xl font-semibold tracking-[-1px]">
                  {formatEuros(d.prixAPartirDe)}
                </span>
                <span className="text-sable"> / pers.</span>
              </div>
              <p className="m-0 text-sm leading-relaxed text-sable">
                Estimation pour 7 nuits à deux, vols depuis Paris et transferts compris, marge et
                frais de dossier inclus. Votre proposition est calculée sur vos dates exactes.
              </p>
            </>
          )}
          <dl className="m-0 flex flex-col gap-2 border-t border-white/15 pt-4 text-sm">
            {Object.entries(d.tempsDeTrajet).map(([ville, minutes]) => (
              <div key={ville} className="flex items-center justify-between gap-3">
                <dt className="flex items-center gap-2 text-sable">
                  <IconAvion size={16} />
                  Depuis {ville}
                </dt>
                <dd className="m-0 font-bold">{formatDuree(minutes)}</dd>
              </div>
            ))}
          </dl>
          <button
            type="button"
            onClick={construire}
            className="flex min-h-[52px] items-center justify-center gap-2 rounded-full bg-terre font-extrabold text-white hover:bg-rouille"
          >
            Construire mon voyage
            <IconFleche size={18} strokeWidth={2.4} />
          </button>
          <p className="m-0 text-center text-xs text-sable">
            Proposition chiffrée en 2 minutes, confirmée par un conseiller.
          </p>
        </aside>
      </section>
    </>
  )
}

function Climat({
  temperatures,
  pluie,
  moisIdeal,
}: {
  temperatures: (number | null)[]
  pluie: (number | null)[]
  moisIdeal: number
}) {
  return (
    <section aria-labelledby="climat-titre" className="flex flex-col gap-4">
      <h2 id="climat-titre" className="m-0 font-display text-[30px] font-semibold">
        Le climat mois par mois
      </h2>
      <div className="overflow-x-auto rounded-[22px] bg-white p-4 shadow-[0_8px_24px_rgba(43,29,20,0.08)] sm:p-6">
        <table className="w-full min-w-[560px] border-collapse text-center text-sm">
          <caption className="sr-only">Températures moyennes et pluie par mois</caption>
          <thead>
            <tr>
              <th scope="row" className="pb-2 text-left text-xs font-bold text-taupe-clair">
                Mois
              </th>
              {MOIS.map((m, i) => (
                <th
                  key={m}
                  scope="col"
                  className={cn(
                    'pb-2 text-xs font-bold uppercase',
                    i === moisIdeal ? 'text-terre' : 'text-taupe-clair',
                  )}
                >
                  {m.slice(0, 3)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            <tr>
              <th scope="row" className="text-left text-xs font-bold text-taupe-clair">
                °C
              </th>
              {temperatures.map((t, i) => (
                <td key={i} className="h-32 px-0.5 align-bottom">
                  {t === null ? (
                    <span className="text-xs text-taupe-clair">–</span>
                  ) : (
                    <div className="flex h-full flex-col items-center justify-end gap-1">
                      <span className="text-xs font-extrabold">{t}°</span>
                      <span
                        aria-hidden="true"
                        className={cn(
                          'w-full max-w-7 rounded-t-md',
                          t >= 25 ? 'bg-terre' : t >= 18 ? 'bg-ocre' : 'bg-beige',
                        )}
                        style={{ height: `${Math.max(6, (Math.max(t, 0) / TEMP_MAX) * 88)}px` }}
                      />
                    </div>
                  )}
                </td>
              ))}
            </tr>
            <tr>
              <th scope="row" className="pt-3 text-left text-xs font-bold text-taupe-clair">
                Pluie
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
        Températures moyennes saisies par nos conseillers. Meilleure période conseillée :{' '}
        {MOIS[moisIdeal]}.
      </p>
    </section>
  )
}

function Prestations({
  titre,
  prestations,
  unite,
}: {
  titre: string
  prestations: Exemple[]
  unite: 'nuit' | 'personne'
}) {
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
                  ? ` · ${p.capacite} pers.`
                  : p.duree
                    ? ` · ${formatDuree(p.duree)}`
                    : ''}
                {p.accessible && ' · accessible'}
              </span>
              <span className="mt-1 text-sm">
                dès <strong>{formatEuros(p.prix)}</strong> / {unite}
              </span>
            </div>
          </article>
        ))}
      </div>
      <p className="m-0 text-xs text-taupe-clair">
        Prix les plus bas de l'année, marge de l'agence comprise ; ils varient selon la saison.
      </p>
    </section>
  )
}
