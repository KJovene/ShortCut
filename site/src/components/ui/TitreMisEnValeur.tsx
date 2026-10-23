import type { ReactNode } from 'react'

/**
 * Le saut du O de la charte DÉTOUR : le premier « o » (ou « ô », comme dans « côté ») du texte monte d'une
 * demi-hauteur et passe en jaune. Sans « o », le texte reste tel quel. À réserver aux titres et accroches de 28 px et plus, un seul par composition.
 */
export function SautDuO({ texte }: { texte: string }): ReactNode {
  const i = texte.search(/[oô]/)
  if (i === -1) return texte
  return (
    <>
      {texte.slice(0, i)}
      <span className="saut-o">{texte[i]}</span>
      {texte.slice(i + 1)}
    </>
  )
}

/**
 * Titre saisi dans le backoffice : les mots entre astérisques portent l'idée du titre (« Où irez-vous chercher le
 * *soleil* ? »). Le premier o du premier passage marqué fait le saut du O ; le passage reste une emphase
 * (em) pour les lecteurs d'écran, sans italique.
 */
export function TitreMisEnValeur({ texte }: { texte: string }) {
  let sautPlace = false
  return texte.split(/\*([^*]+)\*/).map((morceau, i) => {
    if (i % 2 === 0) return morceau
    const avecSaut = !sautPlace && /[oô]/.test(morceau)
    if (avecSaut) sautPlace = true
    return (
      <em key={i} className="not-italic">
        {avecSaut ? <SautDuO texte={morceau} /> : morceau}
      </em>
    )
  })
}
