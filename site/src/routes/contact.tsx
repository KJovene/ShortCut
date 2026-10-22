import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { IconBulle, IconPersonne } from '@/components/ui/icons'
import { useChat } from '@/features/chat/chatContext'
import { decouperContenu, metaPage, pageQuery } from '@/features/pages/pageDuSite'

export const Route = createFileRoute('/contact')({
  loader: ({ context }) => context.queryClient.ensureQueryData(pageQuery('contact')),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, {
      titre: "Contacter l'agence",
      description: 'Posez vos questions à notre assistant ou demandez à parler à un conseiller.',
    }),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: ContactPage,
})

function ContactPage() {
  const { ouvrir, envoyer } = useChat()
  // Introduction, horaires et adresse : contenus du site, modifiables dans le backoffice.
  const { data: page } = useSuspenseQuery(pageQuery('contact'))
  const contenu = decouperContenu(page.contenu)

  return (
    <>
      <EnTetePage
        surtitre="Contact"
        titre={<TitreMisEnValeur texte={page.titre} />}
        photo={page.photos[0] ? { src: page.photos[0], alt: "L'accueil de l'agence" } : undefined}
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
          <h2 className="m-0 font-display text-[28px] font-semibold">L'assistant, 24 h sur 24</h2>
          <p className="m-0 text-lg text-taupe">
            Une proposition complète et chiffrée en moins de 2 minutes.
          </p>
          <button
            type="button"
            onClick={() => ouvrir()}
            className="flex min-h-12 items-center rounded-full bg-terre px-6 font-extrabold text-white hover:bg-rouille"
          >
            Ouvrir l'assistant
          </button>
        </article>
        <article className="flex flex-col items-start gap-4 rounded-[28px] bg-white p-6 shadow-[0_8px_24px_rgba(22,35,63,0.08)] sm:p-8">
          <span
            aria-hidden="true"
            className="flex size-12 items-center justify-center rounded-full bg-encre text-creme"
          >
            <IconPersonne size={24} />
          </span>
          <h2 className="m-0 font-display text-[28px] font-semibold">Un conseiller</h2>
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
              envoyer('Je voudrais parler à un conseiller')
              ouvrir()
            }}
            className="flex min-h-12 items-center rounded-full border-[1.5px] border-encre px-6 font-bold hover:bg-sable"
          >
            Être rappelé par un conseiller
          </button>
        </article>
      </section>
    </>
  )
}
