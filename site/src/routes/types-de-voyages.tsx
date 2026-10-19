import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute, Link } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { IconFleche } from '@/components/ui/icons'
import { DESTINATIONS, TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { decouperContenu, metaPage, pageQuery, trouverSection } from '@/features/pages/pageDuSite'

export const Route = createFileRoute('/types-de-voyages')({
  loader: ({ context }) => context.queryClient.ensureQueryData(pageQuery('types-de-voyages')),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, {
      titre: 'Séjours, circuits, groupes, voyages de noces et entreprise',
      description:
        "Séjours détente, circuits accompagnés, voyages de groupe, voyages de noces et séminaires : l'agence organise tous vos voyages.",
    }),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: TypesPage,
})

/** Début de message proposé à l'assistant pour chaque type de voyage. */
const BROUILLONS: Record<TypeDeVoyage, string> = {
  sejour: 'Un séjour pour deux, ',
  circuit: 'Un circuit en plusieurs étapes, ',
  groupe: 'Un voyage de groupe pour 15 personnes, ',
  noces: 'Notre voyage de noces, ',
  entreprise: 'Un séminaire d’entreprise pour 30 collègues, ',
}

const titreType = (t: (typeof TYPES_DE_VOYAGE)[number]) =>
  t.pluriel === 'Noces' ? 'Voyages de noces' : t.pluriel

function TypesPage() {
  const ouvrirAssistant = useOuvrirAssistant()
  // Une section par type (intertitre « Séjours », « Circuits »…) et une photo nommée d'après le type :
  // contenus du site, modifiables dans le backoffice.
  const { data: page } = useSuspenseQuery(pageQuery('types-de-voyages'))
  const contenu = decouperContenu(page.contenu)

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
          const section = trouverSection(contenu, titreType(t), t.label, t.pluriel)
          const photo = page.photos.find((p) => p.endsWith(`/${t.valeur}.jpg`))
          const nb = DESTINATIONS.filter((d) => d.types.includes(t.valeur)).length
          return (
            <article
              key={t.valeur}
              className="flex flex-col overflow-hidden rounded-[28px] bg-white shadow-[0_8px_24px_rgba(43,29,20,0.08)] md:flex-row md:even:flex-row-reverse"
            >
              {photo && (
                <img
                  src={photo}
                  alt=""
                  className="block h-56 w-full object-cover md:h-auto md:w-[45%]"
                />
              )}
              <div className="flex flex-1 flex-col justify-center gap-3 p-6 sm:p-10">
                <span className="text-[13px] font-extrabold tracking-[1.5px] text-rouille uppercase">
                  {String(i + 1).padStart(2, '0')} · {nb} destination{nb > 1 ? 's' : ''}
                </span>
                <h2 className="m-0 font-display text-[32px] font-semibold sm:text-[40px]">
                  {titreType(t)}
                </h2>
                {section?.paragraphes.map((paragraphe) => (
                  <p key={paragraphe} className="m-0 text-lg leading-relaxed text-taupe">
                    {paragraphe}
                  </p>
                ))}
                <p className="m-0 text-sm text-taupe-clair">
                  Frais de dossier : {t.fraisDossier} € par dossier.
                </p>
                <div className="mt-2 flex flex-wrap gap-2.5">
                  <button
                    type="button"
                    onClick={() => ouvrirAssistant(BROUILLONS[t.valeur])}
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
