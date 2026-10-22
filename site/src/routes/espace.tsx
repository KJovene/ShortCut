import { useQuery, useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute, Link, redirect } from '@tanstack/react-router'
import { useState } from 'react'
import { IconBulle, IconCoche, IconPersonne } from '@/components/ui/icons'
import { SautDuO } from '@/components/ui/TitreMisEnValeur'
import { destinationsQuery } from '@/features/catalogue/catalogue'
import {
  assistantDisponible,
  auteurMessage,
  dateCourte,
  type DemandeClient,
  destinationDe,
  estEnCours,
  etapes,
  initiales,
  mesDemandesQuery,
  metaDemande,
  moisDeDepart,
  prenomConseiller,
  statutClient,
  titreDemande,
  VOYAGES_EN_COURS_MAX,
} from '@/features/espace/espace'
import { urlRendezVous } from '@/features/espace/rendezVous'
import { TEXTES } from '@/features/espace/textes'
import { useChat } from '@/features/chat/chatContext'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { profilQuery } from '@/lib/auth'
import { cn } from '@/lib/cn'
import { euros, LOCALE, langueDuSite, nomDuMois, useLangue } from '@/lib/langue'

export const Route = createFileRoute('/espace')({
  beforeLoad: async ({ context }) => {
    const profil = await context.queryClient.ensureQueryData(profilQuery)
    if (!profil) {
      throw redirect({ to: '/connexion' })
    }
  },
  loader: ({ context }) => {
    // Le catalogue (photos, climat) se charge en parallèle, sans retenir la page.
    void context.queryClient.prefetchQuery(destinationsQuery())
    return context.queryClient.ensureQueryData(mesDemandesQuery)
  },
  head: () => {
    const t = TEXTES[langueDuSite()].meta
    return {
      meta: [
        { title: t.titre },
        { name: 'description', content: t.description },
        { name: 'robots', content: 'noindex' },
      ],
    }
  },
  component: PageEspace,
})

function PageEspace() {
  const langue = useLangue()
  const t = TEXTES[langue]
  const { data: profil } = useSuspenseQuery(profilQuery)
  const { data: demandes } = useSuspenseQuery(mesDemandesQuery)
  const ouvrirAssistant = useOuvrirAssistant()

  // Le voyage suivi en tête de page : celui que le client a choisi parmi ses voyages en cours, sinon le plus
  // récent encore en cours, sinon le plus récent.
  const enCours = demandes.filter(estEnCours)
  const [choisi, setChoisi] = useState<number | null>(null)
  const suivi = enCours.find((d) => d.id === choisi) ?? enCours[0] ?? demandes[0]
  const complet = enCours.length >= VOYAGES_EN_COURS_MAX
  const prenom = profil?.prenom ?? profil?.nom

  return (
    <div className="mx-auto flex max-w-[1376px] flex-col gap-8 px-4 pt-9 pb-16 sm:px-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="flex flex-col gap-1.5">
          <span className="text-[13px] font-extrabold text-rouille">
            {t.monEspace}
          </span>
          <h1 className="m-0 font-display text-[34px] leading-[1.05] font-semibold tracking-[-1px] sm:text-[46px]">
            {t.bonjour(prenom)}{' '}
            <SautDuO texte={suivi && estEnCours(suivi) ? t.voyageAvance : t.ouPartons} />
          </h1>
        </div>
        <div className="flex flex-col items-end gap-1.5">
          <button
            type="button"
            onClick={() => ouvrirAssistant()}
            disabled={complet}
            aria-describedby={complet ? 'voyages-max' : undefined}
            className="flex min-h-12 items-center gap-2 rounded-full bg-terre px-[22px] text-[15px] font-extrabold text-white hover:bg-rouille disabled:cursor-not-allowed disabled:bg-beige disabled:text-taupe"
          >
            <span aria-hidden="true" className="text-xl leading-none">
              +
            </span>
            {t.nouveauVoyage}
          </button>
          {complet && (
            <p id="voyages-max" className="m-0 max-w-xs text-right text-[13px] text-taupe">
              {t.voyagesMax(VOYAGES_EN_COURS_MAX)}
            </p>
          )}
        </div>
      </div>

      {enCours.length > 1 && (
        <div role="group" aria-label={t.mesVoyagesEnCours} className="flex flex-wrap gap-2.5">
          {enCours.map((d) => {
            const actif = d.id === suivi?.id
            return (
              <button
                key={d.id}
                type="button"
                aria-pressed={actif}
                onClick={() => setChoisi(d.id)}
                className={cn(
                  'flex min-h-12 flex-col items-start justify-center rounded-2xl border-[1.5px] px-4 py-2 text-left',
                  actif
                    ? 'border-encre bg-encre text-creme'
                    : 'border-lin bg-white text-encre hover:border-beige',
                )}
              >
                <span className="text-[15px] font-bold">{titreDemande(d, langue)}</span>
                <span className={cn('text-xs', actif ? 'text-sable' : 'text-taupe')}>
                  {statutClient(d, langue).label}
                </span>
              </button>
            )
          })}
        </div>
      )}

      {suivi ? <VoyageSuivi key={suivi.id} demande={suivi} /> : <AucunVoyage />}

      {demandes.some((d) => d.conversation) && <Conversations demandes={demandes} />}
    </div>
  )
}

