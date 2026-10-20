import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute, Link } from '@tanstack/react-router'
import { useState } from 'react'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { IconTelecharger } from '@/components/ui/icons'
import {
  formatDuree,
  formatEuros,
  labelType,
  MOIS,
  trouverDestination,
  type Prestation,
} from '@/data/catalogue'
import { useChat } from '@/features/chat/chatContext'
import {
  estJetonProposition,
  propositionQuery,
  titreProposition,
} from '@/features/proposition/enLigne'
import { NIVEAUX, type Niveau } from '@/features/proposition/proposition'
import { PropositionEnLigne } from '@/features/proposition/PropositionEnLigne'
import { trouverProposition } from '@/features/proposition/store'
import { cn } from '@/lib/cn'

/**
 * « Ma proposition » : par son lien unique (jeton), la proposition envoyée par le conseiller, lue dans l'API ;
 * « exemple » et les propositions de démonstration restent calculées dans le navigateur.
 */
export const Route = createFileRoute('/proposition/$id')({
  loader: ({ context, params }) =>
    estJetonProposition(params.id)
      ? context.queryClient.ensureQueryData(propositionQuery(params.id))
      : null,
  head: ({ loaderData }) => ({
    meta: [
      {
        title: loaderData
          ? `Ma proposition · ${titreProposition(loaderData)}`
          : 'Ma proposition de voyage',
      },
      {
        name: 'description',
        content: 'Votre proposition de voyage sur mesure, à confirmer par votre conseiller.',
      },
      // Proposition personnelle accessible par lien unique : pas d'indexation.
      { name: 'robots', content: 'noindex' },
    ],
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: PagePropositionRoute,
})

function PagePropositionRoute() {
  const { id } = Route.useParams()
  return estJetonProposition(id) ? <PropositionDuLien jeton={id} /> : <PropositionPage />
}

function PropositionDuLien({ jeton }: { jeton: string }) {
  const { data: proposition } = useSuspenseQuery(propositionQuery(jeton))
  return proposition ? <PropositionEnLigne proposition={proposition} /> : <PropositionIntrouvable />
}

const dateLongue = (iso: string) =>
  new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }).format(
    new Date(iso),
  )

