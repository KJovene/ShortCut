import { useSuspenseQuery } from '@tanstack/react-query'
import { TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { decouperContenu, estMentionAValider, pageQuery } from '@/features/pages/pageDuSite'

/** Gabarit des pages légales, lues depuis les contenus du site (module Contenus du backoffice). */
export function PageLegale({ slug }: { slug: string }) {
  const { data: page } = useSuspenseQuery(pageQuery(slug))
  const contenu = decouperContenu(page.contenu)
  const aValider = contenu.intro.some(estMentionAValider)
  const intro = contenu.intro.filter((p) => !estMentionAValider(p))

  return (
    <article className="mx-auto max-w-[820px] px-4 py-10 sm:px-8 sm:py-16">
      <h1 className="m-0 mb-6 font-display text-[36px] leading-tight font-semibold sm:text-[48px]">
        <TitreMisEnValeur texte={page.titre} />
      </h1>
      {aValider && (
        <p
          role="note"
          className="m-0 mb-8 rounded-2xl bg-[#FFF4D6] px-4 py-3 font-semibold text-[#5C4300]"
        >
          Modèle à valider par l'agence : ce texte doit être relu et complété avant la mise en
          ligne.
        </p>
      )}
      <div className="prose-agence">
        {intro.map((paragraphe) => (
          <p key={paragraphe}>{paragraphe}</p>
        ))}
        {contenu.sections.map((section) => (
          <section key={section.titre}>
            <h2>{section.titre}</h2>
            {section.paragraphes.map((paragraphe) => (
              <p key={paragraphe}>{paragraphe}</p>
            ))}
            {section.elements.length > 0 && (
              <ul>
                {section.elements.map((element) => (
                  <li key={element}>{element}</li>
                ))}
              </ul>
            )}
          </section>
        ))}
      </div>
    </article>
  )
}
