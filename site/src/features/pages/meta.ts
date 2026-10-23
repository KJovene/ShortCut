/** Balises title et description d'une page, tirées de ses textes (features/pages/textes.ts). */
export const metaPage = ({ titre, description }: { titre: string; description: string }) => [
  { title: titre },
  { name: 'description', content: description },
]
