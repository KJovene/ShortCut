import { Link } from '@tanstack/react-router'
import { TEXTES } from '@/components/textes'
import { useLangue } from '@/lib/langue'

export function NotFound() {
  const t = TEXTES[useLangue()]
  return (
    <section className="mx-auto flex max-w-[720px] flex-col items-start gap-4 px-4 py-16 sm:px-8 sm:py-24">
      <span className="text-[13px] font-extrabold text-rouille">{t.erreur404}</span>
      <h1 className="m-0 font-display text-[40px] leading-tight font-semibold sm:text-[54px]">
        {t.pageIntrouvable}
      </h1>
      <p className="m-0 text-lg text-taupe">{t.pageIntrouvableTexte}</p>
      <Link
        to="/destinations"
        className="flex min-h-12 items-center rounded-full bg-terre px-6 font-extrabold text-white hover:bg-rouille"
      >
        {t.voirDestinations}
      </Link>
      <Link to="/" className="font-bold text-rouille underline-offset-2 hover:underline">
        {t.retourAccueil}
      </Link>
    </section>
  )
}
