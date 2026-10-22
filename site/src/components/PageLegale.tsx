import { useSuspenseQuery } from '@tanstack/react-query'
import { TEXTES } from '@/components/textes'
import { TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { decouperContenu, estMentionAValider, pageQuery } from '@/features/pages/pageDuSite'
import { useLangue } from '@/lib/langue'

/** Gabarit des pages légales, lues depuis les contenus du site (module Contenus du backoffice). */
export function PageLegale({ slug }: { slug: string }) {
  const langue = useLangue()
  const { data: page } = useSuspenseQuery(pageQuery(slug, langue))
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
          className="m-0 mb-8 rounded-2xl bg-[#EAE2D2] px-4 py-3 font-semibold text-[#16233F]"
        >
          {TEXTES[langue].modeleAValider}
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
