import { useForm } from '@tanstack/react-form'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createFileRoute, Link, useNavigate } from '@tanstack/react-router'
import { z } from 'zod'
import { Bandeau, Champ, classeBouton } from '@/components/Champ'
import { EcranAuthentification } from '@/components/EcranAuthentification'
import { TEXTES } from '@/features/pages/textes'
import {
  accedeAuBackoffice,
  ouvrirBackoffice,
  type Profil,
  profilQuery,
  seConnecter,
} from '@/lib/auth'
import { type Langue, langueDuSite, messageApi, useLangue } from '@/lib/langue'

export const Route = createFileRoute('/connexion')({
  head: () => {
    const t = TEXTES[langueDuSite()].connexion.meta
    return {
      meta: [{ title: t.titre }, { name: 'description', content: t.description }],
    }
  },
  component: PageConnexion,
})

const identifiants = (langue: Langue) => {
  const t = TEXTES[langue].champs
  return z.object({
    email: z.email(t.emailInvalide),
    motDePasse: z.string().min(1, t.motDePasseVide),
  })
}

/** Connexion commune : les conseillers et l'administrateur vont au backoffice, les clients restent sur le site. */
function PageConnexion() {
  const langue = useLangue()
  const t = TEXTES[langue]
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const connexion = useMutation({
    mutationFn: ({ email, motDePasse }: { email: string; motDePasse: string }) =>
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
    validators: { onSubmit: identifiants(langue) },
    onSubmit: ({ value }) => connexion.mutate(value),
  })

  const enCours = connexion.isPending || connexion.isSuccess

  return (
    <EcranAuthentification
      titre={t.connexion.titre}
      sousTitre={t.connexion.sousTitre}
      pied={
        <>
          {t.connexion.pasDeCompte}{' '}
          <Link
            to="/inscription"
            className="font-bold text-rouille underline-offset-2 hover:underline"
          >
            {t.connexion.creerUnCompte}
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
        {connexion.isError && (
          <Bandeau ton="erreur">{messageApi(connexion.error.message, langue)!}</Bandeau>
        )}

        <form.Field name="email">
          {(field) => (
            <Champ
              id="email"
              label={t.champs.email}
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
              label={t.champs.motDePasse}
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
          {enCours ? t.connexion.enCours : t.connexion.seConnecter}
        </button>
      </form>
    </EcranAuthentification>
  )
}
