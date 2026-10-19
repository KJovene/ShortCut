import { useForm } from '@tanstack/react-form'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createFileRoute, Link, useNavigate } from '@tanstack/react-router'
import { z } from 'zod'
import { Bandeau, Champ, classeBouton } from '@/components/Champ'
import { ErreurFormulaire, type Inscription, profilQuery, sInscrire } from '@/lib/auth'

export const Route = createFileRoute('/inscription')({
  component: PageInscription,
})

const SchemaInscription = z.object({
  prenom: z.string(),
  nom: z.string().trim().min(1, 'Saisissez votre nom.'),
  email: z.email('Saisissez une adresse e-mail valide.'),
  motDePasse: z.string().min(8, 'Le mot de passe doit faire au moins 8 caractères.'),
})

/** Inscription libre : le compte créé est un compte client, connecté d'office. */
function PageInscription() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const inscription = useMutation({
    mutationFn: (valeurs: Inscription) => sInscrire(valeurs),
    onSuccess: (profil) => {
      queryClient.setQueryData(profilQuery.queryKey, profil)
      void navigate({ to: '/profil' })
    },
  })

  const form = useForm({
    defaultValues: { prenom: '', nom: '', email: '', motDePasse: '' },
    validators: { onSubmit: SchemaInscription },
    onSubmit: ({ value }) => inscription.mutate(value),
  })

  const erreurServeur = (champ: string) =>
    inscription.error instanceof ErreurFormulaire ? inscription.error.erreurs[champ] : undefined

  return (
    <section className="mx-auto max-w-sm">
      <h1 className="text-3xl font-bold">Créer un compte</h1>
      <p className="mt-2 text-slate-600">Retrouvez vos demandes de voyage et vos propositions.</p>

      <form
        className="mt-8 space-y-5"
        noValidate
        onSubmit={(event) => {
          event.preventDefault()
          void form.handleSubmit()
        }}
      >
        {inscription.isError && <Bandeau ton="erreur">{inscription.error.message}</Bandeau>}

        <form.Field name="prenom">
          {(field) => (
            <Champ
              id="prenom"
              label="Prénom"
              autoComplete="given-name"
              value={field.state.value}
              onBlur={field.handleBlur}
              onChange={field.handleChange}
              erreur={field.state.meta.errors[0]?.message ?? erreurServeur('prenom')}
            />
          )}
        </form.Field>

        <form.Field name="nom">
          {(field) => (
            <Champ
              id="nom"
              label="Nom"
              autoComplete="family-name"
              value={field.state.value}
              onBlur={field.handleBlur}
              onChange={field.handleChange}
              erreur={field.state.meta.errors[0]?.message ?? erreurServeur('nom')}
            />
          )}
        </form.Field>

        <form.Field name="email">
          {(field) => (
            <Champ
              id="email"
              label="E-mail"
              type="email"
              autoComplete="email"
              value={field.state.value}
              onBlur={field.handleBlur}
              onChange={field.handleChange}
              erreur={field.state.meta.errors[0]?.message ?? erreurServeur('email')}
            />
          )}
        </form.Field>

        <form.Field name="motDePasse">
          {(field) => (
            <Champ
              id="motDePasse"
              label="Mot de passe"
              type="password"
              autoComplete="new-password"
              aide="Au moins 8 caractères."
              value={field.state.value}
              onBlur={field.handleBlur}
              onChange={field.handleChange}
              erreur={field.state.meta.errors[0]?.message ?? erreurServeur('motDePasse')}
            />
          )}
        </form.Field>

        <button type="submit" disabled={inscription.isPending} className={classeBouton}>
          {inscription.isPending ? 'Création…' : 'Créer mon compte'}
        </button>
      </form>

      <p className="mt-6 text-sm text-slate-600">
        Déjà un compte ?{' '}
        <Link to="/connexion" className="text-blue-700 underline">
          Se connecter
        </Link>
      </p>
    </section>
  )
}
