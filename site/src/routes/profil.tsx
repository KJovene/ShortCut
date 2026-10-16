import { useForm } from '@tanstack/react-form'
import { useMutation, useQueryClient, useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute, redirect } from '@tanstack/react-router'
import { z } from 'zod'
import { Bandeau, Champ, classeBouton } from '@/components/Champ'
import {
  ErreurFormulaire,
  type ModificationProfil,
  modifierProfil,
  type Profil,
  profilQuery,
} from '@/lib/auth'

export const Route = createFileRoute('/profil')({
  beforeLoad: async ({ context }) => {
    const profil = await context.queryClient.ensureQueryData(profilQuery)
    if (!profil) {
      throw redirect({ to: '/connexion' })
    }
  },
  component: PageProfil,
})

const SchemaProfil = z.object({
  prenom: z.string(),
  nom: z.string().trim().min(1, 'Saisissez votre nom.'),
  email: z.email('Saisissez une adresse e-mail valide.'),
  telephone: z.string(),
  motDePasseActuel: z.string(),
  nouveauMotDePasse: z
    .string()
    .refine(
      (valeur) => valeur === '' || valeur.length >= 8,
      'Le mot de passe doit faire au moins 8 caractères.',
    ),
})

function valeursInitiales(profil: Profil): ModificationProfil {
  return {
    prenom: profil.prenom ?? '',
    nom: profil.nom,
    email: profil.email,
    telephone: profil.telephone ?? '',
    motDePasseActuel: '',
    nouveauMotDePasse: '',
  }
}

const LIBELLES_ROLE: Record<Profil['role'], string> = {
  ROLE_CLIENT: 'Client',
  ROLE_CONSEILLER: 'Conseiller',
  ROLE_ADMIN: 'Administrateur',
}

function PageProfil() {
  const { data: profil } = useSuspenseQuery(profilQuery)

  // beforeLoad garantit une session.
  return profil ? <FormulaireProfil profil={profil} /> : null
}

function FormulaireProfil({ profil }: { profil: Profil }) {
  const queryClient = useQueryClient()

  const modification = useMutation({
    mutationFn: (valeurs: ModificationProfil) => modifierProfil(valeurs),
    onSuccess: (misAJour) => queryClient.setQueryData(profilQuery.queryKey, misAJour),
  })

  const form = useForm({
    defaultValues: valeursInitiales(profil),
    validators: { onSubmit: SchemaProfil },
    onSubmit: ({ value }) =>
      // Enregistré : les champs reprennent le profil à jour, mots de passe vidés.
      modification.mutate(value, {
        onSuccess: (misAJour) => form.reset(valeursInitiales(misAJour)),
      }),
  })

  const erreurServeur = (champ: string) =>
    modification.error instanceof ErreurFormulaire ? modification.error.erreurs[champ] : undefined

  const champTexte = (
    name: 'prenom' | 'nom' | 'email' | 'telephone' | 'motDePasseActuel' | 'nouveauMotDePasse',
    props: Omit<
      React.ComponentProps<typeof Champ>,
      'id' | 'value' | 'onBlur' | 'onChange' | 'erreur'
    >,
  ) => (
    <form.Field name={name}>
      {(field) => (
        <Champ
          id={name}
          {...props}
          value={field.state.value}
          onBlur={field.handleBlur}
          onChange={field.handleChange}
          erreur={field.state.meta.errors[0]?.message ?? erreurServeur(name)}
        />
      )}
    </form.Field>
  )

  return (
    <section className="mx-auto max-w-md">
      <h1 className="text-3xl font-bold">Mon profil</h1>
      <p className="mt-2 text-slate-600">
        Compte {LIBELLES_ROLE[profil.role].toLowerCase()} · {profil.email}
      </p>

      <form
        className="mt-8 space-y-5"
        noValidate
        onSubmit={(event) => {
          event.preventDefault()
          void form.handleSubmit()
        }}
      >
        {modification.isError && <Bandeau ton="erreur">{modification.error.message}</Bandeau>}
        {modification.isSuccess && <Bandeau ton="succes">Profil mis à jour.</Bandeau>}

        {champTexte('prenom', { label: 'Prénom', autoComplete: 'given-name' })}
        {champTexte('nom', { label: 'Nom', autoComplete: 'family-name' })}
        {champTexte('email', { label: 'E-mail', type: 'email', autoComplete: 'email' })}
        {champTexte('telephone', { label: 'Téléphone', type: 'tel', autoComplete: 'tel' })}

        <fieldset className="space-y-5 border-t border-slate-200 pt-5">
          <legend className="pr-2 text-sm font-semibold">Changer de mot de passe</legend>
          {champTexte('nouveauMotDePasse', {
            label: 'Nouveau mot de passe',
            type: 'password',
            autoComplete: 'new-password',
            aide: 'Laissez vide pour garder le mot de passe actuel.',
          })}
          {champTexte('motDePasseActuel', {
            label: 'Mot de passe actuel',
            type: 'password',
            autoComplete: 'current-password',
            aide: "Exigé pour changer d'e-mail ou de mot de passe.",
          })}
        </fieldset>

        <button type="submit" disabled={modification.isPending} className={classeBouton}>
          {modification.isPending ? 'Enregistrement…' : 'Enregistrer'}
        </button>
      </form>
    </section>
  )
}
