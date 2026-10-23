import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { IconCoche, IconFermer } from '@/components/ui/icons'
import { formatDuree, formatEuros } from '@/data/catalogue'
import { destinationsQuery } from '@/features/catalogue/catalogue'
import { cn } from '@/lib/cn'
import { LOCALE } from '@/lib/langue'
import type { Piste, RecapitulatifChat, Suggestion } from '../api'
import { useChat } from '../chatContext'
import type { Message } from '../conversation'
import { TEXTES } from '../textes'

const BULLE_ASSISTANT =
  'rounded-[6px_20px_20px_20px] bg-white shadow-[0_2px_10px_rgba(22,35,63,0.06)]'

const NIVEAUX = ['essentiel', 'equilibre', 'confort'] as const

/**
 * Un message du fil. Les réponses de l'assistant gardent leurs retours à la ligne (listes en « - ») ; la piste, les
 * suggestions et le récapitulatif s'affichent en cartes.
 *
 * @param actif dernier récapitulatif du fil, conversation ouverte : ses options s'ajoutent d'un clic
 */
export function Bulle({ message, actif = false }: { message: Message; actif?: boolean }) {
  if (message.auteur === 'client') {
    return (
      <div className="max-w-[85%] self-end rounded-[20px_6px_20px_20px] bg-encre px-4 py-3 whitespace-pre-line text-creme sm:max-w-[72%]">
        {message.texte}
      </div>
    )
  }
  if ('type' in message) {
    if (message.type === 'confirmation') return <Confirmation texte={message.texte} />
    if (message.type === 'piste') return <CartePiste piste={message.piste} />
    if (message.type === 'suggestions') {
      return (
        <>
          {message.texte.trim() && <TexteAssistant texte={message.texte} />}
          <CartesSuggestions suggestions={message.suggestions} />
        </>
      )
    }
    return <CarteRecapitulatif recapitulatif={message.recapitulatif} actif={actif} />
  }
  return <TexteAssistant texte={message.texte} />
}

function TexteAssistant({ texte }: { texte: string }) {
  return (
    <div
      className={cn(
        'max-w-[85%] self-start px-4 py-3 whitespace-pre-line sm:max-w-[72%]',
        BULLE_ASSISTANT,
      )}
    >
      {texte.trim()}
    </div>
  )
}

/** « 45 min », « 2 h 30 ». */
function duree(minutes: number) {
  return minutes < 60 ? `${minutes} min` : formatDuree(minutes)
}

/** Logements et activités recommandés par l'assistant : chaque carte ouvre sa fiche complète. */
function CartesSuggestions({ suggestions }: { suggestions: Suggestion[] }) {
  const { langue } = useChat()
  const t = TEXTES[langue].suggestions
  const [ouverte, setOuverte] = useState<Suggestion | null>(null)

  return (
    <section aria-label={t.titre} className="w-full max-w-[640px] self-start">
      <ul className="m-0 grid list-none gap-2.5 p-0 sm:grid-cols-2">
        {suggestions.map((s) => (
          <li key={s.id}>
            <button
              type="button"
              onClick={() => setOuverte(s)}
              aria-label={t.voir(s.nom)}
              aria-haspopup="dialog"
              className="flex h-full w-full items-stretch overflow-hidden rounded-2xl border-[1.5px] border-lin bg-white text-left shadow-[0_2px_10px_rgba(22,35,63,0.06)] hover:border-encre"
            >
              {s.photos[0] && (
                <img src={s.photos[0]} alt="" className="w-24 shrink-0 object-cover" />
              )}
              <span className="flex min-w-0 flex-col gap-0.5 px-3.5 py-3">
                <span className="text-xs font-extrabold text-rouille">
                  {t.types[s.type]}
                  {s.categorie && ` · ${s.categorie}`}
                </span>
                <span className="text-[15px] leading-snug font-bold">{s.nom}</span>
                {s.destination.ville && (
                  <span className="text-[13px] text-taupe">{s.destination.ville}</span>
                )}
              </span>
            </button>
          </li>
        ))}
      </ul>
      {ouverte && <FicheSuggestion suggestion={ouverte} onFermer={() => setOuverte(null)} />}
    </section>
  )
}

/**
 * Fiche d'un logement ou d'une activité, au centre de l'écran par-dessus le chat : photos, description, capacité,
 * durée, équipements. Échap, le bouton ou un clic à côté la ferment, sans fermer la fenêtre du chatbot.
 */
