import type { TypeDeVoyage } from '@/data/catalogue'
import type { DestinationPublique, DestinationSurCarte } from '@/features/catalogue/catalogue'

/** Projection de la carte (équirectangulaire, de 84° N à 58° S). */
export const position = (d: DestinationSurCarte) => ({
  x: ((d.lon + 180) / 360) * 100,
  y: ((84 - d.lat) / 142) * 100,
})

export const estVisible = (d: DestinationPublique, type: TypeDeVoyage | 'tous') =>
  type === 'tous' || d.types.includes(type)
