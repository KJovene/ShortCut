import { TEXTES } from '@/components/textes'
import { TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { TEXTES as TEXTES_PAGES } from '@/features/pages/textes'
import { useLangue } from '@/lib/langue'

/** Gabarit des pages légales, dont le contenu est dans les textes du site (features/pages/textes.ts). */
export function PageLegale({ slug }: { slug: keyof (typeof TEXTES_PAGES)['fr']['legales'] }) {
  const langue = useLangue()
  const page = TEXTES_PAGES[langue].legales[slug]

  return (
    <article className="mx-auto max-w-[820px] px-4 py-10 sm:px-8 sm:py-16">
      <h1 className="m-0 mb-6 font-display text-[36px] leading-tight font-semibold sm:text-[48px]">
        <TitreMisEnValeur texte={page.titre} />
      </h1>
      {page.aValider && (
        <p role="note" className="m-0 mb-8 rounded-2xl bg-sable px-4 py-3 font-semibold text-encre">
          {TEXTES[langue].modeleAValider}
        </p>
      )}
      <div className="prose-agence">
        {page.sections.map((section) => (
          <section key={section.titre}>
            <h2>{section.titre}</h2>
            <p>{section.texte}</p>
          </section>
        ))}
      </div>
    </article>
  )
}
