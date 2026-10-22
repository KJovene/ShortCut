import { Link } from '@tanstack/react-router'
import { TEXTES } from '@/components/textes'
import { cn } from '@/lib/cn'
import { useLangue } from '@/lib/langue'

export const NOM_AGENCE = 'Détour'

/**
 * Logo DÉTOUR : les fichiers SVG fournis par l'agence (public/new-logos), jamais un texte tapé. Version négative sur
 * fond encre. La charte demande le monogramme sous 96 px de large : le logo garde ici au moins 34 px de haut, soit
 * environ 100 px de large.
 */
export function Logo({
  className,
  clair = false,
  petit = false,
}: {
  className?: string
  clair?: boolean
  /** Pied de page : 32 px de haut. */
  petit?: boolean
}) {
  const t = TEXTES[useLangue()]
  return (
    <Link
      to="/"
      aria-label={t.logo(NOM_AGENCE)}
      className={cn('flex shrink-0 items-center', className)}
    >
      <img
        src={clair ? '/new-logos/detour-logo-negatif.svg' : '/new-logos/detour-logo.svg'}
        alt=""
        className={cn('block w-auto', petit ? 'h-8' : 'h-[34px] sm:h-10')}
      />
    </Link>
  )
}
