import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { useState } from 'react'
import { IconCoche, IconTelecharger } from '@/components/ui/icons'
import { formatEuros } from '@/data/catalogue'
import { cn } from '@/lib/cn'
import { type Langue, LOCALE, nomDuMois, typeDeVoyage } from '@/lib/langue'
import {
  type Changement,
  type Comparaison,
  cleLigne,
  comparer,
  type Ecart,
  formatEcart,
  formatPourcentage,
} from './comparaison'
import {
  type ActionProposition,
  agirSurProposition,
  NIVEAUX,
  pdfProposition,
  type PropositionEnLigne as Proposition,
  propositionQuery,
  titreProposition,
  versionQuery,
  voyageurs,
} from './enLigne'
import { TEXTES, type Textes } from './textes'

const dateLongue = (iso: string, langue: Langue) =>
  new Intl.DateTimeFormat(LOCALE[langue], {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(iso))

const dateJour = (iso: string, langue: Langue) =>
  new Intl.DateTimeFormat(LOCALE[langue], {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  }).format(new Date(iso))

const euros = (centimes: number) => formatEuros(centimes / 100)

/** La version reçue juste avant `numero`, ou null s'il n'y en a pas. */
function versionPrecedente(versions: Proposition['versions'], numero: number) {
  const anciennes = versions.filter((v) => v.version < numero).map((v) => v.version)
  return anciennes.length > 0 ? Math.max(...anciennes) : null
}

/**
 * Page « Ma proposition » d'une proposition envoyée par le conseiller (ticket #208). Quand le client a reçu plusieurs
 * versions (#211), il en choisit une et la compare à une autre : ce qui a changé est surligné (#212). Elle est dans la
 * langue où le client a dialogué avec le chatbot (US15).
 *
 * @param jeton le jeton du lien ouvert, clé de la page en cache
 * @param proposition la version qui fait foi
 */
export function PropositionEnLigne({
  jeton,
  proposition: actuelle,
}: {
  jeton: string
  proposition: Proposition
}) {
  const [numero, setNumero] = useState(actuelle.version)
  const [numeroCompare, setNumeroCompare] = useState(() =>
    versionPrecedente(actuelle.versions, actuelle.version),
  )
  const affichee = useQuery({
    ...versionQuery(jeton, numero),
    enabled: numero !== actuelle.version,
  })
  const comparee = useQuery({
    ...versionQuery(jeton, numeroCompare ?? 0),
    enabled: numeroCompare !== null && numeroCompare !== actuelle.version,
  })
  const t = TEXTES[actuelle.langue]

  const p = numero === actuelle.version ? actuelle : affichee.data
  const reference =
    numeroCompare === null
      ? undefined
      : numeroCompare === actuelle.version
        ? actuelle
        : comparee.data
  const comparaison = p && reference ? comparer(reference, p) : undefined

  function afficher(version: number) {
    setNumero(version)
    setNumeroCompare(versionPrecedente(actuelle.versions, version))
  }

  if (!p) {
    return (
      <div className="pb-16" lang={actuelle.langue}>
        <ChoixDesVersions
          versions={actuelle.versions}
          numero={numero}
          numeroCompare={numeroCompare}
          onAfficher={afficher}
          onComparer={setNumeroCompare}
          langue={actuelle.langue}
        />
        <p
          role={affichee.isError ? 'alert' : 'status'}
          className="mx-auto m-0 max-w-[1376px] px-4 py-8 text-taupe sm:px-8"
        >
          {affichee.isError ? t.versionIndisponible(numero) : t.chargementVersion(numero)}
        </p>
      </div>
    )
  }

  return (
    <Page
      p={p}
      jeton={jeton}
      actuelle={actuelle}
      comparaison={comparaison}
      choix={
        <ChoixDesVersions
          versions={actuelle.versions}
          numero={numero}
          numeroCompare={numeroCompare}
          onAfficher={afficher}
          onComparer={setNumeroCompare}
          chargement={numeroCompare !== null && !reference}
          langue={actuelle.langue}
        />
      }
      onActuelle={() => afficher(actuelle.version)}
    />
  )
}

function Page({
  p,
  jeton,
  actuelle,
  comparaison,
  choix,
  onActuelle,
}: {
  p: Proposition
  jeton: string
  actuelle: Proposition
  comparaison: Comparaison | undefined
  choix: React.ReactNode
  onActuelle: () => void
}) {
  const langue = p.langue
  const t = TEXTES[langue]
  const destination = p.destination
  const mois = p.demande.dateDebut ? new Date(p.demande.dateDebut).getMonth() : null
  const temperature = mois === null ? null : (destination?.temperatures[mois] ?? null)
  const confirmee = p.etat === 'confirmee'
  const logements = p.prestations.filter((x) => x.type === 'logement')
  const activites = p.prestations.filter((x) => x.type === 'activite')

  return (
    <div className="pb-16" lang={langue}>
      {/* Visible sans défiler : prix estimés tant que le conseiller n'a pas confirmé. */}
      <div
        className={cn(
          'border-b',
          confirmee ? 'border-[#A9C3DE] bg-[#BCD3EA]' : 'border-[#C9C0AD] bg-[#EAE2D2]',
        )}
      >
        <p
          className={cn(
            'mx-auto m-0 max-w-[1376px] px-4 py-3 text-sm font-semibold sm:px-8',
            confirmee ? 'text-[#16233F]' : 'text-[#16233F]',
          )}
        >
          {p.mention}
          {!confirmee && t.confirmationAnnoncee}
        </p>
      </div>

      {choix}

      {p.version !== actuelle.version && (
        <p
          role="status"
          className="mx-auto m-0 mt-4 flex max-w-[1376px] flex-wrap items-center gap-x-3 gap-y-1 px-4 text-sm font-semibold text-rouille sm:px-8"
        >
          {t.versionRemplacee(p.version, actuelle.version)}
          <button
            type="button"
            onClick={onActuelle}
            className="rounded-full underline underline-offset-2 hover:text-encre"
          >
            {t.voirActuelle}
          </button>
        </p>
      )}

      {p.version === actuelle.version && p.versionDemandee !== null && (
        <p
          role="status"
          className="mx-auto m-0 mt-4 max-w-[1376px] px-4 text-sm font-semibold text-rouille sm:px-8"
        >
          {t.lienRemplace(p.versionDemandee, p.version)}
        </p>
      )}

      <section className="mx-auto flex max-w-[1376px] flex-wrap gap-8 px-4 pt-8 pb-6 sm:px-8 sm:pt-10 lg:gap-10">
        <div className="flex min-w-0 flex-[1_1_560px] flex-col gap-4">
          <span className="text-[13px] font-extrabold text-rouille">
            Version {p.version} ·{' '}
            {dateLongue(confirmee && p.confirmeeLe ? p.confirmeeLe : p.creeLe, langue)}
            {p.demande.typeDeVoyage && ` · ${typeDeVoyage(p.demande.typeDeVoyage, langue)}`}
          </span>
          <h1 className="m-0 font-display text-[40px] leading-[1.05] font-semibold tracking-[-1.5px] sm:text-[52px]">
            {titreProposition(p)}
          </h1>
          {p.pourquoi && <p className="m-0 text-lg leading-relaxed text-taupe">{p.pourquoi}</p>}
          <div className="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3">
            <Chiffre label={t.voyageurs} valeur={voyageurs(p.demande, langue)} />
            {p.demande.dateDebut && (
              <Chiffre label={t.depart} valeur={dateLongue(p.demande.dateDebut, langue)} />
            )}
            {mois !== null && temperature !== null && (
              <Chiffre
                label={t.temperatureEn(nomDuMois(mois, langue))}
                valeur={`${Math.round(temperature)} °C`}
              />
            )}
          </div>
          <div className="no-print flex flex-wrap gap-2.5 pt-2">
            {/* PDF généré par l'API (#213), pour la version affichée. */}
            <a
              href={pdfProposition(jeton, p.version)}
              download
              className="flex min-h-11 items-center gap-2 rounded-full border-[1.5px] border-encre px-[18px] text-sm font-bold text-encre no-underline hover:bg-sable"
            >
              <IconTelecharger size={18} />
              {t.telechargerPdf}
            </a>
            <Link
              to="/espace"
              className="flex min-h-11 items-center rounded-full px-[18px] text-sm font-bold text-rouille hover:bg-sable"
            >
              {t.suivreVoyage}
            </Link>
          </div>
          {comparaison && (
            <CeQuiAChange comparaison={comparaison} versionAffichee={p.version} langue={langue} />
          )}
        </div>
        {destination?.photo ? (
          <img
            src={destination.photo}
            alt={destination.photoAlt ?? ''}
            className="block min-h-[260px] w-full min-w-0 flex-[1_1_420px] rounded-[28px] object-cover sm:min-h-[340px]"
          />
        ) : (
          <div className="min-h-[200px] min-w-0 flex-[1_1_420px] rounded-[28px] bg-sable" />
        )}
      </section>

      <section className="mx-auto flex max-w-[1376px] flex-wrap items-start gap-8 px-4 pt-4 sm:px-8">
        <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
          <h2 className="m-0 font-display text-[30px] font-semibold">{t.programme}</h2>
          <ol className="m-0 flex list-none flex-col overflow-hidden rounded-[22px] bg-white p-0 shadow-[0_8px_24px_rgba(22,35,63,0.08)]">
            {[
              ...p.programme.map((j) => ({ j, changement: comparaison?.jours.get(j.jour) })),
              ...(comparaison?.joursRetires ?? []).map((j) => ({
                j,
                changement: 'retire' as const,
              })),
            ].map(({ j, changement }) => (
              <li
                key={`${changement === 'retire' ? 'retire-' : ''}${j.jour}`}
                className={cn(
                  'flex items-baseline gap-4 border-b border-sable px-5 py-3.5 last:border-b-0',
                  changement && SURLIGNAGE[changement],
                )}
              >
                <span className="flex w-16 shrink-0 flex-col">
                  <span className="font-display font-semibold text-terre">
                    {t.jour}
                    {j.jour}
                  </span>
                  <span className="text-xs text-taupe">{dateJour(j.date, langue)}</span>
                </span>
                <span className="flex flex-1 flex-col gap-0.5">
                  <strong className={cn('text-base', changement === 'retire' && 'line-through')}>
                    {j.titre}
                  </strong>
                  <span className="text-sm text-taupe">{j.description}</span>
                </span>
                <Marque changement={changement} t={t} />
              </li>
            ))}
          </ol>

          {p.argumentaire.length > 0 && (
            <>
              <h2 className="m-0 mt-4 font-display text-[30px] font-semibold">{t.pourquoi}</h2>
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

          <Prestations
            titre={t.hebergement}
            liste={logements}
            retirees={comparaison?.prestationsRetirees.filter((x) => x.type === 'logement')}
            etats={comparaison?.prestations}
            t={t}
          />
          <Prestations
            titre={t.activites}
            liste={activites}
            retirees={comparaison?.prestationsRetirees.filter((x) => x.type === 'activite')}
            etats={comparaison?.prestations}
            t={t}
          />
        </div>

        <Budget
          proposition={p}
          jeton={jeton}
          comparaison={comparaison}
          actuelle={p.version === actuelle.version}
        />
      </section>
    </div>
  )
}

function Chiffre({ label, valeur }: { label: string; valeur: string }) {
  return (
    <div className="flex flex-col gap-0.5 rounded-2xl bg-white px-4 py-3.5 shadow-[0_2px_10px_rgba(22,35,63,0.06)]">
      <span className="text-xs font-semibold text-taupe">{label}</span>
      <span className="font-display text-xl font-semibold">{valeur}</span>
    </div>
  )
}

function Prestations({
  titre,
  liste,
  retirees = [],
  etats,
  t,
}: {
  titre: string
  liste: Proposition['prestations']
  retirees?: Proposition['prestations']
  etats?: Map<number, Changement>
  t: Textes
}) {
  if (liste.length === 0 && retirees.length === 0) return null
  return (
    <>
      <h2 className="m-0 mt-4 font-display text-[30px] font-semibold">{titre}</h2>
      <div className="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-4">
        {[
          ...liste.map((x) => ({ x, changement: etats?.get(x.id) })),
          ...retirees.map((x) => ({ x, changement: 'retire' as const })),
        ].map(({ x, changement }) => (
          <article
            key={`${changement === 'retire' ? 'retire-' : ''}${x.id}`}
            className={cn(
              'flex overflow-hidden rounded-[22px] bg-white shadow-[0_8px_24px_rgba(22,35,63,0.08)]',
              changement && SURLIGNAGE[changement],
              changement && CONTOUR[changement],
            )}
          >
            {x.photo && (
              <img
                src={x.photo}
                alt=""
                className={cn(
                  'block w-28 shrink-0 object-cover sm:w-32',
                  changement === 'retire' && 'opacity-50 grayscale',
                )}
              />
            )}
            <div className="flex flex-col items-start gap-1.5 px-4 py-3.5">
              <Marque changement={changement} t={t} />
              <h3
                className={cn('m-0 text-base font-bold', changement === 'retire' && 'line-through')}
              >
                {x.nom}
              </h3>
              {x.pointsForts.length > 0 && (
                <ul className="m-0 list-disc pl-4 text-sm text-taupe">
                  {x.pointsForts.map((pf) => (
                    <li key={pf}>{pf}</li>
                  ))}
                </ul>
              )}
            </div>
          </article>
        ))}
      </div>
    </>
  )
}

function Budget({
  proposition: p,
  jeton,
  comparaison,
  actuelle,
}: {
  proposition: Proposition
  jeton: string
  comparaison: Comparaison | undefined
  actuelle: boolean
}) {
  const queryClient = useQueryClient()
  const t = TEXTES[p.langue]
  const confirmee = p.etat === 'confirmee'
  const action = useMutation({
    mutationFn: (quoi: ActionProposition) => agirSurProposition(p.jeton, quoi),
    // La page est en cache sous le jeton du lien ouvert, qui peut être celui d'une version remplacée.
    onSuccess: (miseAJour) => queryClient.setQueryData(propositionQuery(jeton).queryKey, miseAJour),
  })

  return (
    <aside className="flex min-w-0 flex-[1_1_360px] flex-col gap-4 rounded-[28px] bg-brun p-6 text-creme lg:sticky lg:top-6">
      <h2 className="m-0 font-display text-2xl font-semibold">
        {confirmee ? t.budgetConfirme : t.budgetEstime}
      </h2>
      <div className="flex flex-col gap-0.5">
        <span className="font-display text-5xl font-semibold tracking-[-1px]">
          {euros(p.totalCentimes)}
        </span>
        <span className="text-sm text-sable">
          {t.parPersonne(euros(p.totalParPersonneCentimes), euros(p.fraisDeDossierCentimes))}
        </span>
        {comparaison && (
          <span className="mt-1 text-sm font-bold">
            <EcartDeBudget ecart={comparaison.total} langue={p.langue} sombre />
            {t.parRapportA(comparaison.version)}
          </span>
        )}
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
                {t.niveaux[n.valeur]}
                {retenu && <span className="sr-only">{t.niveauDetaille}</span>}
              </dt>
              <dd className="m-0 text-[15px] font-bold">{euros(budget.totalCentimes)}</dd>
              {comparaison?.niveaux[n.valeur] && comparaison.niveaux[n.valeur]!.centimes !== 0 && (
                <dd className="m-0 text-[11px] font-bold">
                  <EcartDeBudget
                    ecart={comparaison.niveaux[n.valeur]!}
                    langue={p.langue}
                    sombre={!retenu}
                  />
                </dd>
              )}
            </div>
          )
        })}
      </dl>

      {(p.lignes.length > 0 || (comparaison?.lignesRetirees.length ?? 0) > 0) && (
        <div className="flex flex-col gap-2 border-t border-white/15 pt-3.5">
          <span className="text-sm font-bold">{t.inclus}</span>
          <ul className="m-0 flex flex-col gap-1 pl-[18px] text-[13px] text-sable">
            {[
              ...p.lignes.map((l) => ({ l, changement: comparaison?.lignes.get(cleLigne(l)) })),
              ...(comparaison?.lignesRetirees ?? []).map((l) => ({
                l,
                changement: 'retire' as const,
              })),
            ].map(({ l, changement }, i) => (
              <li key={i}>
                <span className={cn(changement === 'retire' && 'line-through opacity-70')}>
                  {l.libelle}
                  {l.quantite > 1 && ` × ${l.quantite}`}
                </span>{' '}
                <Marque changement={changement} t={t} />
              </li>
            ))}
          </ul>
        </div>
      )}

      {(p.hypotheses.length > 0 || (comparaison?.hypothesesRetirees.length ?? 0) > 0) && (
        <div className="flex flex-col gap-2 border-t border-white/15 pt-3.5">
          <span className="text-sm font-bold">{t.hypotheses}</span>
          <ul className="m-0 pl-[18px] text-[13px] leading-relaxed text-sable">
            {p.hypotheses.map((h) => (
              <li key={h}>
                {h}{' '}
                <Marque
                  changement={comparaison?.hypothesesAjoutees.has(h) ? 'ajoute' : undefined}
                  t={t}
                />
              </li>
            ))}
            {comparaison?.hypothesesRetirees.map((h) => (
              <li key={`retiree-${h}`}>
                <span className="line-through opacity-70">{h}</span>{' '}
                <Marque changement="retire" t={t} />
              </li>
            ))}
          </ul>
        </div>
      )}

      {action.isError && (
        <p role="alert" className="m-0 rounded-xl bg-white/10 px-3 py-2.5 text-[13px] font-bold">
          {action.error.message}
        </p>
      )}
      {actuelle && p.actions.accepter && (
        <button
          type="button"
          disabled={action.isPending}
          onClick={() => action.mutate('acceptation')}
          className="no-print flex min-h-12 items-center justify-center rounded-[14px] bg-creme font-bold text-encre hover:bg-sable disabled:opacity-60"
        >
          {t.accepter}
        </button>
      )}
      {actuelle && p.actions.demanderConfirmation && (
        <button
          type="button"
          disabled={action.isPending}
          onClick={() => action.mutate('demande-de-confirmation')}
          className="no-print flex min-h-12 items-center justify-center rounded-[14px] bg-creme font-bold text-encre hover:bg-sable disabled:opacity-60"
        >
          {t.demanderConfirmation}
        </button>
      )}
      <p className="m-0 text-center text-xs text-sable">{etatSuivi(p)}</p>
    </aside>
  )
}

