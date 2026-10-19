import { createFileRoute, Link } from '@tanstack/react-router'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { IconFleche } from '@/components/ui/icons'
import { DESTINATIONS, TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'

export const Route = createFileRoute('/types-de-voyages')({
  head: () => ({
    meta: [
      { title: 'Séjours, circuits, groupes, voyages de noces et entreprise' },
      {
        name: 'description',
        content:
          "Séjours détente, circuits accompagnés, voyages de groupe, voyages de noces et séminaires : l'agence organise tous vos voyages.",
      },
    ],
  }),
  component: TypesPage,
})

// Textes repris des contenus de démo de l'API (PageDuSite « types-de-voyages »).
const TYPES: Record<TypeDeVoyage, { texte: string; photo: string; brouillon: string }> = {
  sejour: {
    texte:
      'Une destination, un hébergement choisi pour vous et des activités à la carte : plage, ville ou nature.',
    photo: '/uploads/pages/sejour.jpg',
    brouillon: 'Un séjour pour deux, ',
  },
  circuit: {
    texte:
      'Plusieurs étapes, un itinéraire pensé jour par jour, avec guide ou en autotour : Islande, Costa Rica…',
    photo: '/uploads/pages/circuit.jpg',
    brouillon: 'Un circuit en plusieurs étapes, ',
  },
  groupe: {
    texte:
      'Amis, associations, scolaires : hébergements adaptés, transferts et visites guidées pour 10 à 60 personnes.',
    photo: '/uploads/pages/groupe.jpg',
    brouillon: 'Un voyage de groupe pour 15 personnes, ',
  },
  noces: {
    texte: 'Suites avec vue, villas avec piscine privée, attentions pour les jeunes mariés.',
    photo: '/uploads/pages/noces.jpg',
    brouillon: 'Notre voyage de noces, ',
  },
  entreprise: {
    texte:
      'Séminaires, conventions et incentives : salles de réunion, hébergement en bloc et activités de cohésion.',
    photo: '/uploads/pages/entreprise.jpg',
    brouillon: 'Un séminaire d’entreprise pour 30 collègues, ',
  },
}

function TypesPage() {
  const ouvrirAssistant = useOuvrirAssistant()

  return (
    <>
      <EnTetePage
        surtitre="Nos voyages"
        titre={
          <>
            Tous les voyages, <em className="font-normal text-terre">sur mesure</em>
          </>
        }
      >
        <p className="m-0 max-w-[640px]">
          Quel que soit votre projet, l'assistant pose les questions propres à votre type de voyage
          : étapes d'un circuit, taille du groupe, date du mariage ou objectif de l'entreprise.
        </p>
      </EnTetePage>

      <section className="mx-auto flex max-w-[1376px] flex-col gap-6 px-4 pb-16 sm:px-8 sm:pb-24">
        {TYPES_DE_VOYAGE.map((t, i) => {
          const contenu = TYPES[t.valeur]
          const nb = DESTINATIONS.filter((d) => d.types.includes(t.valeur)).length
          return (
            <article
              key={t.valeur}
              className="flex flex-col overflow-hidden rounded-[28px] bg-white shadow-[0_8px_24px_rgba(43,29,20,0.08)] md:flex-row md:even:flex-row-reverse"
            >
              <img
                src={contenu.photo}
                alt=""
                className="block h-56 w-full object-cover md:h-auto md:w-[45%]"
              />
              <div className="flex flex-1 flex-col justify-center gap-3 p-6 sm:p-10">
                <span className="text-[13px] font-extrabold tracking-[1.5px] text-rouille uppercase">
                  {String(i + 1).padStart(2, '0')} · {nb} destination{nb > 1 ? 's' : ''}
                </span>
                <h2 className="m-0 font-display text-[32px] font-semibold sm:text-[40px]">
                  {t.pluriel === 'Noces' ? 'Voyages de noces' : t.pluriel}
                </h2>
                <p className="m-0 text-lg leading-relaxed text-taupe">{contenu.texte}</p>
                <p className="m-0 text-sm text-taupe-clair">
                  Frais de dossier : {t.fraisDossier} € par dossier.
                </p>
                <div className="mt-2 flex flex-wrap gap-2.5">
                  <button
                    type="button"
                    onClick={() => ouvrirAssistant(contenu.brouillon)}
                    className="flex min-h-11 items-center gap-2 rounded-full bg-terre px-5 text-sm font-extrabold text-white hover:bg-rouille"
                  >
                    Construire mon voyage
                    <IconFleche size={16} strokeWidth={2.4} />
                  </button>
                  <Link
                    to="/destinations"
                    search={{ type: t.valeur }}
                    className="flex min-h-11 items-center rounded-full border-[1.5px] border-encre px-5 text-sm font-bold hover:bg-sable"
                  >
                    Voir les destinations
                  </Link>
                </div>
              </div>
            </article>
          )
        })}
      </section>
    </>
  )
}