function AucunVoyage() {
  const t = TEXTES[useLangue()]
  const ouvrirAssistant = useOuvrirAssistant()

  return (
    <section className="flex flex-col items-start gap-4 rounded-[28px] bg-white p-8 shadow-[0_18px_50px_rgba(22,35,63,0.10)]">
      <h2 className="m-0 font-display text-[28px] font-semibold">{t.aucunVoyage}</h2>
      <p className="m-0 max-w-xl text-taupe">{t.aucunVoyageTexte}</p>
      <button
        type="button"
        onClick={() => ouvrirAssistant()}
        className="flex min-h-11 items-center rounded-full bg-encre px-5 text-sm font-bold text-creme hover:bg-rouille"
      >
        {t.construireMonVoyage}
      </button>
    </section>
  )
}

function VoyageSuivi({ demande }: { demande: DemandeClient }) {
  const langue = useLangue()
  const t = TEXTES[langue]
  // Photo et climat lus dans le catalogue de l'API ; sans lui, le voyage s'affiche sans photo.
  const { data: destinations = [] } = useQuery(destinationsQuery(langue))
  const destination = destinationDe(demande, destinations)
  const mois = moisDeDepart(demande)
  const temperature = mois === null ? null : (destination?.temperatures[mois] ?? null)
  const statut = statutClient(demande, langue)
  const conseiller = prenomConseiller(demande)

  return (
    <section
      aria-labelledby="titre-voyage"
      className="flex flex-wrap overflow-hidden rounded-[28px] bg-white shadow-[0_18px_50px_rgba(22,35,63,0.10)]"
    >
      <div className="relative min-h-[240px] flex-[1_1_380px] bg-sable">
        {destination?.photo && (
          <img
            src={destination.photo}
            alt={destination.alt}
            className="absolute inset-0 size-full object-cover"
          />
        )}
        {mois !== null && temperature !== null && (
          <span className="absolute top-[18px] left-[18px] rounded-full bg-white px-3 py-1.5 text-[13px] font-extrabold">
            {t.temperatureEn(temperature, nomDuMois(mois, langue))}
          </span>
        )}
      </div>

      <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-[22px] px-6 py-7 sm:px-8">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="flex flex-col gap-1">
            <span className="text-[13px] font-bold text-taupe">{metaDemande(demande, langue)}</span>
            <h2
              id="titre-voyage"
              className="m-0 font-display text-[28px] font-semibold sm:text-[32px]"
            >
              {titreDemande(demande, langue)}
            </h2>
          </div>
          <span
            className={cn('rounded-full px-3.5 py-1.5 text-[13px] font-extrabold', statut.classe)}
          >
            {statut.label}
          </span>
        </div>

        {demande.statut !== 'perdue' && <Etapes demande={demande} />}

        {estEnCours(demande) && <ModifierVoyage demande={demande} />}

        <div className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-3.5">
          <div className="flex flex-col gap-1.5 rounded-[18px] bg-creme px-[18px] py-4">
            {demande.proposition ? (
              <>
                <span className="text-xs font-extrabold text-rouille">
                  {demande.proposition.etat === 'preparee'
                    ? t.recapitulatifEnRelecture
                    : t.propositionActuelle(demande.proposition.version)}
                </span>
                <span className="font-display text-[30px] font-semibold">
                  {euros(demande.proposition.totalCentimes / 100, langue)}
                </span>
                <span className="text-[13px] text-taupe">
                  {demande.proposition.niveauBudget} ·{' '}
                  {euros(demande.proposition.totalParPersonneCentimes / 100, langue)}{' '}
                  {t.parPersonne} ·{' '}
                  {demande.proposition.etat === 'confirmee'
                    ? t.prixConfirme
                    : demande.proposition.etat === 'preparee'
                      ? t.prixEstimeAVerifier
                      : t.prixEstime}
                </span>
                <Link
                  to="/proposition/$id"
                  params={{ id: demande.proposition.jeton }}
                  className="mt-1.5 flex min-h-10 items-center self-start rounded-full bg-encre px-4 text-[13px] font-extrabold text-creme hover:bg-rouille"
                >
                  {demande.proposition.etat === 'preparee'
                    ? t.voirRecapitulatif
                    : t.voirProposition}
                </Link>
              </>
            ) : (
              <>
                <span className="text-xs font-extrabold text-rouille">
                  {t.proposition}
                </span>
                <span className="text-[15px] font-bold">{t.enPreparation}</span>
                <span className="text-[13px] text-taupe">{t.apparaitra}</span>
              </>
            )}
          </div>

          <div className="flex flex-col gap-2 rounded-[18px] bg-creme px-[18px] py-4">
            <span className="text-xs font-extrabold text-rouille">
              {t.votreConseiller}
            </span>
            {demande.conseiller ? (
              <>
                <div className="flex items-center gap-3">
                  <span
                    aria-hidden="true"
                    className="flex size-12 items-center justify-center rounded-full bg-sable font-extrabold text-rouille"
                  >
                    {initiales(demande.conseiller.prenom, demande.conseiller.nom)}
                  </span>
                  <strong className="text-base">
                    {[demande.conseiller.prenom, demande.conseiller.nom].filter(Boolean).join(' ')}
                  </strong>
                </div>
                <span className="text-[13px] leading-[1.45] text-taupe">
                  {t.suitVotreDossier(conseiller)}
                  {assistantDisponible(demande) && t.ajustementLeger}
                </span>
              </>
            ) : (
              <span className="text-[13px] leading-[1.45] text-taupe">{t.conseillerVaPrendre}</span>
            )}
          </div>
        </div>
      </div>
    </section>
  )
}