/** Fond d'un élément ajouté, modifié ou retiré par rapport à la version comparée. */
const SURLIGNAGE: Record<Changement, string> = {
  ajoute: 'bg-[#EAF1F8]',
  modifie: 'bg-[#FBF0EC]',
  retire: 'bg-creme',
}

const CONTOUR: Record<Changement, string> = {
  ajoute: 'ring-2 ring-[#A9C3DE]',
  modifie: 'ring-2 ring-[#C9C0AD]',
  retire: 'ring-1 ring-lin',
}

const MARQUES: Record<Changement, string> = {
  ajoute: 'bg-[#BCD3EA] text-[#16233F]',
  modifie: 'bg-[#F7E3DD] text-[#16233F]',
  retire: 'bg-[#F7E3DD] text-[#A8432C]',
}

/** Étiquette du changement : le texte le dit, la couleur ne fait que le souligner. */
function Marque({ changement, t }: { changement: Changement | undefined; t: Textes }) {
  if (!changement) return null
  return (
    <span
      className={cn(
        'inline-block shrink-0 rounded-full px-2 py-0.5 text-[11px] font-extrabold whitespace-nowrap no-underline',
        MARQUES[changement],
      )}
    >
      {t.marques[changement]}
    </span>
  )
}

/** « +166 € (+1,8 %) », en rouge s'il monte et en vert s'il baisse. */
function EcartDeBudget({
  ecart,
  langue,
  sombre = false,
}: {
  ecart: Ecart
  langue: Langue
  sombre?: boolean
}) {
  if (ecart.centimes === 0) return <span>{TEXTES[langue].memeBudget}</span>
  const hausse = ecart.centimes > 0
  return (
    <span
      className={cn(
        sombre
          ? hausse
            ? 'text-[#F2C2B3]'
            : 'text-[#BCD3EA]'
          : hausse
            ? 'text-[#A8432C]'
            : 'text-[#16233F]',
      )}
    >
      {formatEcart(ecart.centimes)}
      {ecart.pourcentage !== null && ` (${formatPourcentage(ecart.pourcentage, langue)})`}
    </span>
  )
}

