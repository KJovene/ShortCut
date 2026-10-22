import type { DestinationPublique, DestinationSurCarte } from '@/features/catalogue/catalogue'

/** Projection de la carte (équirectangulaire, de 84° N à 58° S). */
export const position = (d: DestinationSurCarte) => ({
  x: ((d.lon + 180) / 360) * 100,
  y: ((84 - d.lat) / 142) * 100,
})

/** Les deux thèmes de l'accueil : voyages au soleil, ou au frais (aurores, neige, grands espaces). */
export type Theme = 'chaud' | 'froid'

/** En dessous de cette température moyenne au mois conseillé, un voyage est « froid ». */
export const SEUIL_FROID = 15

/** Nombre maximum de voyages mis en avant sur la carte et dans le bandeau, par thème. */
export const MAX_VOYAGES_ACCUEIL = 10

/** Thème d'un voyage, d'après sa température au mois conseillé ; null si elle n'est pas saisie. */
export function themeDe(d: DestinationPublique): Theme | null {
  const temperature = d.temperatures[d.moisIdeal]
  if (temperature === null || temperature === undefined) return null
  return temperature < SEUIL_FROID ? 'froid' : 'chaud'
}

/**
 * Voyages de l'accueil pour un thème : parmi les voyages mis en avant (déjà filtrés par l'API), ceux du thème,
 * 10 au plus. Un voyage sans climat saisi reste visible dans les deux thèmes.
 */
export function voyagesDuTheme(destinations: DestinationPublique[], theme: Theme) {
  return destinations
    .filter((d) => {
      const t = themeDe(d)
      return t === null || t === theme
    })
    .slice(0, MAX_VOYAGES_ACCUEIL)
}
