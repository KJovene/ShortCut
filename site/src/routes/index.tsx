import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/')({
  component: HomePage,
})

function HomePage() {
  return (
    <section>
      <h1 className="text-3xl font-bold">Votre voyage sur mesure</h1>
      <p className="mt-4 text-slate-600">
        Décrivez vos envies à notre assistant : il vous prépare une première proposition, puis un
        conseiller la confirme.
      </p>
    </section>
  )
}
