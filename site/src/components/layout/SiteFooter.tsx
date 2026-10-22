import { Link } from '@tanstack/react-router'
import { Logo } from './Logo'

export function SiteFooter() {
  return (
    <footer className="no-print bg-encre text-ciel">
      <div className="mx-auto flex max-w-[1376px] flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm sm:px-8">
        <div className="flex items-center gap-[18px]">
          <Logo clair petit />
          <span className="font-display text-[17px] font-semibold text-creme">
            Prenez le pas de côté.
          </span>
        </div>
        <Link to="/types-de-voyages" className="hover:text-creme">
          Séjours · Circuits · Groupes · Voyages de noces · Entreprise
        </Link>
        <nav aria-label="Liens légaux" className="flex flex-wrap gap-[18px]">
          <Link
            to="/mentions-legales"
            className="underline-offset-2 hover:text-creme hover:underline"
          >
            Mentions légales
          </Link>
          <Link to="/cgv" className="underline-offset-2 hover:text-creme hover:underline">
            CGV
          </Link>
          <Link
            to="/confidentialite"
            className="underline-offset-2 hover:text-creme hover:underline"
          >
            Confidentialité et cookies
          </Link>
        </nav>
      </div>
    </footer>
  )
}