/** Résumé des changements, sous l'en-tête : la demande et le budget. */
function CeQuiAChange({
  comparaison: c,
  versionAffichee,
  langue,
}: {
  comparaison: Comparaison
  versionAffichee: number
  langue: Langue
}) {
  const t = TEXTES[langue]
  const titre =
    c.version < versionAffichee ? t.changeDepuis(c.version) : t.differencesAvec(c.version)
  return (
    <section
      aria-label={titre}
      className="flex flex-col gap-2 rounded-[18px] border-[1.5px] border-[#C9C0AD] bg-[#FBF0EC] px-5 py-4"
    >
      <h2 className="m-0 text-base font-bold">{titre}</h2>
      {c.nombre === 0 ? (
        <p className="m-0 text-sm text-taupe">{t.aucunChangement}</p>
      ) : (
        <ul className="m-0 flex flex-col gap-1 pl-[18px] text-sm">
          {c.demande.map((d) => (
            <li key={d.libelle}>
              <strong>{d.libelle}</strong>
              {langue === 'en' ? ': ' : ' : '}
              {d.apres}{' '}
              <span className="text-taupe">
                ({t.avant}
                {langue === 'en' ? ': ' : ' : '}
                {d.avant})
              </span>
            </li>
          ))}
          <li>
            <strong>{t.budget}</strong>
            {langue === 'en' ? ': ' : ' : '}
            <EcartDeBudget ecart={c.total} langue={langue} />
            {c.total.centimes !== 0 && (
              <span className="text-taupe">
                {t.auLieuDe(
                  euros(c.total.apres),
                  euros(c.total.avant),
                  formatEcart(c.parPersonne.centimes),
                )}
              </span>
            )}
          </li>
          <li className="text-taupe">{t.surlignes}</li>
        </ul>
      )}
    </section>
  )
}