function FicheSuggestion({
  suggestion: s,
  onFermer,
}: {
  suggestion: Suggestion
  onFermer: () => void
}) {
  const { langue } = useChat()
  const t = TEXTES[langue].suggestions
  const fermer = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    const precedent = document.activeElement as HTMLElement | null
    const defilement = document.body.style.overflow
    fermer.current?.focus()
    document.body.style.overflow = 'hidden'
    // En capture : Échap ferme la fiche avant que la fenêtre du chatbot ne la reçoive.
    const surTouche = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return
      e.stopPropagation()
      onFermer()
    }
    document.addEventListener('keydown', surTouche, true)
    return () => {
      document.removeEventListener('keydown', surTouche, true)
      document.body.style.overflow = defilement
      precedent?.focus()
    }
  }, [onFermer])

  const faits = [
    s.type === 'logement' ? t.capacite(s.capacite) : null,
    s.chambres !== null && s.type === 'logement' ? t.chambres(s.chambres) : null,
    s.dureeMinutes !== null ? t.duree(duree(s.dureeMinutes)) : null,
    s.ageMinimum !== null ? t.ageMinimum(s.ageMinimum) : null,
    s.accessible ? t.accessible : null,
  ].filter((f) => f !== null)

  return createPortal(
    <div
      lang={langue}
      className="no-print fixed inset-0 z-[60] flex items-center justify-center bg-encre/60 p-4"
      onClick={(e) => {
        if (e.target === e.currentTarget) onFermer()
      }}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="fiche-suggestion-titre"
        className="relative flex max-h-[90vh] w-full max-w-[560px] flex-col overflow-y-auto rounded-[24px] bg-white shadow-[0_24px_60px_rgba(22,35,63,0.3)]"
      >
        <button
          ref={fermer}
          type="button"
          onClick={onFermer}
          aria-label={t.fermer}
          className="absolute top-3 right-3 z-10 flex size-11 items-center justify-center rounded-full bg-white/90 hover:bg-sable"
        >
          <IconFermer size={22} />
        </button>
        {s.photos.length > 0 && (
          <div className="flex shrink-0 snap-x snap-mandatory gap-1 overflow-x-auto">
            {s.photos.map((photo, i) => (
              <img
                key={photo}
                src={photo}
                alt={t.photo(s.nom, i + 1)}
                className="h-56 w-full shrink-0 snap-center object-cover sm:h-64"
              />
            ))}
          </div>
        )}
        <div className="flex flex-col gap-4 p-6">
          <div className="flex flex-col gap-1 pr-10">
            <span className="text-[13px] font-extrabold text-rouille">
              {t.types[s.type]}
              {s.categorie && ` · ${s.categorie}`}
            </span>
            <h2
              id="fiche-suggestion-titre"
              className="m-0 font-display text-[26px] leading-tight font-semibold"
            >
              {s.nom}
            </h2>
            {s.destination.ville && (
              <span className="text-sm text-taupe">
                {[s.destination.ville, s.destination.pays].filter(Boolean).join(', ')}
              </span>
            )}
          </div>
          {s.description && <p className="m-0 leading-relaxed">{s.description}</p>}
          {faits.length > 0 && (
            <ul className="m-0 flex list-none flex-col gap-1.5 p-0 text-[15px]">
              {faits.map((f) => (
                <li key={f} className="flex items-start gap-2">
                  <span aria-hidden="true" className="mt-0.5 text-encre">
                    <IconCoche size={14} />
                  </span>
                  {f}
                </li>
              ))}
            </ul>
          )}
          {s.equipements.length > 0 && (
            <div className="flex flex-col gap-1.5">
              <strong className="text-[13px]">{t.equipements}</strong>
              <ul className="m-0 flex list-none flex-wrap gap-1.5 p-0">
                {s.equipements.map((e) => (
                  <li key={e} className="rounded-full bg-sable px-3 py-1 text-[13px] font-semibold">
                    {e}
                  </li>
                ))}
              </ul>
            </div>
          )}
          <p className="m-0 text-xs leading-normal text-taupe-clair">{t.prix}</p>
        </div>
      </div>
    </div>,
    document.body,
  )
}

/** Fin de la conversation : la demande est enregistrée et transmise à un conseiller. */
function Confirmation({ texte }: { texte: string }) {
  const { langue } = useChat()
  return (
    <div
      role="status"
      className="flex max-w-full items-start gap-3 self-stretch rounded-[20px] border-2 border-vert bg-white px-4 py-3.5 shadow-[0_2px_10px_rgba(22,35,63,0.06)]"
    >
      <span
        aria-hidden="true"
        className="flex size-8 shrink-0 items-center justify-center rounded-full bg-vert text-white"
      >
        <IconCoche size={16} />
      </span>
      <span className="flex flex-col gap-0.5">
        <strong className="text-[15px] font-extrabold text-vert-fonce">
          {TEXTES[langue].demandeTransmise}
        </strong>
        <span>{texte}</span>
      </span>
    </div>
  )
}

