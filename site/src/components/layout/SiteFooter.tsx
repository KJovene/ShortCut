import { Link } from '@tanstack/react-router'
import { TEXTES } from '@/components/textes'
import { useLangue } from '@/lib/langue'
import { Logo } from './Logo'

export function SiteFooter() {
  const t = TEXTES[useLangue()]
  return (
    <footer className="no-print bg-encre text-ciel">
      <div className="mx-auto flex max-w-[1376px] flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm sm:px-8">
        <div className="flex items-center gap-[18px]">
          <Logo clair petit />
        </div>
        <Link to="/types-de-voyages" className="hover:text-creme">
          {t.typesDeVoyage}
        </Link>
        <nav aria-label={t.liensLegaux} className="flex flex-wrap gap-[18px]">
          <Link
            to="/mentions-legales"
            className="underline-offset-2 hover:text-creme hover:underline"
          >
            {t.mentionsLegales}
          </Link>
          <Link to="/cgv" className="underline-offset-2 hover:text-creme hover:underline">
            {t.cgv}
          </Link>
          <Link
            to="/confidentialite"
            className="underline-offset-2 hover:text-creme hover:underline"
          >
            {t.confidentialite}
          </Link>
        </nav>
      </div>
    </footer>
  )
}
