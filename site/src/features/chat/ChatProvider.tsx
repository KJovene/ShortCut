import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useLocation } from '@tanstack/react-router'
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { destinationsQuery } from '@/features/catalogue/catalogue'
import { profilQuery } from '@/lib/auth'
import { ApiError } from '@/lib/api'
import { envoyerMessage, ficheVersDemande, ouvrirConversation } from './api'
import { ChatContext, type EtatChat, type Reprise } from './chatContext'
import { MESSAGE_ACCUEIL, nouvelId, type Message } from './conversation'
import { demandeDepuisQuestionnaire, type Demande, demandeVide } from './demande'
import { TEXTES } from './textes'

const CLE = 'chat'

const etatInitial = (): EtatChat => ({
  statut: 'accueil',
  messages: [MESSAGE_ACCUEIL],
  conversation: { demande: demandeVide() },
  reflexion: false,
  ouvert: false,
  brouillon: '',
  langue: 'fr',
  questionnaire: { reponses: [], termine: false, transmis: false },
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
  const queryClient = useQueryClient()
  const chemin = useLocation({ select: (location) => location.pathname })
  const { data: profil } = useQuery(profilQuery)
  const estConnecte = profil !== null && profil !== undefined
  const etatCourant = useRef(etat)
  /** Une réponse est en cours : un nouveau message attend qu'elle soit finie. */
  const enCours = useRef(false)
  /** Annule la réponse en cours quand le client recommence. */
  const annulation = useRef<AbortController | null>(null)
  const cheminPrecedent = useRef(chemin)

  useEffect(() => {
    if (cheminPrecedent.current !== chemin) {
      cheminPrecedent.current = chemin
      annulation.current?.abort()
      enCours.current = false
      setEtat((e) => ({ ...e, ouvert: false, reflexion: false }))
    }
  }, [chemin])

  useEffect(() => {
    etatCourant.current = etat
    const {
      statut,
      messages,
      conversation,
      jeton,
      terminee,
      reprise,
      langue,
      questionnaire,
      contexteInvite,
      destinationInvite,
    } = etat
    try {
      sessionStorage.setItem(
        CLE,
        JSON.stringify({
          statut,
          messages,
          conversation,
          jeton,
          terminee,
          reprise,
          langue,
          questionnaire,
          contexteInvite,
          destinationInvite,
        }),
      )
    } catch {
      // Sans stockage, la conversation dure le temps de la page.
    }
  }, [etat])

  useEffect(() => () => annulation.current?.abort(), [])

  /** Destinations publiées, pour reconnaître le lieu cité par le client ; vide si le catalogue est indisponible. */
  const catalogue = useCallback(
    () => queryClient.fetchQuery(destinationsQuery()).catch(() => []),
    [queryClient],
  )

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
      const questionnaire = etatCourant.current.questionnaire
      const reprendQuestionnaire = Boolean(
        etatCourant.current.contexteInvite && !questionnaire.transmis,
      )
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
                const destinations = await catalogue()
                if (!actif()) return
                const nouvelle = (demande = ficheVersDemande(ev.fiche, demande, destinations))
                setEtat((e) => ({ ...e, conversation: { ...e.conversation, demande: nouvelle } }))
              }
              confirme ||= ev.recapitulatifConfirme
              // Le client écrit en anglais : les textes du chatbot passent en anglais (US15).
              setEtat((e) => (e.langue === ev.langue ? e : { ...e, langue: ev.langue }))
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
        if (!jeton || reprendQuestionnaire) {
          jeton = await ouvrirConversation(
            reprendQuestionnaire ? questionnaire.reponses : undefined,
            etatCourant.current.destinationInvite,
            controleur.signal,
          )
          const nouveau = jeton
          setEtat((e) => ({ ...e, jeton: nouveau }))
        }
        try {
          await lire(jeton)
        } catch (err) {
          // Jeton inconnu (conversation expirée, base réinitialisée) : nouvelle conversation, une fois.
          if (!(err instanceof ApiError && err.status === 401)) throw err
          const nouveau = await ouvrirConversation(undefined, undefined, controleur.signal)
          setEtat((e) => ({ ...e, jeton: nouveau }))
          await lire(nouveau)
        }
      } catch (err) {
        if (actif()) {
          ajouter([
            {
              id: nouvelId(),
              auteur: 'assistant',
              texte:
                err instanceof ApiError
                  ? err.message
                  : TEXTES[etatCourant.current.langue].injoignable,
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
          [
            {
              id: nouvelId(),
              auteur: 'assistant',
              type: 'confirmation',
              texte: TEXTES[etatCourant.current.langue].confirmation,
            },
          ],
          {
            terminee: true,
          },
        )
      }
      if (reprendQuestionnaire) {
        await queryClient.invalidateQueries({ queryKey: ['moi', 'demandes'] })
      }
    },
    [ajouter, catalogue, queryClient],
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
        contexteInvite: undefined,
        questionnaire:
          x.questionnaire.termine && x.contexteInvite
            ? { ...x.questionnaire, transmis: true }
            : x.questionnaire,
        reflexion: true,
        messages: [...x.messages, client],
      }))
      void discuter(
        [etatCourant.current.contexteInvite, etatCourant.current.destinationInvite]
          .filter(Boolean)
          .concat(propre)
          .join('\n\n'),
      )
    },
    [discuter],
  )

  // Actions stables d'un rendu à l'autre : la fenêtre du chatbot rejoue son effet de focus quand `fermer`
  // change, ce qui ferait perdre le focus au champ de saisie à chaque lettre tapée.
  const actions = useMemo(
    () => ({
      ouvrir: (brouillon?: string) =>
        setEtat((e) => ({ ...e, ouvert: true, brouillon: brouillon ?? e.brouillon })),
      // Fin d'une modification de voyage : ses changements sont enregistrés côté API, le chat repart à zéro.
      fermer: () => {
        if (etatCourant.current.reprise) {
          annulation.current?.abort()
          enCours.current = false
        }
        setEtat((e) => (e.reprise ? etatInitial() : { ...e, ouvert: false }))
      },
      setBrouillon: (brouillon: string) => setEtat((e) => ({ ...e, brouillon })),
      setDestinationInvite: (destination: string) =>
        setEtat((e) => ({
          ...e,
          destinationInvite: destination,
          conversation: { demande: { ...e.conversation.demande, zone: destination } },
        })),
      recommencer: () => {
        annulation.current?.abort()
        enCours.current = false
        // Sans jeton : le prochain message ouvre une nouvelle conversation côté API.
        setEtat((e) => ({ ...etatInitial(), ouvert: e.ouvert }))
      },
      reprendre: ({ jeton, messages, fiche }: Reprise) => {
        annulation.current?.abort()
        enCours.current = false
        const enCache = queryClient.getQueryData(destinationsQuery().queryKey)
        const demande = ficheVersDemande(fiche, demandeVide(), enCache ?? [])
        // Catalogue pas encore chargé : la destination citée est reconnue dès qu'il arrive.
        if (!enCache) {
          void catalogue().then((destinations) =>
            setEtat((e) =>
              e.jeton === jeton && e.conversation.demande === demande
                ? {
                    ...e,
                    conversation: { demande: ficheVersDemande(fiche, demandeVide(), destinations) },
                  }
                : e,
            ),
          )
        }
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
          conversation: { demande },
          reprise: true,
          ouvert: true,
        })
      },
      repondreQuestion: (reponse: string) => {
        setEtat((e) => {
          if (e.questionnaire.termine || e.questionnaire.reponses.length >= 3) return e
          const reponses = [...e.questionnaire.reponses, reponse]
          return {
            ...e,
            questionnaire: { reponses, termine: reponses.length === 3, transmis: false },
            conversation: {
              demande: demandeDepuisQuestionnaire(reponses, e.conversation.demande),
            },
            contexteInvite:
              reponses.length === 3
                ? `Préférences recueillies avant la connexion. Type de voyage : ${reponses[0]}. Nombre de voyageurs : ${reponses[1] === 'À deux' ? '2 adultes' : reponses[1]}. Budget indicatif : ${reponses[2]}. Utilise ces informations pour compléter la fiche de demande.`
                : e.contexteInvite,
          }
        })
      },
    }),
    [queryClient, catalogue],
  )

  useEffect(() => {
    if (
      !estConnecte ||
      !etat.questionnaire.termine ||
      etat.questionnaire.transmis ||
      !etat.contexteInvite
    ) {
      return
    }

    // Le contexte est envoyé dès que la session devient disponible : l'agent peut ainsi remplir
    // la demande côté serveur avant même que le client ne saisisse une précision supplémentaire.
    envoyer('Je souhaite commencer mon voyage avec ces premières préférences.')
  }, [
    estConnecte,
    etat.questionnaire.termine,
    etat.questionnaire.transmis,
    etat.contexteInvite,
    envoyer,
  ])

  const valeur = useMemo(() => {
    const pretChat = estConnecte && etat.questionnaire.termine && etat.statut === 'accueil'
    const etatVisible = pretChat
      ? {
          ...etat,
          statut: 'conversation' as const,
          messages: [
            ...etat.messages,
            {
              id: 'questionnaire-termine',
              auteur: 'assistant' as const,
              texte:
                'Merci pour ces premières indications. Vous pouvez maintenant préciser votre voyage.',
            },
          ],
        }
      : etat

    return { ...etatVisible, ...actions, envoyer, estConnecte }
  }, [etat, actions, envoyer, estConnecte])

  return <ChatContext.Provider value={valeur}>{children}</ChatContext.Provider>
}
