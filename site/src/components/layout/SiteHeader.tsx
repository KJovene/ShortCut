import { Link, useLocation } from '@tanstack/react-router'
import { useEffect, useRef, useState } from 'react'
import { ChoixApparence } from '@/components/ChoixApparence'
import { ChoixLangue } from '@/components/ChoixLangue'
import { MenuCompte } from '@/components/MenuCompte'
import { TEXTES } from '@/components/textes'
import { IconFermer, IconMenu } from '@/components/ui/icons'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { cn } from '@/lib/cn'
import { useLangue } from '@/lib/langue'
import { Logo } from './Logo'

/** Pages du menu principal, dans leur ordre d'affichage. */
const LIENS = [
  { to: '/destinations', cle: 'destinations' },
  { to: '/types-de-voyages', cle: 'voyages' },
  { to: '/agence', cle: 'agence' },
  { to: '/contact', cle: 'contact' },
] as const

export function SiteHeader({ className }: { className?: string }) {
  const t = TEXTES[useLangue()]
  const pathname = useLocation({ select: (l) => l.pathname })
  // Le menu mobile reste ouvert sur la page où il a été ouvert : changer de page le referme.
  const [ouvertSur, setOuvertSur] = useState<string | null>(null)
  const menuOuvert = ouvertSur === pathname
  const setMenuOuvert = (ouvert: boolean) => setOuvertSur(ouvert ? pathname : null)
  const bouton = useRef<HTMLButtonElement>(null)
  const ouvrirAssistant = useOuvrirAssistant()

  /** Sur mobile, le logo est petit : l'accueil a aussi son entrée dans le menu. */
  const liensMobile = [
    { to: '/', label: t.accueil },
    ...LIENS.map((lien) => ({ to: lien.to, label: t.liens[lien.cle] })),
  ]

  // Échap referme le menu et rend le focus au bouton.
  useEffect(() => {
    if (!menuOuvert) return
    const touche = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return
      setOuvertSur(null)
      bouton.current?.focus()
    }
    document.addEventListener('keydown', touche)
    return () => document.removeEventListener('keydown', touche)
  }, [menuOuvert])

  return (
    <header className={cn('no-print relative z-30', className)}>
      <div className="mx-auto flex max-w-[1376px] items-center justify-between gap-4 px-4 py-3 sm:px-8 sm:py-[18px]">
        <Logo />

        <nav
          aria-label={t.navigationPrincipale}
          className="hidden gap-[26px] text-[15px] font-bold md:flex"
        >
          {LIENS.map((lien) => (
            <Link
              key={lien.to}
              to={lien.to}
              className="hover:text-terre"
              activeProps={{ className: 'text-terre', 'aria-current': 'page' }}
            >
              {t.liens[lien.cle]}
            </Link>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <ChoixLangue />
          <ChoixApparence />
          {/* Connexion et inscription, ou « Mon espace » une fois connecté. */}
          <div className="hidden sm:flex">
            <MenuCompte />
          </div>
          <button
            type="button"
            aria-label={menuOuvert ? t.fermerMenu : t.ouvrirMenu}
            aria-expanded={menuOuvert}
            aria-controls="menu-mobile"
            ref={bouton}
            onClick={() => setMenuOuvert(!menuOuvert)}
            className="flex size-11 items-center justify-center rounded-full md:hidden"
          >
            {menuOuvert ? <IconFermer size={22} /> : <IconMenu size={22} />}
          </button>
        </div>
      </div>

      {menuOuvert && (
        <nav
          id="menu-mobile"
          aria-label={t.navigationPrincipale}
          className="absolute inset-x-0 top-full border-y border-lin bg-creme px-4 pb-5 shadow-lg md:hidden"
        >
          <ul className="flex flex-col">
            {liensMobile.map((lien) => (
              <li key={lien.to}>
                <Link
                  to={lien.to}
                  activeOptions={{ exact: lien.to === '/' }}
                  onClick={() => setMenuOuvert(false)}
                  className="flex min-h-12 items-center border-b border-lin font-display text-xl font-semibold"
                  activeProps={{ className: 'text-terre', 'aria-current': 'page' }}
                >
                  {lien.label}
                </Link>
              </li>
            ))}
          </ul>
          <button
            type="button"
            onClick={() => {
              setMenuOuvert(false)
              ouvrirAssistant()
            }}
            className="mt-4 flex min-h-12 w-full items-center justify-center rounded-full bg-terre font-extrabold text-white"
          >
            {t.construireMonVoyage}
          </button>
          <div className="mt-3 flex justify-center sm:hidden">
            <MenuCompte />
          </div>
        </nav>
      )}
    </header>
  )
}
