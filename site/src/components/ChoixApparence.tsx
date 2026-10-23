import { changerApparence, useApparence } from '@/lib/apparence'
import { cn } from '@/lib/cn'
import { useLangue } from '@/lib/langue'
import { IconLune, IconSoleil } from './ui/icons'
import { TEXTES } from './textes'

/**
 * Interrupteur du thème sombre, à côté du choix de la langue : soleil à gauche, lune à droite, la pastille glisse
 * sous l'apparence active. Pour les lecteurs d'écran, un interrupteur « Thème sombre » activé ou non.
 */
export function ChoixApparence({ className }: { className?: string }) {
  const sombre = useApparence() === 'sombre'
  const t = TEXTES[useLangue()]
  return (
    <button
      type="button"
      role="switch"
      aria-checked={sombre}
      aria-label={t.themeSombre}
      title={t.themeSombre}
      onClick={() => changerApparence(sombre ? 'clair' : 'sombre')}
      className={cn(
        'relative grid grid-cols-2 rounded-full border-[1.5px] border-beige p-0.5',
        className,
      )}
    >
      <span
        aria-hidden="true"
        className={cn(
          'absolute top-0.5 left-0.5 size-9 rounded-full bg-encre motion-safe:transition-transform motion-safe:duration-200',
          sombre && 'translate-x-full',
        )}
      />
      <span
        className={cn(
          'relative flex size-9 items-center justify-center',
          sombre ? 'text-taupe' : 'text-creme',
        )}
      >
        <IconSoleil size={17} />
      </span>
      <span
        className={cn(
          'relative flex size-9 items-center justify-center',
          sombre ? 'text-creme' : 'text-taupe',
        )}
      >
        <IconLune size={17} />
      </span>
    </button>
  )
}
