import { useQuery } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { IconCoche } from '@/components/ui/icons'
import { destinationsQuery } from '@/features/catalogue/catalogue'

const AVANTAGES = [
  'Vos propositions de voyage, à retrouver à tout moment',
  'Le suivi de la confirmation par votre conseiller',
  'Vos conversations avec l’assistant',
]

/**
 * Gabarit des écrans de connexion et d'inscription (maquette BO-Login, aux couleurs du site) :
 * une photo de voyage avec ce qu'apporte le compte, et le formulaire en carte.
 */
export function EcranAuthentification({
  titre,
  sousTitre,
  children,
  pied,
}: {
  titre: string
  sousTitre: string
  children: ReactNode
  /** Lien vers l'autre écran (« Pas encore de compte ? »…). */
  pied: ReactNode
}) {
  // Une destination mise en avant dans le backoffice ; sans catalogue, l'encadré reste sans photo.
  const { data: destinations = [] } = useQuery(destinationsQuery())
  const photo = destinations.find((d) => d.miseEnAvant && d.photo)

  return (
    <div className="mx-auto flex max-w-[1376px] flex-wrap gap-6 px-4 py-6 sm:px-8 sm:py-10 lg:gap-10">
      <aside
        aria-label="Votre compte Détour"
        className="relative hidden min-h-[560px] flex-[1_1_480px] overflow-hidden rounded-[28px] bg-sable md:block"
      >
        {photo && (
          <img
            src={photo.photo}
            alt={photo.alt}
            className="absolute inset-0 size-full object-cover"
          />
        )}
        <div className="absolute right-8 bottom-8 left-8 flex max-w-[440px] flex-col gap-3 rounded-[22px] bg-creme/95 p-6 shadow-[0_18px_50px_rgba(22,35,63,0.18)]">
          <p className="m-0 font-display text-[26px] leading-tight font-semibold">
            L’assistant propose, <em className="font-normal text-terre">le conseiller confirme</em>
          </p>
          <ul className="m-0 flex list-none flex-col gap-2 p-0 text-[15px] text-taupe">
            {AVANTAGES.map((avantage) => (
              <li key={avantage} className="flex items-start gap-2.5">
                <span
                  aria-hidden="true"
                  className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-vert-fonce text-white"
                >
                  <IconCoche size={12} strokeWidth={3} />
                </span>
                {avantage}
              </li>
            ))}
          </ul>
        </div>
      </aside>

      <section className="flex flex-[1_1_420px] items-center justify-center md:py-6">
        <div className="flex w-full max-w-[440px] flex-col gap-6 rounded-[28px] bg-white p-6 shadow-[0_18px_50px_rgba(22,35,63,0.10)] sm:p-9">
          <div className="flex flex-col gap-2">
            <img src="/new-logos/detour-icone-app.svg" alt="" className="mb-2 size-12" />
            <h1 className="m-0 font-display text-[34px] leading-tight font-semibold tracking-[-0.8px]">
              {titre}
            </h1>
            <p className="m-0 text-taupe">{sousTitre}</p>
          </div>
          {children}
          <p className="m-0 border-t border-sable pt-5 text-center text-sm text-taupe">{pied}</p>
        </div>
      </section>
    </div>
  )
}
