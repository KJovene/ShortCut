import { useQuery, useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { PageEnChargement, PageIndisponible } from '@/components/EtatPage'
import { EnTetePage } from '@/components/ui/EnTetePage'
import { SautDuO, TitreMisEnValeur } from '@/components/ui/TitreMisEnValeur'
import { IconCoche } from '@/components/ui/icons'
import { destinationsQuery } from '@/features/catalogue/catalogue'
import { decouperContenu, metaPage, pageQuery, trouverSection } from '@/features/pages/pageDuSite'
import { TEXTES } from '@/features/pages/textes'
import { langueDuSite, useLangue } from '@/lib/langue'

export const Route = createFileRoute('/agence')({
  loader: ({ context }) => context.queryClient.ensureQueryData(pageQuery('agence')),
  head: ({ loaderData }) => ({
    meta: metaPage(loaderData, TEXTES[langueDuSite()].agence.meta),
  }),
  pendingComponent: PageEnChargement,
  errorComponent: PageIndisponible,
  component: AgencePage,
})

// Conseillers des données de démo (UtilisateurFixtures) et destinations qu'ils suivent.
const CONSEILLERS = [
  {
    nom: 'Léa Dubois',
    role: 'conseillere',
    destinations: ['lisbonne', 'marrakech', 'santorin', 'rome'],
  },
  {
    nom: 'Hugo Bernard',
    role: 'conseiller',
    destinations: ['tenerife', 'islande', 'bali', 'costa-rica'],
  },
] as const

const initiales = (nom: string) =>
  nom
    .split(' ')
    .map((n) => n[0])
    .join('')

function AgencePage() {
  const langue = useLangue()
  const t = TEXTES[langue].agence
  // Présentation et engagements : contenus du site, modifiables dans le backoffice (traduits par l'API en anglais).
  const { data: page } = useSuspenseQuery(pageQuery('agence', langue))
  const contenu = decouperContenu(page.contenu)
  const engagements = trouverSection(contenu, 'Nos engagements', 'Our commitments')
  const { data: destinations = [] } = useQuery(destinationsQuery(langue))

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

      {engagements && engagements.elements.length > 0 && (
        <section aria-labelledby="engagements-titre" className="aplat-encre bg-brun text-creme">
          <div className="mx-auto max-w-[1376px] px-4 py-12 sm:px-8 sm:py-16">
            <h2
              id="engagements-titre"
              className="m-0 mb-8 font-display text-[32px] font-semibold sm:text-[40px]"
            >
              <SautDuO texte={engagements.titre} />
            </h2>
            <ul className="m-0 grid list-none grid-cols-1 gap-6 p-0 md:grid-cols-3">
              {engagements.elements.map((e) => (
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
      )}

      <section
        aria-labelledby="equipe-titre"
        className="mx-auto max-w-[1376px] px-4 py-12 sm:px-8 sm:py-20"
      >
        <h2
          id="equipe-titre"
          className="m-0 mb-2 font-display text-[32px] font-semibold sm:text-[40px]"
        >
          {t.equipe}
        </h2>
        <p className="m-0 mb-8 text-lg text-taupe">{t.equipeTexte}</p>
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          {CONSEILLERS.map((c) => (
            <article
              key={c.nom}
              className="flex gap-5 rounded-[22px] bg-white p-6 shadow-[0_8px_24px_rgba(22,35,63,0.08)]"
            >
              <span
                aria-hidden="true"
                className="flex size-16 shrink-0 items-center justify-center rounded-full bg-sable font-display text-2xl font-semibold text-rouille"
              >
                {initiales(c.nom)}
              </span>
              <div className="flex flex-col gap-1">
                <h3 className="m-0 font-display text-2xl font-semibold">{c.nom}</h3>
                <span className="text-sm font-bold text-rouille">{t[c.role]}</span>
                <span className="text-taupe">
                  {c.destinations
                    .flatMap((slug) => destinations.find((d) => d.slug === slug)?.ville ?? [])
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
