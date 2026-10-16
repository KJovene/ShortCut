import { Link } from '@tanstack/react-router'

export function NotFound() {
  return (
    <section className="mx-auto flex max-w-[720px] flex-col items-start gap-4 px-4 py-16 sm:px-8 sm:py-24">
      <span className="text-[13px] font-extrabold tracking-[1.5px] text-rouille uppercase">
        Erreur 404
      </span>
      <h1 className="m-0 font-display text-[40px] leading-tight font-semibold sm:text-[54px]">
        Page introuvable
      </h1>
      <p className="m-0 text-lg text-taupe">
        Cette page n'existe pas ou a été déplacée. Nos destinations, elles, vous attendent toujours.
      </p>
      <Link
        to="/destinations"
        className="flex min-h-12 items-center rounded-full bg-terre px-6 font-extrabold text-white hover:bg-rouille"
      >
        Voir les destinations
      </Link>
      <Link to="/" className="font-bold text-rouille underline-offset-2 hover:underline">
        Retour à l'accueil
      </Link>
    </section>
  )
}