/** La piste retenue : destination, faits, les trois niveaux et leur total, les options, l'estimation datée. */
function CartePiste({ piste }: { piste: Piste }) {
  const { langue } = useChat()
  const t = TEXTES[langue].piste
  const { data: destinations = [] } = useQuery(destinationsQuery())
  const destination = destinations.find((d) => d.slug === piste.destination.slug)
  const date = (iso: string) =>
    new Intl.DateTimeFormat(LOCALE[langue], { day: 'numeric', month: 'long' }).format(new Date(iso))
  const annee = new Date(piste.dateFin).getFullYear()
  const faits = [
    piste.temperatureMoyenne !== null ? t.temperature(piste.temperatureMoyenne) : null,
    piste.tempsDeTrajetMinutes !== null && piste.villeDepart
      ? t.trajet(formatDuree(piste.tempsDeTrajetMinutes), piste.villeDepart)
      : null,
  ].filter(Boolean)

  return (
    <article
      aria-label={`${t.titre} : ${piste.destination.ville}`}
      className="flex w-full max-w-[560px] flex-col overflow-hidden self-start rounded-[22px] bg-white shadow-[0_8px_24px_rgba(22,35,63,0.10)]"
    >
      <div className="relative h-[120px] shrink-0 bg-sable">
        {destination?.photo && (
          <img
            src={destination.photo}
            alt={destination.alt}
            className="block size-full object-cover"
          />
        )}
        <span className="absolute bottom-3 left-3.5 rounded-full bg-white px-3 py-1 text-[12px] font-extrabold tracking-[0.6px] text-rouille uppercase">
          {t.titre}
        </span>
      </div>
      <div className="flex flex-col gap-3 px-4 pt-3.5 pb-4">
        <div className="flex flex-col gap-0.5">
          <h3 className="m-0 font-display text-[22px] leading-tight font-semibold">
            {piste.destination.ville}
            {piste.destination.pays && (
              <span className="font-sans text-sm font-normal text-taupe">
                {' '}
                · {piste.destination.pays}
              </span>
            )}
          </h3>
          <span className="text-[13px] text-taupe">
            {t.dates(date(piste.dateDebut), `${date(piste.dateFin)} ${annee}`)} ·{' '}
            {t.nuits(piste.nuits)} · {t.voyageurs(piste.voyageurs)}
          </span>
          {faits.length > 0 && <span className="text-[13px] text-taupe">{faits.join(' · ')}</span>}
        </div>

        <ul className="m-0 flex list-none flex-col gap-2 p-0">
          {NIVEAUX.map((niveau) => {
            const n = piste.niveaux[niveau]
            const conseille = niveau === piste.niveauConseille
            return (
              <li
                key={niveau}
                className={cn(
                  'flex items-start justify-between gap-3 rounded-2xl px-3.5 py-2.5',
                  conseille ? 'border-2 border-terre bg-creme' : 'border border-lin',
                )}
              >
                <span className="flex min-w-0 flex-col gap-0.5">
                  <span className="flex flex-wrap items-center gap-2">
                    <strong className="text-sm">{t.niveaux[niveau]}</strong>
                    {conseille && (
                      <span className="rounded-full bg-terre px-2 py-0.5 text-[11px] font-extrabold text-white">
                        {t.conseille}
                      </span>
                    )}
                  </span>
                  <span className="text-[13px] leading-snug text-taupe">
                    {[...n.logements, ...n.activites].join(' · ')}
                  </span>
                </span>
                <span className="flex shrink-0 flex-col items-end">
                  <strong className="font-display text-lg font-semibold">
                    {formatEuros(n.totalEuros)}
                  </strong>
                  {piste.voyageurs > 1 && (
                    <span className="text-[12px] text-taupe">
                      {t.parPersonne(formatEuros(n.parPersonneEuros))}
                    </span>
                  )}
                </span>
              </li>
            )
          })}
        </ul>

        {piste.budgetInsuffisant && piste.arbitrages.length > 0 && (
          <div className="flex flex-col gap-1 text-[13px]">
            <strong>{t.arbitrages}</strong>
            <ul className="m-0 pl-4 text-taupe">
              {piste.arbitrages.map((a) => (
                <li key={a.libelle}>
                  {a.libelle} · {formatEuros(a.totalEuros)}
                </li>
              ))}
            </ul>
          </div>
        )}

        {piste.options.length > 0 && (
          <div className="flex flex-col gap-1.5">
            <strong className="text-[13px]">{t.options}</strong>
            <ul className="m-0 flex list-none flex-wrap gap-1.5 p-0">
              {piste.options.map((o) => (
                <li
                  key={o.id}
                  className="rounded-full bg-sable px-3 py-1 text-[13px] font-semibold text-encre"
                >
                  {o.nom} · +{formatEuros(o.prixEuros)}
                </li>
              ))}
            </ul>
          </div>
        )}

        <p className="m-0 text-xs leading-normal text-taupe-clair">{piste.avertissement}</p>
      </div>
    </article>
  )
}

