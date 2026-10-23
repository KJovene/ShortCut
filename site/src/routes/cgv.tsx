import { createFileRoute } from '@tanstack/react-router'
import { PageLegale } from '@/components/PageLegale'
import { metaPage } from '@/features/pages/meta'
import { TEXTES } from '@/features/pages/textes'
import { langueDuSite } from '@/lib/langue'

export const Route = createFileRoute('/cgv')({
  head: () => ({
    meta: metaPage(TEXTES[langueDuSite()].legales['cgv']),
  }),
  component: () => <PageLegale slug="cgv" />,
})