/**
 * « Modifier le voyage » : un rendez-vous avec le conseiller pour les changements importants (Calendly), ou la
 * conversation avec l'assistant pour un ajustement léger, tant qu'aucun conseiller n'a repris la main.
 */
function ModifierVoyage({ demande }: { demande: DemandeClient }) {
  const t = TEXTES[useLangue()]
  const [ouvert, setOuvert] = useState(false)
  const { reprendre } = useChat()
  const conseiller = prenomConseiller(demande)
  const conversation = demande.conversation
  const rendezVous = urlRendezVous()

  return (
    <div className="flex flex-col gap-3 rounded-[18px] border-[1.5px] border-lin p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="m-0 text-sm text-taupe">{t.uneDateAChanger}</p>
        <button
          type="button"
          aria-expanded={ouvert}
          aria-controls="modifier-voyage"
          onClick={() => setOuvert((o) => !o)}
          className="flex min-h-11 items-center rounded-full border-[1.5px] border-encre px-5 text-sm font-bold hover:bg-sable"
        >
          {t.modifierLeVoyage}
        </button>
      </div>

      {ouvert && (
        <div
          id="modifier-voyage"
          className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-3"
        >
          {rendezVous && (
            <a
              href={rendezVous}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-start gap-3 rounded-2xl bg-creme p-4 text-encre no-underline hover:bg-sable"
            >
              <span
                aria-hidden="true"
                className="flex size-10 shrink-0 items-center justify-center rounded-full bg-encre text-creme"
              >
                <IconPersonne size={20} />
              </span>
              <span className="flex flex-col gap-0.5">
                <strong>
                  {t.rendezVousAvec(conseiller)}
                  <span className="sr-only">{t.nouvelOnglet}</span>
                </strong>
                <span className="text-[13px] text-taupe">{t.rendezVousTexte}</span>
              </span>
            </a>
          )}

          {conversation && assistantDisponible(demande) ? (
            <button
              type="button"
              onClick={() =>
                reprendre({
                  jeton: conversation.jeton,
                  messages: conversation.messages,
                  fiche: conversation.fiche,
                })
              }
              className="flex items-start gap-3 rounded-2xl bg-creme p-4 text-left hover:bg-sable"
            >
              <span
                aria-hidden="true"
                className="flex size-10 shrink-0 items-center justify-center rounded-full bg-terre text-white"
              >
                <IconBulle size={20} />
              </span>
              <span className="flex flex-col gap-0.5">
                <strong>{t.continuerAssistant}</strong>
                <span className="text-[13px] text-taupe">{t.continuerAssistantTexte}</span>
              </span>
            </button>
          ) : (
            <p className="m-0 rounded-2xl bg-creme p-4 text-[13px] text-taupe">
              {t.changementsParConseiller(conseiller)}
              {rendezVous ? t.surRendezVous : '.'}
            </p>
          )}
        </div>
      )}
    </div>
  )
}

