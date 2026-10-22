import { Link } from '@tanstack/react-router'
import { cn } from '@/lib/cn'

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
  return (
    <Link
      to="/"
      aria-label={`${NOM_AGENCE}, accueil`}
      className={cn('flex shrink-0 items-center', className)}
    >
      <img
        src={clair ? '/new-logos/detour-logo-mono-blanc.svg' : '/new-logos/detour-logo-anime.svg'}
        alt=""
        className={cn('block w-auto', petit ? 'h-8' : 'h-[34px] sm:h-10')}
      />
    </Link>
  )
}
