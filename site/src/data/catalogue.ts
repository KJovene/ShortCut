/**
 * Constantes et aides partagées par tout le site : types de voyage, mois, formats. Le catalogue lui-même
 * (destinations, logements, activités) est lu dans l'API, voir src/features/catalogue/catalogue.ts.
 */

export const TYPES_DE_VOYAGE = [
  { valeur: 'sejour', label: 'Séjour', pluriel: 'Séjours', fraisDossier: 100 },
  { valeur: 'circuit', label: 'Circuit', pluriel: 'Circuits', fraisDossier: 150 },
  { valeur: 'groupe', label: 'Groupe', pluriel: 'Groupes', fraisDossier: 200 },
  { valeur: 'noces', label: 'Voyage de noces', pluriel: 'Noces', fraisDossier: 150 },
  { valeur: 'entreprise', label: 'Entreprise', pluriel: 'Entreprise', fraisDossier: 200 },
] as const

export type TypeDeVoyage = (typeof TYPES_DE_VOYAGE)[number]['valeur']

export const MOIS = [
  'janvier',
  'février',
  'mars',
  'avril',
  'mai',
  'juin',
  'juillet',
  'août',
  'septembre',
  'octobre',
  'novembre',
  'décembre',
] as const

export function labelType(type: TypeDeVoyage) {
  return TYPES_DE_VOYAGE.find((t) => t.valeur === type)!.label
}

export function formatEuros(montant: number) {
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant)} €`
}

export function formatDuree(minutes: number) {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return m ? `${h} h ${String(m).padStart(2, '0')}` : `${h} h`
}
