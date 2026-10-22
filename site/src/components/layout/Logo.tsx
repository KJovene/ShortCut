import { Link } from '@tanstack/react-router'
import { cn } from '@/lib/cn'

export const NOM_AGENCE = 'Détour'

/** Symbole du logo (public/logo-detour.svg) suivi du nom de l'agence en Fraunces, comme sur la planche d'identité. */
export function Logo({ className, clair = false }: { className?: string; clair?: boolean }) {
  return (
    <Link
      to="/"
      aria-label={`${NOM_AGENCE}, accueil`}
      className={cn(
        'flex items-center gap-2.5 font-display text-[24px] font-semibold tracking-[-0.8px] sm:text-[28px]',
        clair ? 'text-creme' : 'text-encre',
        className,
      )}
    >
      <img src="/logo-detour.svg" alt="" className="size-8 shrink-0 sm:size-[38px]" />
      {NOM_AGENCE}
    </Link>
  )
}
