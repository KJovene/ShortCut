import { createFileRoute } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { PageLegale } from '@/components/PageLegale'
import { metaPage, pageQuery } from '@/features/pages/pageDuSite'

export const Route = createFileRoute('/mentions-legales')({
  loader: ({ context }) => context.queryClient.ensureQueryData(pageQuery('mentions-legales')),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, {
      titre: 'Mentions légales',
      description: "Éditeur du site, hébergement et immatriculation de l'agence.",
    }),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: () => <PageLegale slug="mentions-legales" />,
})
