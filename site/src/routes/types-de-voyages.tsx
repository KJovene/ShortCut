import { useQuery, useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute, Link } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { IconFleche } from '@/components/ui/icons'
import { TYPES_DE_VOYAGE } from '@/data/catalogue'
import { destinationsQuery } from '@/features/catalogue/catalogue'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import {
  decouperContenu,
  metaPage,
  pageQuery,
  photoNommee,
  trouverSection,
} from '@/features/pages/pageDuSite'
import { TEXTES } from '@/features/pages/textes'
import { type Langue, langueDuSite, typeDeVoyage, typesDeVoyage, useLangue } from '@/lib/langue'

export const Route = createFileRoute('/types-de-voyages')({
  loader: ({ context }) => {
    void context.queryClient.prefetchQuery(destinationsQuery())
    return context.queryClient.ensureQueryData(pageQuery('types-de-voyages'))
  },
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, TEXTES[langueDuSite()].types.meta),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: TypesPage,
})

/** « Voyages de noces » plutôt que « Noces » ; « Honeymoons » en anglais. */
const titreType = (t: (typeof TYPES_DE_VOYAGE)[number], langue: Langue) =>
  langue === 'fr' && t.pluriel === 'Noces' ? 'Voyages de noces' : typesDeVoyage(t, langue)

function TypesPage() {
  const langue = useLangue()
  const textes = TEXTES[langue]
  const ouvrirAssistant = useOuvrirAssistant()
  // Une section par type (intertitre « Séjours », « Circuits »…) et une photo nommée d'après le type :
  // contenus du site, modifiables dans le backoffice (traduits par l'API en anglais).
  const { data: page } = useSuspenseQuery(pageQuery('types-de-voyages'))
  const contenu = decouperContenu(page.contenu)
  // Le nombre de destinations par type s'affiche une fois le catalogue chargé.
  const { data: destinations } = useQuery(destinationsQuery())

  return (
    <>
      <EnTetePage surtitre={textes.types.surtitre} titre={<TitreMisEnValeur texte={page.titre} />}>
        <p className="m-0 max-w-[640px]">{textes.types.intro}</p>
      </EnTetePage>

      <section className="mx-auto flex max-w-[1376px] flex-col gap-6 px-4 pb-16 sm:px-8 sm:pb-24">
        {TYPES_DE_VOYAGE.map((t, i) => {
          const section = trouverSection(
            contenu,
            titreType(t, 'fr'),
            t.label,
            t.pluriel,
            titreType(t, 'en'),
            typeDeVoyage(t.valeur, 'en'),
          )
          const photo = photoNommee(page.photos, t.valeur)
          const nb = destinations?.filter((d) => d.types.includes(t.valeur)).length
          return (
            <article
              key={t.valeur}
              className="flex flex-col overflow-hidden rounded-[28px] bg-white shadow-[0_8px_24px_rgba(22,35,63,0.08)] md:flex-row md:even:flex-row-reverse"
            >
              {photo && (
                <img
                  src={photo}
                  alt=""
                  className="block h-56 w-full object-cover md:h-auto md:w-[45%]"
                />
              )}
              <div className="flex flex-1 flex-col justify-center gap-3 p-6 sm:p-10">
                <span className="text-[13px] font-extrabold text-rouille">
                  {String(i + 1).padStart(2, '0')}
                  {nb !== undefined && ` · ${textes.types.nbDestinations(nb)}`}
                </span>
                <h2 className="m-0 font-display text-[32px] font-semibold sm:text-[40px]">
                  {titreType(t, langue)}
                </h2>
                {section?.paragraphes.map((paragraphe) => (
                  <p key={paragraphe} className="m-0 text-lg leading-relaxed text-taupe">
                    {paragraphe}
                  </p>
                ))}
                <p className="m-0 text-sm text-taupe-clair">
                  {textes.types.fraisDeDossier(t.fraisDossier)}
                </p>
                <div className="mt-2 flex flex-wrap gap-2.5">
                  <button
                    type="button"
                    onClick={() => ouvrirAssistant(textes.types.brouillons[t.valeur], { nouveauVoyage: true })}
                    className="flex min-h-11 items-center gap-2 rounded-full bg-terre px-5 text-sm font-extrabold text-white hover:bg-rouille"
                  >
                    {textes.construireMonVoyage}
                    <IconFleche size={16} strokeWidth={2.4} />
                  </button>
                  <Link
                    to="/destinations"
                    search={{ type: t.valeur }}
                    className="flex min-h-11 items-center rounded-full border-[1.5px] border-encre px-5 text-sm font-bold hover:bg-sable"
                  >
                    {textes.types.voirDestinations}
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
