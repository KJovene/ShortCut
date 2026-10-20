import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { IconCoche, IconTelecharger } from '@/components/ui/icons'
import { formatEuros, labelType, MOIS, trouverDestination } from '@/data/catalogue'
import { cn } from '@/lib/cn'
import {
  type ActionProposition,
  agirSurProposition,
  photoPrestation,
  type PropositionEnLigne as Proposition,
  propositionQuery,
  titreProposition,
  voyageurs,
} from './enLigne'
import { NIVEAUX } from './proposition'

const dateLongue = (iso: string) =>
  new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }).format(
    new Date(iso),
  )

const dateJour = (iso: string) =>
  new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }).format(
    new Date(iso),
  )

const euros = (centimes: number) => formatEuros(centimes / 100)

/** Page « Ma proposition » d'une proposition envoyée par le conseiller (ticket #208). */
export function PropositionEnLigne({ proposition: p }: { proposition: Proposition }) {
  const destination = p.destination?.slug ? trouverDestination(p.destination.slug) : undefined
  const mois = p.demande.dateDebut ? new Date(p.demande.dateDebut).getMonth() : null
  const confirmee = p.etat === 'confirmee'
  const logements = p.prestations.filter((x) => x.type === 'logement')
  const activites = p.prestations.filter((x) => x.type === 'activite')

  return (
    <div className="pb-16">
      {/* Visible sans défiler : prix estimés tant que le conseiller n'a pas confirmé. */}
      <div
        className={cn(
          'border-b',
          confirmee ? 'border-[#B9E0C6] bg-[#DDF1E4]' : 'border-[#F0DCA0] bg-[#FFF4D6]',
        )}
      >
        <p
          className={cn(
            'mx-auto m-0 max-w-[1376px] px-4 py-3 text-sm font-semibold sm:px-8',
            confirmee ? 'text-[#1F6B3A]' : 'text-[#5C4300]',
          )}
        >
          {p.mention}
          {!confirmee && ' Confirmation annoncée sous 2 jours ouvrés.'}
        </p>
      </div>

      {p.versionDemandee !== null && (
        <p
          role="status"
          className="mx-auto m-0 mt-4 max-w-[1376px] px-4 text-sm font-semibold text-rouille sm:px-8"
        >
          Le lien ouvert concernait la version {p.versionDemandee} : voici la version {p.version}{' '}
          qui la remplace.
        </p>
      )}

      <section className="mx-auto flex max-w-[1376px] flex-wrap gap-8 px-4 pt-8 pb-6 sm:px-8 sm:pt-10 lg:gap-10">
        <div className="flex min-w-0 flex-[1_1_560px] flex-col gap-4">
          <span className="text-[13px] font-extrabold tracking-[1.5px] text-rouille uppercase">
            Version {p.version} ·{' '}
            {dateLongue(confirmee && p.confirmeeLe ? p.confirmeeLe : p.creeLe)}
            {p.demande.typeDeVoyage && ` · ${labelType(p.demande.typeDeVoyage)}`}
          </span>
          <h1 className="m-0 font-display text-[40px] leading-[1.05] font-semibold tracking-[-1.5px] sm:text-[52px]">
            {titreProposition(p)}
          </h1>
          {p.pourquoi && <p className="m-0 text-lg leading-relaxed text-taupe">{p.pourquoi}</p>}
          <div className="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3">
            <Chiffre label="Voyageurs" valeur={voyageurs(p.demande)} />
            {p.demande.dateDebut && (
              <Chiffre label="Départ" valeur={dateLongue(p.demande.dateDebut)} />
            )}
            {destination && mois !== null && (
              <Chiffre
                label={`Température moyenne en ${MOIS[mois]}`}
                valeur={`${destination.temperatures[mois]} °C`}
              />
            )}
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
            <Link
              to="/espace"
              className="flex min-h-11 items-center rounded-full px-[18px] text-sm font-bold text-rouille hover:bg-sable"
            >
              Suivre mon voyage
            </Link>
          </div>
        </div>
        {destination ? (
          <img
            src={destination.photo}
            alt={destination.alt}
            className="block min-h-[260px] w-full min-w-0 flex-[1_1_420px] rounded-[28px] object-cover sm:min-h-[340px]"
          />
        ) : (
          <div className="min-h-[200px] min-w-0 flex-[1_1_420px] rounded-[28px] bg-sable" />
        )}
      </section>

      <section className="mx-auto flex max-w-[1376px] flex-wrap items-start gap-8 px-4 pt-4 sm:px-8">
        <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
          <h2 className="m-0 font-display text-[30px] font-semibold">Programme jour par jour</h2>
          <ol className="m-0 flex list-none flex-col overflow-hidden rounded-[22px] bg-white p-0 shadow-[0_8px_24px_rgba(43,29,20,0.08)]">
            {p.programme.map((j) => (
              <li
                key={j.jour}
                className="flex items-baseline gap-4 border-b border-sable px-5 py-3.5 last:border-b-0"
              >
                <span className="flex w-16 shrink-0 flex-col">
                  <span className="font-display font-semibold text-terre">J{j.jour}</span>
                  <span className="text-xs text-taupe">{dateJour(j.date)}</span>
                </span>
                <span className="flex flex-1 flex-col gap-0.5">
                  <strong className="text-base">{j.titre}</strong>
                  <span className="text-sm text-taupe">{j.description}</span>
                </span>
              </li>
            ))}
          </ol>

          {p.argumentaire.length > 0 && (
            <>
              <h2 className="m-0 mt-4 font-display text-[30px] font-semibold">
                Pourquoi ce voyage vous correspond
              </h2>
              <ul className="m-0 flex list-none flex-col gap-3 p-0">
                {p.argumentaire.map((a) => (
                  <li key={a.exigence} className="flex gap-3 rounded-[18px] bg-white px-5 py-4">
                    <span
                      aria-hidden="true"
                      className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-vert-fonce text-white"
                    >
                      <IconCoche size={13} strokeWidth={3} />
                    </span>
                    <span className="flex flex-col gap-0.5">
                      <strong>{a.exigence}</strong>
                      <span className="text-sm text-taupe">{a.reponse}</span>
                    </span>
                  </li>
                ))}
              </ul>
            </>
          )}

          <Prestations titre="Hébergement" liste={logements} />
          <Prestations titre="Activités incluses" liste={activites} />
        </div>

        <Budget proposition={p} />
      </section>
    </div>
  )
}

