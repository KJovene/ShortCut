import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { useChat } from '@/features/chat/chatContext'
import {
  estJetonProposition,
  propositionQuery,
  titreProposition,
} from '@/features/proposition/enLigne'
import { PropositionEnLigne } from '@/features/proposition/PropositionEnLigne'

/**
 * « Ma proposition » : par son lien unique (jeton), la proposition envoyée par le conseiller, lue dans l'API.
 * Une adresse qui n'est pas un jeton ne mène à aucune proposition.
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
          ? `${loaderData.langue === 'en' ? 'My proposal' : 'Ma proposition'} · ${titreProposition(loaderData)}`
          : 'Ma proposition de voyage',
      },
      {
        name: 'description',
        content:
          loaderData?.langue === 'en'
            ? 'Your tailor-made travel proposal, to be confirmed by your advisor.'
            : 'Votre proposition de voyage sur mesure, à confirmer par votre conseiller.',
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
  return estJetonProposition(id) ? <PropositionDuLien jeton={id} /> : <PropositionIntrouvable />
}

function PropositionDuLien({ jeton }: { jeton: string }) {
  const { data: proposition } = useSuspenseQuery(propositionQuery(jeton))
  return proposition ? (
    <PropositionEnLigne jeton={jeton} proposition={proposition} />
  ) : (
    <PropositionIntrouvable />
  )
}

function PropositionIntrouvable() {
  const { ouvrir } = useChat()
  return (
    <section className="mx-auto flex max-w-[720px] flex-col items-start gap-4 px-4 py-16 sm:px-8 sm:py-24">
      <span className="text-[13px] font-extrabold text-rouille">Ma proposition</span>
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
      </div>
    </section>
  )
}
