import { useForm } from '@tanstack/react-form'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createFileRoute, Link, useNavigate } from '@tanstack/react-router'
import { z } from 'zod'
import { Bandeau, Champ, classeBouton } from '@/components/Champ'
import { EcranAuthentification } from '@/components/EcranAuthentification'
import {
  accedeAuBackoffice,
  ouvrirBackoffice,
  type Profil,
  profilQuery,
  seConnecter,
} from '@/lib/auth'

export const Route = createFileRoute('/connexion')({
  head: () => ({
    meta: [
      { title: 'Connexion' },
      {
        name: 'description',
        content: 'Connectez-vous pour suivre vos voyages, vos propositions et vos conversations.',
      },
    ],
  }),
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
    <EcranAuthentification
      titre="Connexion"
      sousTitre="Retrouvez vos voyages, vos propositions et vos conversations."
      pied={
        <>
          Pas encore de compte ?{' '}
          <Link
            to="/inscription"
            className="font-bold text-rouille underline-offset-2 hover:underline"
          >
            Créer un compte
          </Link>
        </>
      }
    >
      <form
        className="flex flex-col gap-5"
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
    </EcranAuthentification>
  )
}