function ChoixDesVersions({
  versions,
  numero,
  numeroCompare,
  onAfficher,
  onComparer,
  chargement = false,
  langue,
}: {
  versions: Proposition['versions']
  numero: number
  numeroCompare: number | null
  onAfficher: (version: number) => void
  onComparer: (version: number | null) => void
  chargement?: boolean
  langue: Langue
}) {
  if (versions.length < 2) return null
  const t = TEXTES[langue]
  const derniere = Math.max(...versions.map((v) => v.version))
  const libelle = (v: Proposition['versions'][number]) =>
    `Version ${v.version} · ${dateCourte(v.creeLe, langue)} · ${euros(v.totalCentimes)}${
      v.version === derniere ? t.actuelle : ''
    }${v.etat === 'confirmee' ? t.confirmee : ''}`
  const selectClasse =
    'min-h-11 rounded-xl border-[1.5px] border-lin bg-creme px-3 text-sm font-semibold text-encre focus:border-terre focus:bg-white focus:outline-none'

  return (
    <div className="no-print mx-auto mt-4 flex max-w-[1376px] flex-wrap items-end gap-3 px-4 sm:px-8">
      <label className="flex flex-col gap-1 text-xs font-bold text-taupe">
        {t.versionAffichee}
        <select
          className={selectClasse}
          value={numero}
          onChange={(e) => onAfficher(Number(e.target.value))}
        >
          {versions.map((v) => (
            <option key={v.version} value={v.version}>
              {libelle(v)}
            </option>
          ))}
        </select>
      </label>
      <label className="flex flex-col gap-1 text-xs font-bold text-taupe">
        {t.comparerAvec}
        <select
          className={selectClasse}
          value={numeroCompare ?? ''}
          onChange={(e) => onComparer(e.target.value === '' ? null : Number(e.target.value))}
        >
          <option value="">{t.aucuneVersion}</option>
          {versions
            .filter((v) => v.version !== numero)
            .map((v) => (
              <option key={v.version} value={v.version}>
                {libelle(v)}
              </option>
            ))}
        </select>
      </label>
      {chargement && (
        <span role="status" className="pb-3 text-sm text-taupe">
          {t.chargementComparaison}
        </span>
      )}
    </div>
  )
}

const dateCourte = (iso: string, langue: Langue) =>
  new Intl.DateTimeFormat(LOCALE[langue], { day: 'numeric', month: 'short' }).format(new Date(iso))

/** Où en est la proposition, du point de vue du client. */
function etatSuivi(p: Proposition) {
  const t = TEXTES[p.langue]
  if (p.statut === 'acceptee') return t.acceptee
  if (p.demande.statut === 'a_confirmer') return t.confirmationTransmise
  if (p.etat === 'confirmee') return t.prixVerifies
  return t.rienReserve
}
