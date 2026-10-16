import { createFileRoute } from '@tanstack/react-router'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { IconCoche } from '@/components/ui/icons'
import { DESTINATIONS } from '@/data/catalogue'

export const Route = createFileRoute('/agence')({
  head: () => ({
    meta: [
      { title: "L'agence · Nos conseillers et nos engagements" },
      {
        name: 'description',
        content:
          'Une équipe de conseillers spécialistes de chaque destination, qui confirment chaque proposition avant votre départ.',
      },
    ],
  }),
  component: AgencePage,
})

// Conseillers des données de démo (UtilisateurFixtures) et destinations qu'ils suivent.
const CONSEILLERS = [
  {
    nom: 'Léa Dubois',
    role: 'Conseillère voyage',
    destinations: ['lisbonne', 'marrakech', 'santorin', 'rome'],
  },
  {
    nom: 'Hugo Bernard',
    role: 'Conseiller voyage',
    destinations: ['tenerife', 'islande', 'bali', 'costa-rica'],
  },
]

const ENGAGEMENTS = [
  'Une première proposition complète en quelques minutes, à toute heure.',
  'Un conseiller référent qui confirme les prix et les disponibilités sous deux jours ouvrés.',
  'Des prix transparents : le détail de chaque poste et les hypothèses de budget.',
]

const initiales = (nom: string) =>
  nom
    .split(' ')
    .map((n) => n[0])
    .join('')

function AgencePage() {
  return (
    <>
      <EnTetePage
        surtitre="L'agence"
        titre={
          <>
            Des conseillers qui <em className="font-normal text-terre">connaissent le terrain</em>
          </>
        }
        photo={{ src: '/uploads/pages/agence.jpg', alt: "L'équipe de l'agence" }}
      >
        <p className="m-0">
          Depuis plus de quinze ans, nos conseillers construisent des voyages sur mesure. Chacun
          suit ses destinations, connaît les hébergeurs et les guides sur place, et met à jour les
          tarifs saison après saison.
        </p>
      </EnTetePage>

      <section aria-labelledby="engagements-titre" className="bg-brun text-creme">
        <div className="mx-auto max-w-[1376px] px-4 py-12 sm:px-8 sm:py-16">
          <h2
            id="engagements-titre"
            className="m-0 mb-8 font-display text-[32px] font-semibold sm:text-[40px]"
          >
            Nos engagements
          </h2>
          <ul className="m-0 grid list-none grid-cols-1 gap-6 p-0 md:grid-cols-3">
            {ENGAGEMENTS.map((e) => (
              <li
                key={e}
                className="flex flex-col gap-3 rounded-[22px] bg-white/5 p-6 text-lg leading-relaxed"
              >
                <span
                  aria-hidden="true"
                  className="flex size-9 items-center justify-center rounded-full bg-ocre text-brun"
                >
                  <IconCoche size={16} />
                </span>
                {e}
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section
        aria-labelledby="equipe-titre"
        className="mx-auto max-w-[1376px] px-4 py-12 sm:px-8 sm:py-20"
      >
        <h2
          id="equipe-titre"
          className="m-0 mb-2 font-display text-[32px] font-semibold sm:text-[40px]"
        >
          L'équipe
        </h2>
        <p className="m-0 mb-8 text-lg text-taupe">
          Votre proposition est confirmée par le conseiller de votre destination.
        </p>
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          {CONSEILLERS.map((c) => (
            <article
              key={c.nom}
              className="flex gap-5 rounded-[22px] bg-white p-6 shadow-[0_8px_24px_rgba(43,29,20,0.08)]"
            >
              <span
                aria-hidden="true"
                className="flex size-16 shrink-0 items-center justify-center rounded-full bg-sable font-display text-2xl font-semibold text-rouille"
              >
                {initiales(c.nom)}
              </span>
              <div className="flex flex-col gap-1">
                <h3 className="m-0 font-display text-2xl font-semibold">{c.nom}</h3>
                <span className="text-sm font-bold text-rouille">{c.role}</span>
                <span className="text-taupe">
                  {c.destinations
                    .map((slug) => DESTINATIONS.find((d) => d.slug === slug)!.ville)
                    .join(', ')}
                </span>
              </div>
            </article>
          ))}
        </div>
      </section>
    </>
  )
}
