import { createFileRoute } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { PageLegale } from '@/components/PageLegale'
import { metaPage, pageQuery } from '@/features/pages/pageDuSite'
import { TEXTES } from '@/features/pages/textes'
import { langueDuSite } from '@/lib/langue'

export const Route = createFileRoute('/confidentialite')({
  loader: ({ context }) => context.queryClient.ensureQueryData(pageQuery('confidentialite')),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, TEXTES[langueDuSite()].legales['confidentialite']),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: () => <PageLegale slug="confidentialite" />,
})
