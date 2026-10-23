import { queryOptions } from '@tanstack/react-query'
import { z } from 'zod'
import { requete } from '@/lib/api'
import { type Langue, langueDuSite } from '@/lib/langue'

/** Contenu d'une page du site, modifiable dans le backoffice (« Contenus du site »). */
export const PageDuSite = z.object({
  slug: z.string(),
  titre: z.string(),
  /** Accueil : titre du thème « voyages froids » ; absent pour les autres pages. */
  titreFroid: z.string().nullish(),
  /** HTML simple saisi dans le backoffice : paragraphes, intertitres h2 et listes. */
  contenu: z.string(),
  photos: z.array(z.string()),
  seoTitre: z.string().nullable(),
  seoDescription: z.string().nullable(),
})
export type PageDuSite = z.infer<typeof PageDuSite>

/** En anglais, l'API traduit les textes saisis en français dans le backoffice. */
export async function chargerPage(slug: string, langue: Langue = 'fr'): Promise<PageDuSite> {
  return requete(`/pages/${slug}${langue === 'en' ? '?langue=en' : ''}`, PageDuSite, {
    messageErreur: 'Impossible de charger cette page.',
  })
}

export const pageQuery = (slug: string, langue: Langue = langueDuSite()) =>
  queryOptions({
    queryKey: ['pages', slug, langue],
    queryFn: () => chargerPage(slug, langue),
    staleTime: 5 * 60_000,
  })

/** Page publiée et sa position dans le menu (« Ordre dans le menu » du backoffice) ; absente : hors du menu. */
const PageDuMenu = z.object({ slug: z.string(), ordreMenu: z.number().nullish() })
export type PageDuMenu = z.infer<typeof PageDuMenu>

export async function chargerMenu(): Promise<PageDuMenu[]> {
  return requete('/pages', z.array(PageDuMenu), { messageErreur: 'Impossible de charger le menu.' })
}

export const menuQuery = queryOptions({
  queryKey: ['pages'],
  queryFn: chargerMenu,
  staleTime: 5 * 60_000,
})

/**
 * Liens du menu rangés selon le backoffice : une page sans position ou non publiée en sort. Sans réponse
 * de l'API (chargement, panne), les liens gardent leur ordre par défaut.
 */
export function ordonnerMenu<T extends { slug: string }>(
  liens: readonly T[],
  pages: PageDuMenu[] | undefined,
): T[] {
  if (!pages) return [...liens]
  const ordre = new Map(pages.map((p) => [p.slug, p.ordreMenu]))
  return liens
    .filter((lien) => ordre.get(lien.slug) != null)
    .sort((a, b) => ordre.get(a.slug)! - ordre.get(b.slug)!)
}

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
  const texte = (el: Element) => nettoyer(el.textContent ?? '')

  for (const el of Array.from(document.body.children)) {
    const courante = resultat.sections.at(-1)
    if (el.tagName === 'H1' || el.tagName === 'H2') {
      resultat.sections.push({ titre: texte(el), paragraphes: [], elements: [] })
    } else if (el.tagName === 'UL' || el.tagName === 'OL') {
      const elements = Array.from(el.querySelectorAll('li')).map(texte).filter(Boolean)
      if (courante) courante.elements.push(...elements)
      else resultat.intro.push(...elements)
    } else {
      const paragraphes = lignes(el).map(nettoyer).filter(Boolean)
      if (courante) courante.paragraphes.push(...paragraphes)
      else resultat.intro.push(...paragraphes)
    }
  }
  return resultat
}

function nettoyer(texte: string) {
  return texte.replace(/\s+/g, ' ').trim()
}

/** Texte d'un bloc coupé à chaque retour à la ligne : l'éditeur du backoffice les écrit en <br>. */
function lignes(el: Element) {
  const resultat = ['']
  const parcourir = (noeud: Node) => {
    if (noeud.nodeName === 'BR') resultat.push('')
    else if (noeud.nodeType === Node.TEXT_NODE)
      resultat[resultat.length - 1] += noeud.textContent ?? ''
    else noeud.childNodes.forEach(parcourir)
  }
  el.childNodes.forEach(parcourir)
  return resultat
}

/**
 * Photo d'une page choisie par son nom de fichier : « sejour.jpg » (jeu de démo) ou « sejour-1a2b3c.jpg »
 * (envoyée depuis le backoffice, qui ajoute une empreinte au nom).
 */
export function photoNommee(photos: string[], nom: string) {
  return photos.find((url) => new RegExp(`/${nom}[-.][^/]*$`).test(url))
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

/**
 * Un modèle fourni par ShortCut s'ouvre sur la mention « Modèle à valider par l'agence » (DataFixtures/Donnees/Pages.php) :
 * elle disparaît du contenu quand l'agence saisit ses propres textes dans le backoffice.
 */
export function estMentionAValider(paragraphe: string) {
  const texte = normaliser(paragraphe)
  return texte.includes('a valider') || texte.includes('to be approved')
}