function Chiffre({ label, valeur }: { label: string; valeur: string }) {
  return (
    <div className="flex flex-col gap-0.5 rounded-2xl bg-white px-4 py-3.5 shadow-[0_2px_10px_rgba(43,29,20,0.06)]">
      <span className="text-xs font-semibold text-taupe">{label}</span>
      <span className="font-display text-xl font-semibold">{valeur}</span>
    </div>
  )
}

function Prestations({ titre, liste }: { titre: string; liste: Proposition['prestations'] }) {
  if (liste.length === 0) return null
  return (
    <>
      <h2 className="m-0 mt-4 font-display text-[30px] font-semibold">{titre}</h2>
      <div className="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-4">
        {liste.map((x) => {
          const photo = photoPrestation(x.nom)
          return (
            <article
              key={x.id}
              className="flex overflow-hidden rounded-[22px] bg-white shadow-[0_8px_24px_rgba(43,29,20,0.08)]"
            >
              {photo && (
                <img src={photo} alt="" className="block w-28 shrink-0 object-cover sm:w-32" />
              )}
              <div className="flex flex-col gap-1.5 px-4 py-3.5">
                <h3 className="m-0 text-base font-bold">{x.nom}</h3>
                {x.pointsForts.length > 0 && (
                  <ul className="m-0 list-disc pl-4 text-sm text-taupe">
                    {x.pointsForts.map((pf) => (
                      <li key={pf}>{pf}</li>
                    ))}
                  </ul>
                )}
              </div>
            </article>
          )
        })}
      </div>
    </>
  )
}

