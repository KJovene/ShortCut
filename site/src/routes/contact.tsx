import { createFileRoute } from '@tanstack/react-router'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { IconBulle, IconPersonne } from '@/components/ui/icons'
import { useChat } from '@/features/chat/chatContext'

export const Route = createFileRoute('/contact')({
  head: () => ({
    meta: [
      { title: "Contacter l'agence" },
      {
        name: 'description',
        content: 'Posez vos questions à notre assistant ou demandez à parler à un conseiller.',
      },
    ],
  }),
  component: ContactPage,
})

function ContactPage() {
  const { ouvrir, envoyer } = useChat()

  return (
    <>
      <EnTetePage
        surtitre="Contact"
        titre={
          <>
            Une question ? <em className="font-normal text-terre">Parlons-en</em>
          </>
        }
        photo={{ src: '/uploads/pages/contact.jpg', alt: "L'accueil de l'agence" }}
      >
        <p className="m-0">
          Le plus rapide : ouvrez l'assistant et décrivez votre projet. Vous pouvez à tout moment
          lui demander de parler à un conseiller.
        </p>
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
        <article className="flex flex-col items-start gap-4 rounded-[28px] bg-white p-6 shadow-[0_8px_24px_rgba(43,29,20,0.08)] sm:p-8">
          <span
            aria-hidden="true"
            className="flex size-12 items-center justify-center rounded-full bg-encre text-creme"
          >
            <IconPersonne size={24} />
          </span>
          <h2 className="m-0 font-display text-[28px] font-semibold">Un conseiller</h2>
          <dl className="m-0 flex flex-col gap-3 text-lg">
            <div>
              <dt className="text-sm font-bold text-taupe-clair">Horaires des conseillers</dt>
              <dd className="m-0">
                Du lundi au vendredi de 9 h à 18 h 30, le samedi de 10 h à 17 h.
              </dd>
            </div>
            <div>
              <dt className="text-sm font-bold text-taupe-clair">Adresse</dt>
              <dd className="m-0">
                12 rue des Voyageurs, 75000 Paris (adresse fictive de démonstration).
              </dd>
            </div>
          </dl>
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
