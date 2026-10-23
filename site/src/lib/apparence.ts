import { useSyncExternalStore } from 'react'

/**
 * Apparence du site : claire ou sombre. Tant que le visiteur n'a rien choisi dans l'en-tête, le site suit le
 * réglage du système (et ses changements) ; son choix est ensuite gardé dans le navigateur. Le script de
 * index.html pose la même valeur avant le premier rendu, pour que la page ne clignote pas en clair.
 */
export type Apparence = 'clair' | 'sombre'

const CLE = 'apparence'
const abonnes = new Set<() => void>()
const systeme =
  typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-color-scheme: dark)') : null

function lireChoix(): Apparence | null {
  try {
    const choix = localStorage.getItem(CLE)
    return choix === 'clair' || choix === 'sombre' ? choix : null
  } catch {
    return null
  }
}

const duSysteme = (): Apparence => (systeme?.matches ? 'sombre' : 'clair')

let choisie = lireChoix()
let courante: Apparence = choisie ?? duSysteme()
document.documentElement.dataset.apparence = courante

function appliquer(apparence: Apparence) {
  if (apparence === courante) return
  courante = apparence
  document.documentElement.dataset.apparence = apparence
  abonnes.forEach((abonne) => abonne())
}

// Sans choix du visiteur, le site suit le système quand il passe en clair ou en sombre.
systeme?.addEventListener('change', () => {
  if (!choisie) appliquer(duSysteme())
})

export function changerApparence(apparence: Apparence) {
  choisie = apparence
  try {
    localStorage.setItem(CLE, apparence)
  } catch {
    // Sans stockage, le choix dure le temps de la visite.
  }
  appliquer(apparence)
}

function surChangementDApparence(abonne: () => void) {
  abonnes.add(abonne)
  return () => {
    abonnes.delete(abonne)
  }
}

/** Apparence du site, mise à jour quand le visiteur ou le système la change. */
export const useApparence = () => useSyncExternalStore(surChangementDApparence, () => courante)