function Budget({ proposition: p }: { proposition: Proposition }) {
  const queryClient = useQueryClient()
  const confirmee = p.etat === 'confirmee'
  const action = useMutation({
    mutationFn: (quoi: ActionProposition) => agirSurProposition(p.jeton, quoi),
    onSuccess: (miseAJour) =>
      queryClient.setQueryData(propositionQuery(p.jeton).queryKey, miseAJour),
  })

  return (
    <aside className="flex min-w-0 flex-[1_1_360px] flex-col gap-4 rounded-[28px] bg-brun p-6 text-creme lg:sticky lg:top-6">
      <h2 className="m-0 font-display text-2xl font-semibold">
        {confirmee ? 'Votre budget confirmé' : 'Votre budget estimé'}
      </h2>
      <div className="flex flex-col gap-0.5">
        <span className="font-display text-5xl font-semibold tracking-[-1px]">
          {euros(p.totalCentimes)}
        </span>
        <span className="text-sm text-sable">
          soit {euros(p.totalParPersonneCentimes)} par personne · frais de dossier de{' '}
          {euros(p.fraisDeDossierCentimes)} inclus
        </span>
      </div>

      <dl className="m-0 grid grid-cols-3 gap-1.5">
        {NIVEAUX.map((n) => {
          const budget = p.budgetsParNiveau[n.valeur]
          if (!budget) return null
          const retenu = n.valeur === p.niveauBudget
          return (
            <div
              key={n.valeur}
              className={cn(
                'flex flex-col gap-0.5 rounded-xl p-2',
                retenu ? 'bg-white text-encre' : 'bg-white/10',
              )}
            >
              <dt className={cn('text-[11px]', retenu ? 'font-bold text-rouille' : 'text-sable')}>
                {n.label}
                {retenu && <span className="sr-only"> (niveau détaillé)</span>}
              </dt>
              <dd className="m-0 text-[15px] font-bold">{euros(budget.totalCentimes)}</dd>
            </div>
          )
        })}
      </dl>

      {p.lignes.length > 0 && (
        <div className="flex flex-col gap-2 border-t border-white/15 pt-3.5">
          <span className="text-sm font-bold">Inclus dans le niveau détaillé</span>
          <ul className="m-0 flex flex-col gap-1 pl-[18px] text-[13px] text-sable">
            {p.lignes.map((l, i) => (
              <li key={i}>
                {l.libelle}
                {l.quantite > 1 && ` × ${l.quantite}`}
              </li>
            ))}
          </ul>
        </div>
      )}

      {p.hypotheses.length > 0 && (
        <div className="flex flex-col gap-2 border-t border-white/15 pt-3.5">
          <span className="text-sm font-bold">Hypothèses de budget</span>
          <ul className="m-0 pl-[18px] text-[13px] leading-relaxed text-sable">
            {p.hypotheses.map((h) => (
              <li key={h}>{h}</li>
            ))}
          </ul>
        </div>
      )}

      {action.isError && (
        <p role="alert" className="m-0 rounded-xl bg-white/10 px-3 py-2.5 text-[13px] font-bold">
          {action.error.message}
        </p>
      )}
      {p.actions.accepter && (
        <button
          type="button"
          disabled={action.isPending}
          onClick={() => action.mutate('acceptation')}
          className="no-print flex min-h-12 items-center justify-center rounded-[14px] bg-terre font-bold text-white hover:bg-rouille disabled:opacity-60"
        >
          J’accepte cette proposition
        </button>
      )}
      {p.actions.demanderConfirmation && (
        <button
          type="button"
          disabled={action.isPending}
          onClick={() => action.mutate('demande-de-confirmation')}
          className="no-print flex min-h-12 items-center justify-center rounded-[14px] bg-terre font-bold text-white hover:bg-rouille disabled:opacity-60"
        >
          Demander la confirmation
        </button>
      )}
      <p className="m-0 text-center text-xs text-sable">{etatSuivi(p)}</p>
    </aside>
  )
}

/** Où en est la proposition, du point de vue du client. */
function etatSuivi(p: Proposition) {
  if (p.statut === 'acceptee')
    return 'Vous avez accepté cette proposition : votre conseiller réserve votre voyage.'
  if (p.demande.statut === 'a_confirmer')
    return 'Votre demande de confirmation est transmise : votre conseiller vérifie prix et disponibilités.'
  if (p.etat === 'confirmee')
    return 'Prix vérifiés par votre conseiller. Rien n’est réservé avant votre accord.'
  return 'Rien n’est réservé ni payé avant la confirmation de votre conseiller.'
}