const STYLE_ETAPE = {
  faite: {
    pastille: 'border-vert bg-vert text-white',
    trait: 'bg-vert',
    titre: 'text-encre',
  },
  'en-cours': {
    pastille: 'border-terre bg-white text-terre',
    trait: 'bg-lin',
    titre: 'text-rouille',
  },
  'a-venir': {
    pastille: 'border-beige bg-white text-taupe-clair',
    trait: 'bg-lin',
    titre: 'text-taupe-clair',
  },
} as const

function Etapes({ demande }: { demande: DemandeClient }) {
  const langue = useLangue()
  const t = TEXTES[langue]
  const liste = etapes(demande, langue)

  return (
    <ol
      aria-label={t.etapesDuVoyage}
      className="m-0 grid list-none grid-cols-1 gap-4 p-0 sm:grid-cols-4 sm:gap-0"
    >
      {liste.map((etape, i) => {
        const style = STYLE_ETAPE[etape.etat]
        return (
          <li
            key={etape.titre}
            aria-current={etape.etat === 'en-cours' ? 'step' : undefined}
            className="flex flex-col gap-2 pr-2"
          >
            <div className="flex items-center">
              <span
                className={cn(
                  'flex size-[30px] flex-none items-center justify-center rounded-full border-[2.5px] text-[13px] font-extrabold',
                  style.pastille,
                )}
              >
                {etape.etat === 'faite' ? <IconCoche size={14} strokeWidth={3.5} /> : i + 1}
              </span>
              {i < liste.length - 1 && (
                <span
                  aria-hidden="true"
                  className={cn('ml-1.5 hidden h-[3px] flex-1 rounded-sm sm:block', style.trait)}
                />
              )}
            </div>
            <strong className={cn('text-sm leading-[1.3]', style.titre)}>
              {etape.titre}
              {etape.etat === 'faite' && <span className="sr-only">{t.terminee}</span>}
              {etape.etat === 'en-cours' && <span className="sr-only">{t.enCours}</span>}
            </strong>
            <span className="text-xs leading-[1.4] text-taupe">{etape.detail}</span>
          </li>
        )
      })}
    </ol>
  )
}

function Conversations({ demandes }: { demandes: DemandeClient[] }) {
  const t = TEXTES[useLangue()]
  const avecConversation = demandes.filter((d) => d.conversation)
  const [choisie, setChoisie] = useState(avecConversation[0]!.id)
  const courante = avecConversation.find((d) => d.id === choisie) ?? avecConversation[0]!
  const nombre = avecConversation.length

  return (
    <section aria-labelledby="titre-conversations" className="flex flex-col gap-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2.5">
        <h2
          id="titre-conversations"
          className="m-0 font-display text-[30px] font-semibold sm:text-[34px]"
        >
          <SautDuO texte={t.mesConversations} />
        </h2>
        <span className="text-sm text-taupe">{t.nbConversations(nombre)}</span>
      </div>

      <div className="flex flex-wrap items-stretch gap-5">
        <ul className="m-0 flex min-w-0 flex-[1_1_340px] list-none flex-col gap-2.5 p-0">
          {avecConversation.map((demande) => (
            <li key={demande.id}>
              <CarteConversation
                demande={demande}
                active={demande.id === courante.id}
                onChoisir={() => setChoisie(demande.id)}
              />
            </li>
          ))}
        </ul>
        <Fil demande={courante} />
      </div>
    </section>
  )
}

