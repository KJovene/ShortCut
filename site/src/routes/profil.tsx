import { useForm } from '@tanstack/react-form'
import { useMutation, useQueryClient, useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute, redirect } from '@tanstack/react-router'
import { z } from 'zod'
import { Bandeau, Champ, classeBouton } from '@/components/Champ'
import { TEXTES } from '@/features/pages/textes'
import {
  ErreurFormulaire,
  type ModificationProfil,
  modifierProfil,
  type Profil,
  profilQuery,
} from '@/lib/auth'
import { type Langue, langueDuSite, messageApi, useLangue } from '@/lib/langue'

export const Route = createFileRoute('/profil')({
  beforeLoad: async ({ context }) => {
    const profil = await context.queryClient.ensureQueryData(profilQuery)
    if (!profil) {
      throw redirect({ to: '/connexion' })
    }
  },
  head: () => {
    const t = TEXTES[langueDuSite()].profil.meta
    return {
      meta: [
        { title: t.titre },
        { name: 'description', content: t.description },
        { name: 'robots', content: 'noindex' },
      ],
    }
  },
  component: PageProfil,
})

const schemaProfil = (langue: Langue) => {
  const t = TEXTES[langue].champs
  return z.object({
    prenom: z.string(),
    nom: z.string().trim().min(1, t.nomVide),
    email: z.email(t.emailInvalide),
    telephone: z.string(),
    motDePasseActuel: z.string(),
    nouveauMotDePasse: z
      .string()
      .refine((valeur) => valeur === '' || valeur.length >= 8, t.motDePasseCourt),
  })
}

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

function PageProfil() {
  const { data: profil } = useSuspenseQuery(profilQuery)

  // beforeLoad garantit une session.
  return profil ? <FormulaireProfil profil={profil} /> : null
}

function FormulaireProfil({ profil }: { profil: Profil }) {
  const langue = useLangue()
  const t = TEXTES[langue]
  const queryClient = useQueryClient()

  const modification = useMutation({
    mutationFn: (valeurs: ModificationProfil) => modifierProfil(valeurs),
    onSuccess: (misAJour) => queryClient.setQueryData(profilQuery.queryKey, misAJour),
  })

  const form = useForm({
    defaultValues: valeursInitiales(profil),
    validators: { onSubmit: schemaProfil(langue) },
    onSubmit: ({ value }) =>
      // Enregistré : les champs reprennent le profil à jour, mots de passe vidés.
      modification.mutate(value, {
        onSuccess: (misAJour) => form.reset(valeursInitiales(misAJour)),
      }),
  })

  const erreurServeur = (champ: string) =>
    modification.error instanceof ErreurFormulaire
      ? messageApi(modification.error.erreurs[champ], langue)
      : undefined

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
      <h1 className="text-3xl font-bold">{t.profil.titre}</h1>
      <p className="mt-2 text-slate-600">
        {t.profil.compte(t.profil.roles[profil.role])} · {profil.email}
      </p>

      <form
        className="mt-8 space-y-5"
        noValidate
        onSubmit={(event) => {
          event.preventDefault()
          void form.handleSubmit()
        }}
      >
        {modification.isError && (
          <Bandeau ton="erreur">{messageApi(modification.error.message, langue)!}</Bandeau>
        )}
        {modification.isSuccess && <Bandeau ton="succes">{t.profil.misAJour}</Bandeau>}

        {champTexte('prenom', { label: t.champs.prenom, autoComplete: 'given-name' })}
        {champTexte('nom', { label: t.champs.nom, autoComplete: 'family-name' })}
        {champTexte('email', { label: t.champs.email, type: 'email', autoComplete: 'email' })}
        {champTexte('telephone', { label: t.profil.telephone, type: 'tel', autoComplete: 'tel' })}

        <fieldset className="space-y-5 border-t border-slate-200 pt-5">
          <legend className="pr-2 text-sm font-semibold">{t.profil.changerMotDePasse}</legend>
          {champTexte('nouveauMotDePasse', {
            label: t.profil.nouveauMotDePasse,
            type: 'password',
            autoComplete: 'new-password',
            aide: t.profil.aideNouveau,
          })}
          {champTexte('motDePasseActuel', {
            label: t.profil.motDePasseActuel,
            type: 'password',
            autoComplete: 'current-password',
            aide: t.profil.aideActuel,
          })}
        </fieldset>

        <button type="submit" disabled={modification.isPending} className={classeBouton}>
          {modification.isPending ? t.profil.enCours : t.profil.enregistrer}
        </button>
      </form>
    </section>
  )
}
