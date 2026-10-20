import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { DESTINATIONS } from '@/data/catalogue'
import { ApiError } from '@/lib/api'
import { envoyerMessage, ficheVersDemande, ouvrirConversation } from './api'
import { MESSAGE_ACCUEIL, nouvelId, type Message } from './assistant'
import { ChatContext, type EtatChat, type Reprise } from './chatContext'
import { type Demande, demandeVide } from './demande'

const CLE = 'chat'

const INJOIGNABLE = 'Je n’arrive pas à joindre l’agence. Vérifiez votre connexion, puis réessayez.'

const CONFIRMATION =
  'Votre demande est bien prise en compte. Un conseiller de l’agence revient vers vous dans les plus brefs délais avec votre proposition.'

const etatInitial = (): EtatChat => ({
  statut: 'accueil',
  messages: [MESSAGE_ACCUEIL],
  conversation: { demande: demandeVide(), demandes: [] },
  reflexion: false,
  ouvert: false,
  brouillon: '',
})

function restaurer(): EtatChat {
  try {
    const sauvegarde = sessionStorage.getItem(CLE)
    if (sauvegarde) return { ...etatInitial(), ...(JSON.parse(sauvegarde) as Partial<EtatChat>) }
  } catch {
    // Stockage indisponible : nouvelle conversation.
  }
  return etatInitial()
}

/**
 * État du chatbot partagé par tout le site : la conversation commencée sur l'accueil continue dans la
 * fenêtre ouverte depuis les autres pages. L'assistant répond par l'API (POST /api/chat, en streaming) ;
 * quand le client confirme son récapitulatif, la demande est transmise et la conversation se termine.
 */
