import { useForm } from '@tanstack/react-form'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createFileRoute, Link, useNavigate } from '@tanstack/react-router'
import { z } from 'zod'
import { Bandeau, Champ, classeBouton } from '@/components/Champ'
import {
  accedeAuBackoffice,
  ouvrirBackoffice,
  type Profil,
  profilQuery,
  seConnecter,
} from '@/lib/auth'

export const Route = createFileRoute('/connexion')({
  component: PageConnexion,
})

const Identifiants = z.object({
  email: z.email('Saisissez une adresse e-mail valide.'),
  motDePasse: z.string().min(1, 'Saisissez votre mot de passe.'),
})

/** Connexion commune : les conseillers et l'administrateur vont au backoffice, les clients restent sur le site. */
function PageConnexion() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const connexion = useMutation({
    mutationFn: ({ email, motDePasse }: z.infer<typeof Identifiants>) =>
      seConnecter(email, motDePasse),
    onSuccess: (profil: Profil) => {
      queryClient.setQueryData(profilQuery.queryKey, profil)
      if (accedeAuBackoffice(profil)) {
        ouvrirBackoffice()
      } else {
        void navigate({ to: '/espace' })
      }
    },
  })

  const form = useForm({
    defaultValues: { email: '', motDePasse: '' },
    validators: { onSubmit: Identifiants },
    onSubmit: ({ value }) => connexion.mutate(value),
  })

  const enCours = connexion.isPending || connexion.isSuccess

  return (
    <section className="mx-auto max-w-sm">
      <h1 className="text-3xl font-bold">Connexion</h1>
      <p className="mt-2 text-slate-600">Accédez à votre compte ShortCut.</p>

      <form
        className="mt-8 space-y-5"
        noValidate
        onSubmit={(event) => {
          event.preventDefault()
          void form.handleSubmit()
        }}
      >
        {connexion.isError && <Bandeau ton="erreur">{connexion.error.message}</Bandeau>}

        <form.Field name="email">
          {(field) => (
            <Champ
              id="email"
              label="E-mail"
              type="email"
              autoComplete="username"
              value={field.state.value}
              onBlur={field.handleBlur}
              onChange={field.handleChange}
              erreur={field.state.meta.errors[0]?.message}
            />
          )}
        </form.Field>

        <form.Field name="motDePasse">
          {(field) => (
            <Champ
              id="motDePasse"
              label="Mot de passe"
              type="password"
              autoComplete="current-password"
              value={field.state.value}
              onBlur={field.handleBlur}
              onChange={field.handleChange}
              erreur={field.state.meta.errors[0]?.message}
            />
          )}
        </form.Field>

        <button type="submit" disabled={enCours} className={classeBouton}>
          {enCours ? 'Connexion…' : 'Se connecter'}
        </button>
      </form>

      <p className="mt-6 text-sm text-slate-600">
        Pas encore de compte ?{' '}
        <Link to="/inscription" className="text-blue-700 underline">
          Créer un compte
        </Link>
      </p>
    </section>
  )
}
