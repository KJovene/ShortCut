import { Link } from '@tanstack/react-router'
import { useState } from 'react'
import { MenuCompte } from '@/components/MenuCompte'
import { IconFermer, IconMenu } from '@/components/ui/icons'
import { useOuvrirAssistant } from '@/features/chat/useOuvrirAssistant'
import { cn } from '@/lib/cn'
import { Logo } from './Logo'

const LIENS = [
  { to: '/destinations', label: 'Destinations' },
  { to: '/types-de-voyages', label: 'Nos voyages' },
  { to: '/agence', label: "L'agence" },
  { to: '/contact', label: 'Contact' },
] as const

export function SiteHeader({ className }: { className?: string }) {
  const [menuOuvert, setMenuOuvert] = useState(false)
  const ouvrirAssistant = useOuvrirAssistant()

  return (
    <header className={cn('no-print relative z-30', className)}>
      <div className="mx-auto flex max-w-[1376px] items-center justify-between gap-4 px-4 py-3 sm:px-8 sm:py-[18px]">
        <Logo />

        <nav
          aria-label="Navigation principale"
          className="hidden gap-[26px] text-[15px] font-bold md:flex"
        >
          {LIENS.map((lien) => (
            <Link
              key={lien.to}
              to={lien.to}
              className="hover:text-terre"
              activeProps={{ className: 'text-terre', 'aria-current': 'page' }}
            >
              {lien.label}
            </Link>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          {/* Connexion, compte et accès au backoffice (branche login). */}
          <div className="hidden lg:flex">
            <MenuCompte />
          </div>
          <button
            type="button"
            onClick={() => ouvrirAssistant()}
            className="hidden min-h-11 items-center rounded-full bg-encre px-5 text-sm font-bold text-creme hover:bg-brun sm:flex"
          >
            Construire mon voyage
          </button>
          <button
            type="button"
            aria-label={menuOuvert ? 'Fermer le menu' : 'Ouvrir le menu'}
            aria-expanded={menuOuvert}
            aria-controls="menu-mobile"
            onClick={() => setMenuOuvert((o) => !o)}
            className="flex size-11 items-center justify-center rounded-full md:hidden"
          >
            {menuOuvert ? <IconFermer size={22} /> : <IconMenu size={22} />}
          </button>
        </div>
      </div>

      {menuOuvert && (
        <nav
          id="menu-mobile"
          aria-label="Navigation principale"
          className="absolute inset-x-0 top-full border-y border-lin bg-creme px-4 pb-5 shadow-lg md:hidden"
        >
          <ul className="flex flex-col">
            {LIENS.map((lien) => (
              <li key={lien.to}>
                <Link
                  to={lien.to}
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
            Construire mon voyage
          </button>
        </nav>
      )}
    </header>
  )
}
