import { createFileRoute } from '@tanstack/react-router'
import { PageLegale } from '@/components/PageLegale'
import { metaPage } from '@/features/pages/meta'
import { TEXTES } from '@/features/pages/textes'
import { langueDuSite } from '@/lib/langue'

export const Route = createFileRoute('/confidentialite')({
  head: () => ({
    meta: metaPage(TEXTES[langueDuSite()].legales['confidentialite']),
  }),
  component: () => <PageLegale slug="confidentialite" />,
})