/**
 * Le récapitulatif détaillé : son lien, le total du niveau détaillé et, tant que le client n'a pas répondu, les
 * options à ajouter d'un clic ou le choix de garder le voyage tel quel.
 */
function CarteRecapitulatif({
  recapitulatif: r,
  actif,
}: {
  recapitulatif: RecapitulatifChat
  actif: boolean
}) {
  const { langue, envoyer, fermer, reflexion } = useChat()
  const t = TEXTES[langue]
  const final = r.options.length === 0
  const choix = actif && !final

  return (
    <article
      aria-label={t.recapitulatif.titre}
      className={cn(
        'flex w-full max-w-[560px] flex-col gap-3 self-start rounded-[22px] bg-white px-4 py-4 shadow-[0_8px_24px_rgba(22,35,63,0.10)]',
        final && 'border-2 border-vert',
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex flex-col gap-0.5">
          <span
            className={cn(
              'flex items-center gap-1.5 text-[12px] font-extrabold tracking-[0.6px] uppercase',
              final ? 'text-vert-fonce' : 'text-rouille',
            )}
          >
            {final && <IconCoche size={14} strokeWidth={3} />}
            {final ? t.recapitulatif.transmis : t.recapitulatif.titre}
          </span>
          <strong className="font-display text-xl font-semibold">
            {t.recapitulatif.sousTitre(r.ville, r.jours)}
          </strong>
          <span className="text-[13px] text-taupe">
            {t.piste.niveaux[r.niveau]}
            {r.voyageurs > 1 &&
              ` · ${t.piste.parPersonne(formatEuros(r.parPersonneCentimes / 100))}`}
          </span>
        </div>
        <strong className="shrink-0 font-display text-2xl font-semibold">
          {formatEuros(r.totalCentimes / 100)}
        </strong>
      </div>
      <p className="m-0 text-[15px] leading-normal">{r.texte}</p>
      {choix && (
        <div className="flex flex-wrap gap-2">
          {r.options.map((o) => (
            <button
              key={o.id}
              type="button"
              disabled={reflexion}
              onClick={() => envoyer(t.recapitulatif.messageAjout(o.nom))}
              className="min-h-10 rounded-full border-[1.5px] border-terre px-3.5 text-left text-[13px] font-bold text-rouille hover:bg-creme disabled:opacity-50"
            >
              {t.recapitulatif.ajouter(o.nom, formatEuros(o.prixCentimes / 100))}
            </button>
          ))}
          <button
            type="button"
            disabled={reflexion}
            onClick={() => envoyer(t.recapitulatif.messageGarder)}
            className="min-h-10 rounded-full border-[1.5px] border-encre px-3.5 text-[13px] font-bold hover:bg-sable disabled:opacity-50"
          >
            {t.recapitulatif.garder}
          </button>
        </div>
      )}
      <Link
        to="/proposition/$id"
        params={{ id: r.jeton }}
        onClick={fermer}
        className="flex min-h-11 items-center justify-center self-start rounded-full bg-encre px-5 text-sm font-extrabold text-creme hover:bg-rouille"
      >
        {t.voirRecapitulatif}
      </Link>
    </article>
  )
}

export function AssistantEcrit() {
  const { langue, etape } = useChat()
  const t = TEXTES[langue]
  return (
    <div
      className={cn('flex items-center gap-2.5 self-start px-4 py-3', BULLE_ASSISTANT)}
      role="status"
    >
      <span className="flex items-center gap-1" aria-hidden="true">
        {[0, 1, 2].map((i) => (
          <span
            key={i}
            className="size-2 animate-typing-dot rounded-full bg-terre"
            style={{ animationDelay: `${i * 0.2}s` }}
          />
        ))}
      </span>
      {etape ? (
        <span className="text-sm text-taupe">{t.etapes[etape]}</span>
      ) : (
        <span className="sr-only">{t.assistantEcrit}</span>
      )}
    </div>
  )
}