export function ChatProvider({ children }: { children: ReactNode }) {
  const [etat, setEtat] = useState<EtatChat>(restaurer)
  const etatCourant = useRef(etat)
  /** Une réponse est en cours : un nouveau message attend qu'elle soit finie. */
  const enCours = useRef(false)
  /** Annule la réponse en cours quand le client recommence. */
  const annulation = useRef<AbortController | null>(null)

  useEffect(() => {
    etatCourant.current = etat
    const { statut, messages, conversation, jeton, terminee } = etat
    try {
      sessionStorage.setItem(
        CLE,
        JSON.stringify({ statut, messages, conversation, jeton, terminee }),
      )
    } catch {
      // Sans stockage, la conversation dure le temps de la page.
    }
  }, [etat])

  useEffect(() => () => annulation.current?.abort(), [])

  const ajouter = useCallback((messages: Message[], maj: Partial<EtatChat> = {}) => {
    setEtat((e) => ({ ...e, ...maj, messages: [...e.messages, ...messages] }))
  }, [])

  /** Agent Conversation : envoie le message à l'API et affiche la réponse au fil du flux. */
  const discuter = useCallback(
    async (texte: string) => {
      const controleur = new AbortController()
      annulation.current = controleur
      const actif = () => !controleur.signal.aborted
      let jeton = etatCourant.current.jeton
      let demande: Demande = etatCourant.current.conversation.demande
      let confirme = false
      /** Bulle de l'assistant en cours d'écriture. */
      let bulle: string | undefined

      const lire = async (jetonConversation: string) => {
        for await (const ev of envoyerMessage(jetonConversation, texte, controleur.signal)) {
          if (!actif()) return
          switch (ev.type) {
            case 'texte': {
              if (!bulle) {
                const id = (bulle = nouvelId())
                ajouter([{ id, auteur: 'assistant', texte: ev.texte }], { reflexion: false })
              } else {
                const id = bulle
                setEtat((e) => ({
                  ...e,
                  messages: e.messages.map((m) =>
                    m.id === id ? { ...m, texte: m.texte + ev.texte } : m,
                  ),
                }))
              }
              break
            }
            case 'outil':
              // Le texte qui suivra l'outil va dans une nouvelle bulle.
              bulle = undefined
              setEtat((e) => ({ ...e, reflexion: true }))
              break
            case 'demande': {
              if (ev.fiche) {
                const nouvelle = (demande = ficheVersDemande(ev.fiche, demande))
                setEtat((e) => ({ ...e, conversation: { ...e.conversation, demande: nouvelle } }))
              }
              confirme ||= ev.recapitulatifConfirme
              break
            }
            case 'erreur':
              bulle = undefined
              ajouter([{ id: nouvelId(), auteur: 'assistant', texte: ev.erreur }])
              break
          }
        }
      }

      try {
        if (!jeton) {
          jeton = await ouvrirConversation(controleur.signal)
          const nouveau = jeton
          setEtat((e) => ({ ...e, jeton: nouveau }))
        }
        try {
          await lire(jeton)
        } catch (err) {
          // Jeton inconnu (conversation expirée, base réinitialisée) : nouvelle conversation, une fois.
          if (!(err instanceof ApiError && err.status === 401)) throw err
          const nouveau = await ouvrirConversation(controleur.signal)
          setEtat((e) => ({ ...e, jeton: nouveau }))
          await lire(nouveau)
        }
      } catch (err) {
        if (actif()) {
          ajouter([
            {
              id: nouvelId(),
              auteur: 'assistant',
              texte: err instanceof ApiError ? err.message : INJOIGNABLE,
            },
          ])
        }
      } finally {
        if (annulation.current === controleur) annulation.current = null
      }

      if (!actif()) return
      enCours.current = false
      setEtat((e) => ({ ...e, reflexion: false }))
      // Récapitulatif confirmé : la demande est enregistrée, la conversation est aboutie.
      if (confirme) {
        ajouter(
          [{ id: nouvelId(), auteur: 'assistant', type: 'confirmation', texte: CONFIRMATION }],
          {
            terminee: true,
          },
        )
      }
    },
    [ajouter],
  )

  const envoyer = useCallback(
    (texte: string) => {
      const propre = texte.trim()
      if (!propre || enCours.current || etatCourant.current.terminee) return
      enCours.current = true
      const client: Message = { id: nouvelId(), auteur: 'client', texte: propre }
      setEtat((x) => ({
        ...x,
        statut: 'conversation',
        brouillon: '',
        reflexion: true,
        messages: [...x.messages, client],
      }))
      void discuter(propre)
    },
    [discuter],
  )

  const choisirAmbiance = useCallback(
    (slug: string) => {
      const dest = DESTINATIONS.find((d) => d.slug === slug)
      if (dest) envoyer(`${dest.pointsForts[0]}, à ${dest.ville} !`)
    },
    [envoyer],
  )

  // Actions stables d'un rendu à l'autre : la fenêtre du chatbot rejoue son effet de focus quand `fermer`
  // change, ce qui ferait perdre le focus au champ de saisie à chaque lettre tapée.
  const actions = useMemo(
    () => ({
      ouvrir: (brouillon?: string) =>
        setEtat((e) => ({ ...e, ouvert: true, brouillon: brouillon ?? e.brouillon })),
      fermer: () => setEtat((e) => ({ ...e, ouvert: false })),
      setBrouillon: (brouillon: string) => setEtat((e) => ({ ...e, brouillon })),
      recommencer: () => {
        annulation.current?.abort()
        enCours.current = false
        // Sans jeton : le prochain message ouvre une nouvelle conversation côté API.
        setEtat((e) => ({ ...etatInitial(), ouvert: e.ouvert }))
      },
      reprendre: ({ jeton, messages, fiche }: Reprise) => {
        annulation.current?.abort()
        enCours.current = false
        // La conversation de l'API remplace celle du navigateur : mêmes messages, même fiche, même jeton.
        setEtat({
          ...etatInitial(),
          statut: 'conversation',
          jeton,
          messages: messages.map((m): Message =>
            m.role === 'client'
              ? { id: nouvelId(), auteur: 'client', texte: m.contenu }
              : { id: nouvelId(), auteur: 'assistant', texte: m.contenu },
          ),
          conversation: { demande: ficheVersDemande(fiche, demandeVide()), demandes: [] },
          ouvert: true,
        })
      },
    }),
    [],
  )

  const valeur = useMemo(
    () => ({ ...etat, ...actions, envoyer, choisirAmbiance }),
    [etat, actions, envoyer, choisirAmbiance],
  )

  return <ChatContext.Provider value={valeur}>{children}</ChatContext.Provider>
}
