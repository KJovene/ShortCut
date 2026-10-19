import { queryOptions } from '@tanstack/react-query'
import { z } from 'zod'
import { ApiError } from '@/lib/api'

/** Contenu d'une page du site, modifiable dans le backoffice (« Contenus du site »). */
export const PageDuSite = z.object({
  slug: z.string(),
  titre: z.string(),
  /** HTML simple saisi dans le backoffice : paragraphes, intertitres h2 et listes. */
  contenu: z.string(),
  photos: z.array(z.string()),
  seoTitre: z.string().nullable(),
  seoDescription: z.string().nullable(),
})
export type PageDuSite = z.infer<typeof PageDuSite>

export async function chargerPage(slug: string): Promise<PageDuSite> {
  const response = await fetch(`/api/pages/${slug}`, { headers: { Accept: 'application/json' } })
  if (!response.ok) {
    throw new ApiError(response.status, 'Impossible de charger cette page.')
  }
  return PageDuSite.parse(await response.json())
}

export const pageQuery = (slug: string) =>
  queryOptions({
    queryKey: ['pages', slug],
    queryFn: () => chargerPage(slug),
    staleTime: 5 * 60_000,
  })

/** Balises title et description d'une page, avec un texte de secours si l'API n'a pas répondu. */
export function metaPage(
  page: PageDuSite | null | undefined,
  secours: { titre: string; description: string },
) {
  return [
    { title: page?.seoTitre || secours.titre },
    { name: 'description', content: page?.seoDescription || secours.description },
  ]
}

export interface SectionDeContenu {
  titre: string
  paragraphes: string[]
  elements: string[]
}

export interface ContenuDecoupe {
  /** Paragraphes placés avant le premier intertitre. */
  intro: string[]
  sections: SectionDeContenu[]
}

/**
 * Découpe le HTML d'une page en introduction et sections (un h2 ouvre une section), pour placer chaque
 * morceau dans la mise en page des maquettes. Seul le texte est gardé : rien n'est injecté tel quel.
 */
export function decouperContenu(html: string): ContenuDecoupe {
  const document = new DOMParser().parseFromString(html, 'text/html')
  const resultat: ContenuDecoupe = { intro: [], sections: [] }
  const texte = (el: Element) => el.textContent?.replace(/\s+/g, ' ').trim() ?? ''

  for (const el of Array.from(document.body.children)) {
    const courante = resultat.sections.at(-1)
    if (el.tagName === 'H2') {
      resultat.sections.push({ titre: texte(el), paragraphes: [], elements: [] })
    } else if (el.tagName === 'UL' || el.tagName === 'OL') {
      const elements = Array.from(el.querySelectorAll('li')).map(texte).filter(Boolean)
      if (courante) courante.elements.push(...elements)
      else resultat.intro.push(...elements)
    } else if (texte(el)) {
      if (courante) courante.paragraphes.push(texte(el))
      else resultat.intro.push(texte(el))
    }
  }
  return resultat
}

/** Section dont l'intertitre correspond, sans tenir compte des majuscules ni des accents. */
export function trouverSection(contenu: ContenuDecoupe, ...titres: string[]) {
  const cles = titres.map(normaliser)
  return contenu.sections.find((s) => cles.includes(normaliser(s.titre)))
}

export function normaliser(texte: string) {
  return texte
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase()
    .trim()
}
