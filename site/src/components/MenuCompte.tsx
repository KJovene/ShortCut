import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import { useEffect, useRef, useState } from 'react'
import { TEXTES } from '@/components/textes'
import { IconFlecheBas } from '@/components/ui/icons'
import { accedeAuBackoffice, profilQuery, seDeconnecter, URL_BACKOFFICE } from '@/lib/auth'
import { useLangue } from '@/lib/langue'

const classeLien =
  'flex min-h-11 items-center rounded-lg px-3 text-[15px] font-semibold hover:bg-sable'

/** Connexion et inscription, ou menu « Mon espace » une fois connecté. */
export function MenuCompte() {
  const t = TEXTES[useLangue()]
  const { data: profil } = useQuery(profilQuery)

  if (!profil) {
    return (
      <nav aria-label={t.compte} className="flex items-center gap-2">
        <Link
          to="/connexion"
          className="flex min-h-11 items-center rounded-full px-4 text-sm font-bold hover:bg-sable"
        >
          {t.connexion}
        </Link>
        <Link
          to="/inscription"
          className="flex min-h-11 items-center rounded-full bg-encre px-5 text-sm font-bold text-creme hover:bg-brun"
        >
          {t.inscription}
        </Link>
      </nav>
    )
  }

  return <MenuConnecte />
}

function MenuConnecte() {
  const t = TEXTES[useLangue()]
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const { data: profil } = useQuery(profilQuery)
  const [ouvert, setOuvert] = useState(false)
  const conteneur = useRef<HTMLDivElement>(null)

  // Fermé par un clic ailleurs ou par Échap.
  useEffect(() => {
    if (!ouvert) return
    const clic = (event: MouseEvent) => {
      if (!conteneur.current?.contains(event.target as Node)) setOuvert(false)
    }
    const touche = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOuvert(false)
    }
    document.addEventListener('mousedown', clic)
    document.addEventListener('keydown', touche)
    return () => {
      document.removeEventListener('mousedown', clic)
      document.removeEventListener('keydown', touche)
    }
  }, [ouvert])

  const deconnexion = useMutation({
    mutationFn: seDeconnecter,
    onSuccess: () => {
      queryClient.setQueryData(profilQuery.queryKey, null)
      queryClient.removeQueries({ queryKey: ['moi'] })
      void navigate({ to: '/' })
    },
  })

  if (!profil) return null
  const initiale = (profil.prenom ?? profil.nom).charAt(0).toUpperCase()
  const fermer = () => setOuvert(false)

  return (
    <div ref={conteneur} className="relative">
      <button
        type="button"
        aria-expanded={ouvert}
        aria-controls="menu-compte"
        onClick={() => setOuvert((o) => !o)}
        className="flex min-h-11 items-center gap-2.5 rounded-full border-[1.5px] border-beige bg-white py-1 pr-3 pl-1 text-sm font-bold hover:border-terre"
      >
        <span
          aria-hidden="true"
          className="flex size-9 items-center justify-center rounded-full bg-terre text-sm text-white"
        >
          {initiale}
        </span>
        {t.monEspace}
        <IconFlecheBas size={14} strokeWidth={2.4} />
      </button>

      {ouvert && (
        <nav
          id="menu-compte"
          aria-label={t.compte}
          className="absolute right-0 z-40 mt-2 flex w-60 flex-col gap-0.5 rounded-2xl border border-lin bg-white p-2 shadow-[0_12px_36px_rgba(43,29,20,0.12)]"
        >
          <p className="px-3 pt-1 pb-2 text-xs text-taupe">
            {t.connecteEnTantQue} <strong className="text-encre">{profil.email}</strong>
          </p>
          {accedeAuBackoffice(profil) ? (
            <a href={URL_BACKOFFICE} className={classeLien}>
              {t.backoffice}
            </a>
          ) : (
            <Link to="/espace" onClick={fermer} className={classeLien}>
              {t.mesVoyages}
            </Link>
          )}
          <Link to="/profil" onClick={fermer} className={classeLien}>
            {t.monProfil}
          </Link>
          <button
            type="button"
            onClick={() => deconnexion.mutate()}
            disabled={deconnexion.isPending}
            className={`${classeLien} text-left text-rouille disabled:opacity-60`}
          >
            {t.seDeconnecter}
          </button>
        </nav>
      )}
    </div>
  )
}
