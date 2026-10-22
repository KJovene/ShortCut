/**
 * Titre saisi dans le backoffice : les mots entre astérisques sont mis en valeur (italique de couleur),
 * comme dans les maquettes. « Où irez-vous *chercher le soleil* ? »
 */
export function TitreMisEnValeur({ texte }: { texte: string }) {
  return texte.split(/\*([^*]+)\*/).map((morceau, i) =>
    i % 2 === 1 ? (
      <em key={i} className="font-normal text-terre">
        {morceau}
      </em>
    ) : (
      morceau
    ),
  )
}
