import { createFileRoute } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { PageLegale } from '@/components/PageLegale'
import { metaPage, pageQuery } from '@/features/pages/pageDuSite'

export const Route = createFileRoute('/confidentialite')({
  loader: ({ context }) => context.queryClient.ensureQueryData(pageQuery('confidentialite')),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, {
      titre: 'Confidentialité et cookies',
      description:
        "Données collectées par l'assistant et le site, finalités, durée de conservation et vos droits.",
    }),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: () => <PageLegale slug="confidentialite" />,
})
