import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { IconBulle, IconPersonne } from '@/components/ui/icons'
import { useChat } from '@/features/chat/chatContext'
import { TEXTES as TEXTES_CHAT } from '@/features/chat/textes'
import { decouperContenu, metaPage, pageQuery } from '@/features/pages/pageDuSite'
import { TEXTES } from '@/features/pages/textes'
import { langueDuSite, useLangue } from '@/lib/langue'

export const Route = createFileRoute('/contact')({
  loader: ({ context }) => context.queryClient.ensureQueryData(pageQuery('contact')),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, TEXTES[langueDuSite()].contact.meta),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: ContactPage,
})

function ContactPage() {
  const { ouvrir, envoyer, langue: langueDuChat } = useChat()
  const langue = useLangue()
  const t = TEXTES[langue].contact
  // Introduction, horaires et adresse : contenus du site, modifiables dans le backoffice.
  const { data: page } = useSuspenseQuery(pageQuery('contact'))
  const contenu = decouperContenu(page.contenu)

  return (
    <>
      <EnTetePage
        surtitre={t.surtitre}
        titre={<TitreMisEnValeur texte={page.titre} />}
        photo={page.photos[0] ? { src: page.photos[0], alt: t.photo } : undefined}
      >
        {contenu.intro.map((paragraphe) => (
          <p key={paragraphe} className="m-0">
            {paragraphe}
          </p>
        ))}
      </EnTetePage>

      <section className="mx-auto grid max-w-[1376px] grid-cols-1 gap-6 px-4 pb-16 sm:px-8 sm:pb-24 md:grid-cols-2">
        <article className="flex flex-col items-start gap-4 rounded-[28px] bg-sable p-6 sm:p-8">
          <span
            aria-hidden="true"
            className="flex size-12 items-center justify-center rounded-full bg-terre text-white"
          >
            <IconBulle size={24} />
          </span>
          <h2 className="m-0 font-display text-[28px] font-semibold">{t.assistant}</h2>
          <p className="m-0 text-lg text-taupe">{t.assistantTexte}</p>
          <button
            type="button"
            onClick={() => ouvrir()}
            className="flex min-h-12 items-center rounded-full bg-terre px-6 font-extrabold text-white hover:bg-rouille"
          >
            {t.ouvrirAssistant}
          </button>
        </article>
        <article className="flex flex-col items-start gap-4 rounded-[28px] bg-white p-6 shadow-[0_8px_24px_rgba(22,35,63,0.08)] sm:p-8">
          <span
            aria-hidden="true"
            className="flex size-12 items-center justify-center rounded-full bg-encre text-creme"
          >
            <IconPersonne size={24} />
          </span>
          <h2 className="m-0 font-display text-[28px] font-semibold">{t.unConseiller}</h2>
          {contenu.sections.length > 0 && (
            <dl className="m-0 flex flex-col gap-3 text-lg">
              {contenu.sections.map((section) => (
                <div key={section.titre}>
                  <dt className="text-sm font-bold text-taupe-clair">{section.titre}</dt>
                  {[...section.paragraphes, ...section.elements].map((ligne) => (
                    <dd key={ligne} className="m-0">
                      {ligne}
                    </dd>
                  ))}
                </div>
              ))}
            </dl>
          )}
          <button
            type="button"
            onClick={() => {
              envoyer(TEXTES_CHAT[langueDuChat].messageConseiller)
              ouvrir()
            }}
            className="flex min-h-12 items-center rounded-full border-[1.5px] border-encre px-6 font-bold hover:bg-sable"
          >
            {t.etreRappele}
          </button>
        </article>
      </section>
    </>
  )
}
