import { createFileRoute } from '@tanstack/react-router'
import { NotFound } from '@/components/NotFound'
import { TEXTES } from '@/features/pages/textes'
import { langueDuSite } from '@/lib/langue'

/** Toute adresse inconnue : page introuvable, avec son propre titre et non indexée. */
export const Route = createFileRoute('/$')({
  head: () => {
    const t = TEXTES[langueDuSite()].introuvable
    return {
      meta: [
        { title: t.titre },
        { name: 'description', content: t.description },
        { name: 'robots', content: 'noindex' },
      ],
    }
  },
  component: NotFound,
})
