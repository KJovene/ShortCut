import { createFileRoute } from '@tanstack/react-router'
import { NotFound } from '@/components/NotFound'

/** Toute adresse inconnue : page introuvable, avec son propre titre et non indexée. */
export const Route = createFileRoute('/$')({
  head: () => ({
    meta: [
      { title: 'Page introuvable' },
      {
        name: 'description',
        content: "Cette page n'existe pas ou a été déplacée. Retrouvez nos destinations.",
      },
      { name: 'robots', content: 'noindex' },
    ],
  }),
  component: NotFound,
})
