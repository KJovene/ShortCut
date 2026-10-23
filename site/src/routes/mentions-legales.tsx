import { createFileRoute } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { PageLegale } from '@/components/PageLegale'
import { metaPage, pageQuery } from '@/features/pages/pageDuSite'
import { TEXTES } from '@/features/pages/textes'
import { langueDuSite } from '@/lib/langue'

export const Route = createFileRoute('/mentions-legales')({
  loader: ({ context }) => context.queryClient.ensureQueryData(pageQuery('mentions-legales')),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, TEXTES[langueDuSite()].legales['mentions-legales']),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: () => <PageLegale slug="mentions-legales" />,
})
