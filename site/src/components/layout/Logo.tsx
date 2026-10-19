import { Link } from '@tanstack/react-router'
import { cn } from '@/lib/cn'

/** Nom de l'agence, en attente de son identité définitive (même mention que sur les maquettes). */
export const NOM_AGENCE = '[Agence]'

export function Logo({ className, clair = false }: { className?: string; clair?: boolean }) {
  return (
    <Link
      to="/"
      className={cn(
        'font-display text-[22px] font-bold tracking-tight sm:text-[26px]',
        clair ? 'text-creme' : 'text-encre',
        className,
      )}
    >
      {NOM_AGENCE}
      <span className="text-terre">.</span>
    </Link>
  )
}
