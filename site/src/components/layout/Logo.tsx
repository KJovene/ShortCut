import { Link } from '@tanstack/react-router'
import { TEXTES } from '@/components/textes'
import { cn } from '@/lib/cn'
import { useLangue } from '@/lib/langue'

export const NOM_AGENCE = 'Détour'

/** Symbole du logo (public/logo-detour.svg) suivi du nom de l'agence en Fraunces, comme sur la planche d'identité. */
export function Logo({ className, clair = false }: { className?: string; clair?: boolean }) {
  const t = TEXTES[useLangue()]
  return (
    <Link
      to="/"
      aria-label={t.logo(NOM_AGENCE)}
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
