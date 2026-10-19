import { createFileRoute } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { PageLegale } from '@/components/PageLegale'
import { metaPage, pageQuery } from '@/features/pages/pageDuSite'

export const Route = createFileRoute('/cgv')({
  loader: ({ context }) => context.queryClient.ensureQueryData(pageQuery('cgv')),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, {
      titre: 'Conditions générales de vente',
      description:
        "Conditions de réservation, de paiement, de modification et d'annulation des voyages.",
    }),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: () => <PageLegale slug="cgv" />,
})
