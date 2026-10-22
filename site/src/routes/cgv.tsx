import { createFileRoute } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { PageLegale } from '@/components/PageLegale'
import { metaPage, pageQuery } from '@/features/pages/pageDuSite'
import { TEXTES } from '@/features/pages/textes'
import { langueDuSite } from '@/lib/langue'

export const Route = createFileRoute('/cgv')({
  loader: ({ context }) => context.queryClient.ensureQueryData(pageQuery('cgv')),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, TEXTES[langueDuSite()].legales['cgv']),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: () => <PageLegale slug="cgv" />,
})
