import { useRouter } from '@tanstack/react-router'
import { TEXTES } from '@/components/textes'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { useLangue } from '@/lib/langue'

/** Pendant le chargement du contenu d'une page (pendingComponent des routes). */
export function PageEnChargement() {
  const t = TEXTES[useLangue()]
  return (
    <section
      aria-busy="true"
      className="mx-auto flex max-w-[1376px] flex-col gap-5 px-4 pt-8 pb-16 sm:px-8 sm:pt-14"
    >
      <p role="status" className="sr-only">
        {t.chargement}
      </p>
      <div aria-hidden="true" className="h-4 w-40 animate-pulse rounded-full bg-lin" />
      <div aria-hidden="true" className="h-12 w-full max-w-xl animate-pulse rounded-2xl bg-lin" />
      <div
        aria-hidden="true"
        className="h-5 w-full max-w-2xl animate-pulse rounded-full bg-sable"
      />
      <div aria-hidden="true" className="h-5 w-full max-w-lg animate-pulse rounded-full bg-sable" />
    </section>
  )
}

/** Contenu indisponible (errorComponent des routes) : réessayer, ou passer par l'assistant qui reste ouvert. */
export function PageIndisponible() {
  const t = TEXTES[useLangue()]
  const router = useRouter()
  const ouvrirAssistant = useOuvrirAssistant()

  return (
    <section
      role="alert"
      className="mx-auto flex max-w-[720px] flex-col items-start gap-4 px-4 py-16 sm:px-8 sm:py-24"
    >
      <h1 className="m-0 font-display text-[34px] leading-tight font-semibold sm:text-[44px]">
        {t.indisponibleTitre}
      </h1>
      <p className="m-0 text-lg text-taupe">{t.indisponibleTexte}</p>
      <div className="flex flex-wrap gap-2.5">
        <button
          type="button"
          onClick={() => void router.invalidate()}
          className="flex min-h-12 items-center rounded-full bg-terre px-6 font-extrabold text-white hover:bg-rouille"
        >
          {t.reessayer}
        </button>
        <button
          type="button"
          onClick={() => ouvrirAssistant()}
          className="flex min-h-12 items-center rounded-full border-[1.5px] border-encre px-6 font-bold hover:bg-sable"
        >
          {t.decrireALAssistant}
        </button>
      </div>
    </section>
  )
}
