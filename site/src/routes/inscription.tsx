import { useForm } from '@tanstack/react-form'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createFileRoute, Link, useNavigate } from '@tanstack/react-router'
import { z } from 'zod'
import { Bandeau, Champ, classeBouton } from '@/components/Champ'
import { EcranAuthentification } from '@/components/EcranAuthentification'
import { TEXTES } from '@/features/pages/textes'
import { ErreurFormulaire, type Inscription, profilQuery, sInscrire } from '@/lib/auth'
import { type Langue, langueDuSite, messageApi, useLangue } from '@/lib/langue'

export const Route = createFileRoute('/inscription')({
  head: () => {
    const t = TEXTES[langueDuSite()].inscription.meta
    return {
      meta: [{ title: t.titre }, { name: 'description', content: t.description }],
    }
  },
  component: PageInscription,
})

const schemaInscription = (langue: Langue) => {
  const t = TEXTES[langue].champs
  return z.object({
    prenom: z.string(),
    nom: z.string().trim().min(1, t.nomVide),
    email: z.email(t.emailInvalide),
    motDePasse: z.string().min(8, t.motDePasseCourt),
  })
}

/** Inscription libre : le compte créé est un compte client, connecté d'office. */
function PageInscription() {
  const langue = useLangue()
  const t = TEXTES[langue]
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const inscription = useMutation({
    mutationFn: (valeurs: Inscription) => sInscrire(valeurs),
    onSuccess: (profil) => {
      queryClient.setQueryData(profilQuery.queryKey, profil)
      void navigate({ to: '/espace' })
    },
  })

  const form = useForm({
    defaultValues: { prenom: '', nom: '', email: '', motDePasse: '' },
    validators: { onSubmit: schemaInscription(langue) },
    onSubmit: ({ value }) => inscription.mutate(value),
  })

  const erreurServeur = (champ: string) =>
    inscription.error instanceof ErreurFormulaire
      ? messageApi(inscription.error.erreurs[champ], langue)
      : undefined

  return (
    <EcranAuthentification
      titre={t.inscription.titre}
      sousTitre={t.inscription.sousTitre}
      pied={
        <>
          {t.inscription.dejaUnCompte}{' '}
          <Link
            to="/connexion"
            className="font-bold text-rouille underline-offset-2 hover:underline"
          >
            {t.inscription.seConnecter}
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
        {inscription.isError && (
          <Bandeau ton="erreur">{messageApi(inscription.error.message, langue)!}</Bandeau>
        )}

        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <form.Field name="prenom">
            {(field) => (
              <Champ
                id="prenom"
                label={t.champs.prenom}
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
                label={t.champs.nom}
                autoComplete="family-name"
                value={field.state.value}
                onBlur={field.handleBlur}
                onChange={field.handleChange}
                erreur={field.state.meta.errors[0]?.message ?? erreurServeur('nom')}
              />
            )}
          </form.Field>
        </div>

        <form.Field name="email">
          {(field) => (
            <Champ
              id="email"
              label={t.champs.email}
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
              label={t.champs.motDePasse}
              type="password"
              autoComplete="new-password"
              aide={t.inscription.aideMotDePasse}
              value={field.state.value}
              onBlur={field.handleBlur}
              onChange={field.handleChange}
              erreur={field.state.meta.errors[0]?.message ?? erreurServeur('motDePasse')}
            />
          )}
        </form.Field>

        <button type="submit" disabled={inscription.isPending} className={classeBouton}>
          {inscription.isPending ? t.inscription.enCours : t.inscription.creer}
        </button>
      </form>
    </EcranAuthentification>
  )
}
