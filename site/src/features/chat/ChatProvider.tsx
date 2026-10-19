import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { DESTINATIONS, formatEuros } from '@/data/catalogue'
import { genererProposition } from '@/features/proposition/proposition'
import { enregistrerProposition, trouverProposition } from '@/features/proposition/store'
import {
  choisirAmbiance as choisirAmbianceAssistant,
  etapesPreparation,
  MESSAGE_ACCUEIL,
  nouvelId,
  repondre,
  type Conversation,
  type Message,
  type Reponse,
} from './assistant'
import { ChatContext, type EtatChat } from './chatContext'
import { demandeVide, extraire } from './demande'

const CLE = 'chat'
/** Délai simulé de réponse de l'assistant. */
const DELAI = 700

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

const CONSEILLER = /conseiller|humain|quelqu.un|appeler|telephone|téléphone/i

/**
 * État du chatbot partagé par tout le site : la conversation commencée sur l'accueil continue dans la
 * fenêtre ouverte depuis les autres pages.
 */
export function ChatProvider({ children }: { children: ReactNode }) {
  const [etat, setEtat] = useState<EtatChat>(restaurer)
  const timers = useRef<number[]>([])

  useEffect(() => {
    const { statut, messages, conversation, propositionId } = etat
    try {
      sessionStorage.setItem(CLE, JSON.stringify({ statut, messages, conversation, propositionId }))
    } catch {
      // Sans stockage, la conversation dure le temps de la page.
    }
  }, [etat])

  useEffect(() => {
    const enCours = timers.current
    return () => enCours.forEach((t) => window.clearTimeout(t))
  }, [])

  const plusTard = useCallback((delai: number, action: () => void) => {
    timers.current.push(window.setTimeout(action, delai))
  }, [])

  const ajouter = useCallback((messages: Message[], maj: Partial<EtatChat> = {}) => {
    setEtat((e) => ({ ...e, ...maj, messages: [...e.messages, ...messages] }))
  }, [])

  /** Agents Recherche et Chiffrage : étapes affichées une à une, puis la proposition. */
  const preparer = useCallback(
    (conversation: Conversation) => {
      const labels = etapesPreparation(conversation.demande)
      const idPreparation = nouvelId()
      const etapes = (fait: number) =>
        labels.map((label, i) => ({
          label,
          etat:
            i < fait
              ? ('fait' as const)
              : i === fait
                ? ('en-cours' as const)
                : ('attente' as const),
        }))
      ajouter([
        {
          id: idPreparation,
          auteur: 'assistant',
          type: 'preparation',
          texte:
            'J’ai tout ce qu’il me faut : vérifiez le récapitulatif. Je prépare votre proposition chiffrée.',
          etapes: etapes(0),
        },
      ])
      labels.forEach((_, i) =>
        plusTard(DELAI * (i + 1), () =>
          setEtat((e) => ({
            ...e,
            messages: e.messages.map((m) =>
              m.id === idPreparation && 'type' in m && m.type === 'preparation'
                ? { ...m, etapes: etapes(i + 1) }
                : m,
            ),
          })),
        ),
      )
      plusTard(DELAI * (labels.length + 1), () => {
        const d = conversation.demande
        const slug = d.destination ?? DESTINATIONS[0].slug
        const proposition = genererProposition(d, slug)
        enregistrerProposition(proposition)
        const equilibre = proposition.budgets.equilibre
        ajouter(
          [
            {
              id: nouvelId(),
              auteur: 'assistant',
              type: 'proposition',
              texte: `Voici votre première proposition : ${proposition.titre}, ${formatEuros(equilibre.total)} en niveau équilibré. Vous pouvez me demander de la modifier.`,
              propositionId: proposition.id,
            },
          ],
          { propositionId: proposition.id, reflexion: false },
        )
      })
    },
    [ajouter, plusTard],
  )

  /** Agent Modification : nouvelle version de la proposition avec la demande mise à jour. */
  const modifier = useCallback((e: EtatChat, texte: string) => {
    const precedente = trouverProposition(e.propositionId!)
    const demande = extraire(texte, e.conversation.demande)
    const slug = demande.destination ?? precedente?.destination ?? DESTINATIONS[0].slug
    const proposition = {
      ...genererProposition(demande, slug),
      version: (precedente?.version ?? 1) + 1,
    }
    enregistrerProposition(proposition)
    const ecart = precedente
      ? proposition.budgets.equilibre.total - precedente.budgets.equilibre.total
      : 0
    const resumeEcart =
      ecart === 0
        ? 'le budget ne change pas'
        : `le budget équilibré ${ecart > 0 ? 'augmente' : 'baisse'} de ${formatEuros(Math.abs(ecart))}`
    return {
      conversation: { ...e.conversation, demande },
      message: {
        id: nouvelId(),
        auteur: 'assistant' as const,
        type: 'proposition' as const,
        texte: `C’est fait : voici la version ${proposition.version}, ${resumeEcart}.`,
        propositionId: proposition.id,
      },
      propositionId: proposition.id,
    }
  }, [])

  const appliquer = useCallback(
    (reponse: Reponse) => {
      plusTard(DELAI, () => {
        setEtat((e) => ({
          ...e,
          conversation: reponse.conversation,
          messages: [...e.messages, ...reponse.messages],
          reflexion: reponse.complete,
        }))
        if (reponse.complete) preparer(reponse.conversation)
      })
    },
    [plusTard, preparer],
  )

  const envoyer = useCallback(
    (texte: string) => {
      const propre = texte.trim()
      if (!propre) return
      const e = etat
      const client: Message = { id: nouvelId(), auteur: 'client', texte: propre }
      setEtat((x) => ({
        ...x,
        statut: 'conversation',
        brouillon: '',
        reflexion: true,
        messages: [...x.messages, client],
      }))

      if (CONSEILLER.test(propre)) {
        plusTard(DELAI, () =>
          ajouter(
            [
              {
                id: nouvelId(),
                auteur: 'assistant',
                texte:
                  'Je transmets votre demande et notre conversation à un conseiller de l’agence. Il vous recontacte sous 2 jours ouvrés, du lundi au samedi.',
              },
            ],
            { reflexion: false },
          ),
        )
        return
      }

      if (e.propositionId) {
        const resultat = modifier(e, propre)
        plusTard(DELAI, () =>
          ajouter([resultat.message], {
            conversation: resultat.conversation,
            propositionId: resultat.propositionId,
            reflexion: false,
          }),
        )
        return
      }

      appliquer(repondre(e.conversation, propre))
    },
    [etat, ajouter, appliquer, modifier, plusTard],
  )

  const choisirAmbiance = useCallback(
    (slug: string) => {
      const e = etat
      const dest = DESTINATIONS.find((d) => d.slug === slug)
      if (!dest || e.reflexion || e.propositionId) return
      setEtat((x) => ({
        ...x,
        reflexion: true,
        messages: [
          ...x.messages.map((m) =>
            'type' in m && m.type === 'ambiances' && !m.choix ? { ...m, choix: slug } : m,
          ),
          { id: nouvelId(), auteur: 'client', texte: `${dest.pointsForts[0]}, à ${dest.ville} !` },
        ],
      }))
      appliquer(choisirAmbianceAssistant(e.conversation, slug))
    },
    [etat, appliquer],
  )

  const valeur = useMemo(
    () => ({
      ...etat,
      envoyer,
      choisirAmbiance,
      ouvrir: (brouillon?: string) =>
        setEtat((e) => ({ ...e, ouvert: true, brouillon: brouillon ?? e.brouillon })),
      fermer: () => setEtat((e) => ({ ...e, ouvert: false })),
      setBrouillon: (brouillon: string) => setEtat((e) => ({ ...e, brouillon })),
      recommencer: () => {
        timers.current.forEach((t) => window.clearTimeout(t))
        timers.current = []
        setEtat((e) => ({ ...etatInitial(), ouvert: e.ouvert }))
      },
    }),
    [etat, envoyer, choisirAmbiance],
  )

  return <ChatContext.Provider value={valeur}>{children}</ChatContext.Provider>
}
