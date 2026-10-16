import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import { accedeAuBackoffice, profilQuery, seDeconnecter, URL_BACKOFFICE } from '@/lib/auth'

/** Liens du compte dans l'en-tête, selon la session. */
export function MenuCompte() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const { data: profil } = useQuery(profilQuery)

  const deconnexion = useMutation({
    mutationFn: seDeconnecter,
    onSuccess: () => {
      queryClient.setQueryData(profilQuery.queryKey, null)
      void navigate({ to: '/' })
    },
  })

  if (!profil) {
    return (
      <nav aria-label="Compte" className="flex items-center gap-4 text-sm">
        <Link to="/connexion" className="hover:underline">
          Connexion
        </Link>
        <Link
          to="/inscription"
          className="rounded-md bg-slate-900 px-3 py-1.5 text-white hover:bg-slate-700"
        >
          Créer un compte
        </Link>
      </nav>
    )
  }

  return (
    <nav aria-label="Compte" className="flex items-center gap-4 text-sm">
      {accedeAuBackoffice(profil) && (
        <a href={URL_BACKOFFICE} className="hover:underline">
          Backoffice
        </a>
      )}
      <Link to="/profil" className="hover:underline">
        {profil.prenom ?? profil.nom}
      </Link>
      <button
        type="button"
        onClick={() => deconnexion.mutate()}
        disabled={deconnexion.isPending}
        className="text-slate-600 hover:underline disabled:opacity-60"
      >
        Déconnexion
      </button>
    </nav>
  )
}
