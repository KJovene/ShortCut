import { demandeVide } from '@/features/chat/demande'
import { genererProposition, type Proposition } from './proposition'

/**
 * Propositions gardées dans le navigateur le temps de la démo. Avec l'API, la page « Ma proposition »
 * chargera la proposition par son lien unique.
 */
const CLE = 'propositions'
const memoire = new Map<string, Proposition>()

function lireStockage(): Record<string, Proposition> {
  try {
    return JSON.parse(sessionStorage.getItem(CLE) ?? '{}') as Record<string, Proposition>
  } catch {
    return {}
  }
}

export function enregistrerProposition(proposition: Proposition) {
  memoire.set(proposition.id, proposition)
  try {
    sessionStorage.setItem(
      CLE,
      JSON.stringify({ ...lireStockage(), [proposition.id]: proposition }),
    )
  } catch {
    // Stockage indisponible (navigation privée) : la proposition reste en mémoire.
  }
}

/** Proposition d'exemple, toujours disponible sous /proposition/exemple. */
function exemple(): Proposition {
  const demande = {
    ...demandeVide(),
    type: 'noces' as const,
    mois: 5,
    jours: 8,
    adultes: 2,
    budget: 4000,
    contraintes: ['sans voiture'],
  }
  return {
    ...genererProposition(demande, 'santorin'),
    id: 'exemple',
    creeLe: '2026-10-16T12:00:00Z',
  }
}

export function trouverProposition(id: string): Proposition | undefined {
  if (id === 'exemple') return exemple()
  return memoire.get(id) ?? lireStockage()[id]
}