function CarteConversation({
  demande,
  active,
  onChoisir,
}: {
  demande: DemandeClient
  active: boolean
  onChoisir: () => void
}) {
  const langue = useLangue()
  const t = TEXTES[langue]
  const { data: destinations = [] } = useQuery(destinationsQuery(langue))
  const destination = destinationDe(demande, destinations)
  const statut = statutClient(demande, langue)
  const dernierMessage = demande.conversation?.messages.at(-1)?.contenu ?? t.sansMessage

  return (
    <button
      type="button"
      aria-pressed={active}
      onClick={onChoisir}
      className={cn(
        'flex w-full items-center gap-3 rounded-[20px] border-2 p-2.5 text-left',
        active ? 'border-terre bg-white' : 'border-lin bg-creme hover:border-beige',
      )}
    >
      {destination?.photo ? (
        <img
          src={destination.photo}
          alt=""
          className="size-[72px] flex-none rounded-[14px] object-cover"
        />
      ) : (
        <span
          aria-hidden="true"
          className="flex size-[72px] flex-none items-center justify-center rounded-[14px] bg-sable text-rouille"
        >
          <IconBulle size={26} />
        </span>
      )}
      <span className="flex min-w-0 flex-1 flex-col gap-[3px]">
        <span className="flex justify-between gap-2">
          <strong className="text-[15px]">{titreDemande(demande, langue)}</strong>
          <span className="text-xs whitespace-nowrap text-taupe-clair">
            {dateCourte(demande.conversation!.debutLe, langue)}
          </span>
        </span>
        <span className="truncate text-[13px] text-taupe">{dernierMessage}</span>
        <span
          className={cn(
            'mt-0.5 self-start rounded-full px-[9px] py-0.5 text-[11px] font-extrabold',
            statut.classe,
          )}
        >
          {statut.label}
        </span>
      </span>
    </button>
  )
}

const STYLE_MESSAGE = {
  client: 'self-end rounded-[18px_6px_18px_18px] bg-encre text-creme',
  assistant:
    'self-start rounded-[6px_18px_18px_18px] bg-white text-encre shadow-[0_2px_10px_rgba(22,35,63,0.07)]',
  info: 'self-center rounded-full bg-sable text-taupe',
} as const

function Fil({ demande }: { demande: DemandeClient }) {
  const langue = useLangue()
  const t = TEXTES[langue]
  const messages = demande.conversation!.messages
  const conseiller = prenomConseiller(demande)
  const date = new Intl.DateTimeFormat(LOCALE[langue], {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date(demande.conversation!.debutLe))
  const meta = [
    date,
    t.nbMessages(messages.length),
    demande.proposition ? t.propositionVersion(demande.proposition.version) : t.pasDeProposition,
  ].join(' · ')

  return (
    <article
      aria-label={t.conversationSelectionnee}
      className="flex min-w-0 flex-[999_1_560px] flex-col overflow-hidden rounded-3xl bg-white shadow-[0_12px_36px_rgba(22,35,63,0.08)]"
    >
      <div className="flex flex-col gap-0.5 border-b border-sable px-[22px] py-4">
        <strong className="font-display text-[22px] font-semibold">
          {titreDemande(demande, langue)}
        </strong>
        <span className="text-xs text-taupe">{meta}</span>
      </div>
      <div className="flex max-h-[420px] flex-1 flex-col gap-3 overflow-y-auto bg-[#FFFDF8] px-[22px] py-[18px] text-sm leading-normal">
        {messages.length === 0 && <p className="m-0 self-center text-taupe">{t.aucunMessage}</p>}
        {messages.map((message, i) => (
          <p
            key={i}
            className={cn(
              'm-0 max-w-[78%] px-3.5 py-2.5 whitespace-pre-line',
              STYLE_MESSAGE[auteurMessage(message.role)],
            )}
          >
            {message.contenu}
          </p>
        ))}
      </div>
      {conseiller && (
        <p className="m-0 border-t border-sable px-[22px] py-3.5 text-[13px] text-taupe">
          {t.dossierSuiviPar} <strong className="text-encre">{conseiller}</strong>
          {assistantDisponible(demande) ? t.ajustezAvecAssistant : t.modifierParConseiller}
        </p>
      )}
    </article>
  )
}
