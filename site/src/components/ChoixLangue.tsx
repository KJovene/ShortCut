import { useRouter } from '@tanstack/react-router'
import { changerLangue, type Langue, useLangue } from '@/lib/langue'
import { cn } from '@/lib/cn'
import { TEXTES } from './textes'

const LANGUES: Langue[] = ['fr', 'en']

/**
 * Bascule FR | EN de l'en-tête, à côté de la connexion. Le site passe dans la langue choisie : textes fixes,
 * pages et destinations (traduites par l'API) et premier message du chatbot.
 */
export function ChoixLangue({ className }: { className?: string }) {
  const langue = useLangue()
  const router = useRouter()
  const t = TEXTES[langue]

  const choisir = (choix: Langue) => {
    if (choix === langue) return
    changerLangue(choix)
    // Les loaders relisent les pages dans la nouvelle langue, et les titres des onglets suivent.
    void router.invalidate()
  }

  return (
    <div
      role="group"
      aria-label={t.choixLangue}
      className={cn('flex items-center rounded-full border-[1.5px] border-beige p-0.5', className)}
    >
      {LANGUES.map((choix) => (
        <button
          key={choix}
          type="button"
          lang={choix}
          aria-pressed={choix === langue}
          aria-label={choix === 'fr' ? t.francais : t.anglais}
          onClick={() => choisir(choix)}
          className={cn(
            'flex min-h-9 min-w-10 items-center justify-center rounded-full px-2.5 text-[13px] font-extrabold',
            choix === langue ? 'bg-encre text-creme' : 'text-taupe hover:text-encre',
          )}
        >
          {choix.toUpperCase()}
        </button>
      ))}
    </div>
  )
}
