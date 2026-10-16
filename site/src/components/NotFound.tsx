import { Link } from '@tanstack/react-router'

export function NotFound() {
  return (
    <section>
      <h1 className="text-3xl font-bold">Page introuvable</h1>
      <p className="mt-4 text-slate-600">Cette page n'existe pas ou a été déplacée.</p>
      <Link to="/" className="mt-6 inline-block text-blue-700 underline">
        Retour à l'accueil
      </Link>
    </section>
  )
}
