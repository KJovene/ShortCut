export interface SectionLegale {
  titre: string
  texte: string
}

/** Gabarit des pages légales. Modèles marqués « à valider » tant que l'agence n'a pas fourni ses textes. */
export function PageLegale({ titre, sections }: { titre: string; sections: SectionLegale[] }) {
  return (
    <article className="mx-auto max-w-[820px] px-4 py-10 sm:px-8 sm:py-16">
      <h1 className="m-0 mb-6 font-display text-[36px] leading-tight font-semibold sm:text-[48px]">
        {titre}
      </h1>
      <p className="m-0 mb-8 rounded-2xl bg-[#FFF4D6] px-4 py-3 font-semibold text-[#5C4300]">
        Modèle à valider par l'agence : ce texte doit être relu et complété avant la mise en ligne.
      </p>
      <div className="prose-agence">
        {sections.map((s) => (
          <section key={s.titre}>
            <h2>{s.titre}</h2>
            <p>{s.texte}</p>
          </section>
        ))}
      </div>
    </article>
  )
}