function PropositionPage() {
  const { id } = Route.useParams()
  const proposition = trouverProposition(id)
  const [niveau, setNiveau] = useState<Niveau>('equilibre')
  const { ouvrir, envoyer } = useChat()

  if (!proposition) return <PropositionIntrouvable />

  const destination = trouverDestination(proposition.destination)!
  const budget = proposition.budgets[niveau]
  const temperature = destination.temperatures[proposition.mois]
  const ecartBudget =
    proposition.budgetClient !== undefined ? budget.total - proposition.budgetClient : undefined

  return (
    <div className="pb-16">
      <div className="border-b border-[#F0DCA0] bg-[#FFF4D6]">
        <p className="mx-auto m-0 max-w-[1376px] px-4 py-3 text-sm font-semibold text-[#5C4300] sm:px-8">
          Première proposition, prix estimés, à confirmer par votre conseiller. Confirmation
          annoncée sous 2 jours ouvrés.
        </p>
      </div>

      <section className="mx-auto flex max-w-[1376px] flex-wrap gap-8 px-4 pt-8 pb-6 sm:px-8 sm:pt-10 lg:gap-10">
        <div className="flex min-w-0 flex-[1_1_560px] flex-col gap-4">
          <span className="text-[13px] font-extrabold tracking-[1.5px] text-rouille uppercase">
            Version {proposition.version} · {dateLongue(proposition.creeLe)} ·{' '}
            {labelType(proposition.type)}
          </span>
          <h1 className="m-0 font-display text-[40px] leading-[1.05] font-semibold tracking-[-1.5px] sm:text-[52px]">
            {proposition.titre}
          </h1>
          <p className="m-0 text-lg leading-relaxed text-taupe">{proposition.resume}</p>
          <div className="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3">
            <Chiffre
              label={`Température moyenne en ${MOIS[proposition.mois]}`}
              valeur={`${temperature} °C`}
            />
            <Chiffre
              label={`Vol depuis ${proposition.depart}`}
              valeur={formatDuree(
                destination.tempsDeTrajet[proposition.depart] ?? destination.tempsDeTrajet.Paris,
              )}
            />
            <Chiffre label="Ambiance" valeur={destination.pointsForts[0]} petit />
          </div>
          <div className="no-print flex flex-wrap gap-2.5 pt-2">
            <button
              type="button"
              onClick={() => window.print()}
              className="flex min-h-11 items-center gap-2 rounded-full border-[1.5px] border-encre px-[18px] text-sm font-bold hover:bg-sable"
            >
              <IconTelecharger size={18} />
              Télécharger le PDF
            </button>
            <button
              type="button"
              onClick={() => ouvrir()}
              className="flex min-h-11 items-center rounded-full bg-terre px-[18px] text-sm font-bold text-white hover:bg-rouille"
            >
              Modifier dans le chat
            </button>
          </div>
        </div>
        <img
          src={destination.photo}
          alt={destination.alt}
          className="block min-h-[260px] w-full min-w-0 flex-[1_1_420px] rounded-[28px] object-cover sm:min-h-[340px]"
        />
      </section>

      <section className="mx-auto flex max-w-[1376px] flex-wrap items-start gap-8 px-4 pt-4 sm:px-8">
        <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
          <h2 className="m-0 font-display text-[30px] font-semibold">Programme jour par jour</h2>
          <ol className="m-0 flex list-none flex-col overflow-hidden rounded-[22px] bg-white p-0 shadow-[0_8px_24px_rgba(43,29,20,0.08)]">
            {proposition.jours.map((j) => (
              <li
                key={j.numero}
                className="flex items-baseline gap-4 border-b border-sable px-5 py-3.5 last:border-b-0"
              >
                <span className="w-12 shrink-0 font-display font-semibold text-terre">
                  J{j.numero}
                </span>
                <span className="flex flex-1 flex-col gap-0.5">
                  <strong className="text-base">{j.titre}</strong>
                  <span className="text-sm text-taupe">{j.detail}</span>
                </span>
              </li>
            ))}
          </ol>

          <h2 className="m-0 mt-4 font-display text-[30px] font-semibold">
            Hébergement · {NIVEAUX.find((n) => n.valeur === niveau)!.label}
          </h2>
          <CartePrestation
            p={budget.logement}
            detail={`${budget.logement.categorie} · ${proposition.nuits} nuits · ${budget.chambres} chambre${budget.chambres > 1 ? 's' : ''}`}
          />

          <h2 className="m-0 mt-4 font-display text-[30px] font-semibold">Activités incluses</h2>
          <div className="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
            {budget.activites.map((a) => (
              <CartePrestation
                key={a.nom}
                p={a}
                detail={`${a.categorie} · ${formatDuree(a.duree ?? 120)}`}
              />
            ))}
          </div>
        </div>

        <aside className="flex min-w-0 flex-[1_1_360px] flex-col gap-4 rounded-[28px] bg-brun p-6 text-creme lg:sticky lg:top-6">
          <h2 className="m-0 font-display text-2xl font-semibold">Votre budget estimé</h2>
          <div
            role="group"
            aria-label="Niveau de budget"
            className="no-print grid grid-cols-3 gap-1 rounded-[14px] bg-white/10 p-1"
          >
            {NIVEAUX.map((n) => (
              <button
                key={n.valeur}
                type="button"
                aria-pressed={n.valeur === niveau}
                onClick={() => setNiveau(n.valeur)}
                className={cn(
                  'min-h-11 rounded-[10px] text-[13px]',
                  n.valeur === niveau
                    ? 'bg-white font-bold text-encre'
                    : 'font-semibold text-sable hover:bg-white/10',
                )}
              >
                {n.label}
              </button>
            ))}
          </div>
          <div className="flex flex-col gap-0.5">
            <span className="font-display text-5xl font-semibold tracking-[-1px]">
              {formatEuros(budget.total)}
            </span>
            <span className="text-sm text-sable">
              soit {formatEuros(budget.parPersonne)} par personne
              {proposition.budgetClient !== undefined &&
                ` · votre budget : ${formatEuros(proposition.budgetClient)}`}
            </span>
            {ecartBudget !== undefined && ecartBudget > 0 && (
              <span className="mt-1 text-sm font-bold text-ocre">
                {formatEuros(ecartBudget)} au-dessus de votre budget : essayez le niveau essentiel
                ou moins de jours.
              </span>
            )}
          </div>
          <dl className="m-0 flex flex-col gap-2.5 border-t border-white/15 pt-3.5 text-sm">
            {budget.lignes.map((l) => (
              <div key={l.label} className="flex justify-between gap-3">
                <dt className="text-sable">
                  {l.label}
                  {l.estime && ' (estimé)'}
                </dt>
                <dd className="m-0 font-bold">{formatEuros(l.montant)}</dd>
              </div>
            ))}
          </dl>
          <div className="flex flex-col gap-2 border-t border-white/15 pt-3.5">
            <span className="text-sm font-bold">Hypothèses de budget</span>
            <ul className="m-0 pl-[18px] text-[13px] leading-relaxed text-sable">
              {proposition.hypotheses.map((h) => (
                <li key={h}>{h}</li>
              ))}
            </ul>
          </div>
          {proposition.contraintesSignalees.length > 0 && (
            <p className="m-0 rounded-xl bg-white/10 px-3 py-2.5 text-[13px] font-bold">
              Signalé à votre conseiller : {proposition.contraintesSignalees.join(', ')}
            </p>
          )}
          <button
            type="button"
            onClick={() => {
              envoyer(
                `Je souhaite faire confirmer la version ${proposition.version} (${NIVEAUX.find((n) => n.valeur === niveau)!.label}) par un conseiller`,
              )
              ouvrir()
            }}
            className="no-print flex min-h-12 items-center justify-center rounded-[14px] bg-terre font-bold text-white hover:bg-rouille"
          >
            Demander la confirmation
          </button>
          <p className="m-0 text-center text-xs text-sable">
            Rien n'est réservé ni payé avant la confirmation de votre conseiller.
          </p>
        </aside>
      </section>
    </div>
  )
}

function Chiffre({
  label,
  valeur,
  petit = false,
}: {
  label: string
  valeur: string
  petit?: boolean
}) {
  return (
    <div className="flex flex-col gap-0.5 rounded-2xl bg-white px-4 py-3.5 shadow-[0_2px_10px_rgba(43,29,20,0.06)]">
      <span className="text-xs font-semibold text-taupe">{label}</span>
      <span
        className={cn('font-display font-semibold', petit ? 'text-lg leading-tight' : 'text-2xl')}
      >
        {valeur}
      </span>
    </div>
  )
}

function CartePrestation({ p, detail }: { p: Prestation; detail: string }) {
  return (
    <article className="flex overflow-hidden rounded-[22px] bg-white shadow-[0_8px_24px_rgba(43,29,20,0.08)]">
      <img src={p.photo} alt="" className="block w-28 shrink-0 object-cover sm:w-36" />
      <div className="flex flex-col gap-1 px-4 py-3.5">
        <strong>{p.nom}</strong>
        <span className="text-sm text-taupe">{detail}</span>
        <span className="text-xs text-taupe-clair">Fiche source : catalogue de l'agence</span>
      </div>
    </article>
  )
}

function PropositionIntrouvable() {
  const { ouvrir } = useChat()
  return (
    <section className="mx-auto flex max-w-[720px] flex-col items-start gap-4 px-4 py-16 sm:px-8 sm:py-24">
      <span className="text-[13px] font-extrabold tracking-[1.5px] text-rouille uppercase">
        Ma proposition
      </span>
      <h1 className="m-0 font-display text-[40px] leading-tight font-semibold">
        Proposition introuvable
      </h1>
      <p className="m-0 text-lg text-taupe">
        Ce lien ne correspond à aucune proposition, ou elle a expiré. Décrivez votre voyage à
        l'assistant pour en recevoir une nouvelle en 2 minutes.
      </p>
      <div className="flex flex-wrap gap-2.5">
        <button
          type="button"
          onClick={() => ouvrir()}
          className="flex min-h-12 items-center rounded-full bg-terre px-6 font-extrabold text-white"
        >
          Construire mon voyage
        </button>
        <Link
          to="/proposition/$id"
          params={{ id: 'exemple' }}
          className="flex min-h-12 items-center rounded-full border-[1.5px] border-encre px-6 font-bold"
        >
          Voir un exemple
        </Link>
      </div>
    </section>
  )
}
