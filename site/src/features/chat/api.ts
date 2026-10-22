import { z } from 'zod'
import type { TypeDeVoyage } from '@/data/catalogue'
import type { DestinationPublique } from '@/features/catalogue/catalogue'
import { ApiError } from '@/lib/api'
import type { Demande } from './demande'

/**
 * Appels du chatbot à l'API Symfony. Le site ne parle jamais à l'API Claude : POST /api/chat diffuse la
 * réponse de l'assistant en Server-Sent Events (voir le README, « Messages du chatbot »).
 */

const EN_TETE_JETON = 'X-Conversation-Token'

const conversationSchema = z.object({ jeton: z.string(), demande: z.number() })

/** Fiche demande renvoyée par l'outil enregistrer_exigences (montants en euros). */
export const ficheSchema = z.object({
  typeDeVoyage: z.enum(['sejour', 'circuit', 'groupe', 'noces', 'entreprise']).nullable(),
  lieuOuZone: z.string().nullable(),
  villeDepart: z.string().nullable(),
  dateDebut: z.string().nullable(),
  dateFin: z.string().nullable(),
  datesFlexibles: z.boolean(),
  nombreDeJours: z.number().nullable(),
  temperatureMin: z.number().nullable(),
  temperatureMax: z.number().nullable(),
  adultes: z.number(),
  enfants: z.number(),
  agesEnfants: z.array(z.number()),
  budgetEuros: z.number().nullable(),
  budgetParPersonne: z.boolean(),
  transportInclus: z.boolean(),
  envies: z.array(z.string()),
  contraintes: z.array(z.string()),
})

export type Fiche = z.infer<typeof ficheSchema>

const NIVEAU = z.enum(['essentiel', 'equilibre', 'confort'])

/** Piste retenue par la recherche (api/src/Chatbot/Piste.php), affichée en carte sous le message de l'assistant. */
export const pisteSchema = z.object({
  destination: z.object({ slug: z.string(), ville: z.string(), pays: z.string().nullable() }),
  dateDebut: z.string(),
  dateFin: z.string(),
  nuits: z.number(),
  voyageurs: z.number(),
  temperatureMoyenne: z.number().nullable(),
  villeDepart: z.string().nullable(),
  tempsDeTrajetMinutes: z.number().nullable(),
  envies: z.array(z.string()),
  niveauConseille: NIVEAU,
  niveaux: z.record(
    NIVEAU,
    z.object({
      logements: z.array(z.string()),
      activites: z.array(z.string()),
      transport: z.string(),
      totalEuros: z.number(),
      parPersonneEuros: z.number(),
    }),
  ),
  options: z.array(
    z.object({
      id: z.number(),
      nom: z.string(),
      prixEuros: z.number(),
      parPersonneEuros: z.number(),
    }),
  ),
  budgetInsuffisant: z.boolean(),
  arbitrages: z.array(z.object({ libelle: z.string(), totalEuros: z.number() })),
  avertissement: z.string(),
})
export type Piste = z.infer<typeof pisteSchema>

/** Récapitulatif détaillé enregistré (AgentRedaction::carte) : son lien et, s'il reste à choisir, les options. */
export const recapitulatifSchema = z.object({
  texte: z.string(),
  jeton: z.string(),
  version: z.number(),
  ville: z.string().nullable(),
  jours: z.number(),
  niveau: NIVEAU,
  totalCentimes: z.number(),
  parPersonneCentimes: z.number(),
  voyageurs: z.number(),
  options: z.array(z.object({ id: z.number(), nom: z.string(), prixCentimes: z.number() })),
})
export type RecapitulatifChat = z.infer<typeof recapitulatifSchema>

/** Carte gardée avec un message de la conversation (messages de l'API). */
export const carteSchema = z.discriminatedUnion('type', [
  pisteSchema.extend({ type: z.literal('piste') }),
  recapitulatifSchema.extend({ type: z.literal('recapitulatif') }),
])

const evenementSchema = z.discriminatedUnion('type', [
  z.object({ type: z.literal('texte'), texte: z.string() }),
  z.object({ type: z.literal('outil'), nom: z.string() }),
  z.object({
    type: z.literal('demande'),
    fiche: ficheSchema.nullable(),
    recapitulatifConfirme: z.boolean(),
    /** Langue du client, reconnue par l'assistant (US15). */
    langue: z.enum(['fr', 'en']).default('fr'),
  }),
  pisteSchema.extend({ type: z.literal('piste') }),
  recapitulatifSchema.extend({ type: z.literal('recapitulatif') }),
  z.object({ type: z.literal('erreur'), erreur: z.string() }),
  /** terminee : la conversation n'attend plus rien du client (récapitulatif complet, conseiller). */
  z.object({ type: z.literal('fin'), terminee: z.boolean().default(false) }),
])

export type EvenementChat = z.infer<typeof evenementSchema>

/** Message d'erreur de l'API ({"erreur": "…"}), déjà rédigé pour le client. */
async function erreurApi(reponse: Response): Promise<ApiError> {
  const corps: unknown = await reponse.json().catch(() => null)
  const message =
    corps && typeof corps === 'object' && 'erreur' in corps && typeof corps.erreur === 'string'
      ? corps.erreur
      : 'L’assistant est momentanément indisponible. Réessayez dans quelques instants.'
  return new ApiError(reponse.status, message)
}

/** Ouvre une conversation : le jeton renvoyé authentifie les messages suivants. */
export async function ouvrirConversation(
  preferences?: string[],
  destination?: string,
  signal?: AbortSignal,
): Promise<string> {
  const reponse = await fetch('/api/chat/conversations', {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: preferences || destination ? JSON.stringify({ preferences, destination }) : undefined,
    signal,
  })
  if (!reponse.ok) throw await erreurApi(reponse)
  return conversationSchema.parse(await reponse.json()).jeton
}

/**
 * Découpe un flux Server-Sent Events en événements. Les morceaux reçus peuvent couper un événement
 * n'importe où, y compris au milieu d'un caractère : le décodeur garde la fin incomplète.
 */
export async function* lireEvenements(
  flux: ReadableStream<Uint8Array>,
): AsyncGenerator<EvenementChat> {
  const lecteur = flux.getReader()
  const decodeur = new TextDecoder()
  let tampon = ''

  for (;;) {
    const { done, value } = await lecteur.read()
    tampon += decodeur.decode(value, { stream: !done }).replace(/\r\n/g, '\n')

    let fin = tampon.indexOf('\n\n')
    while (fin !== -1) {
      const evenement = analyser(tampon.slice(0, fin))
      tampon = tampon.slice(fin + 2)
      if (evenement) yield evenement
      fin = tampon.indexOf('\n\n')
    }
    if (done) return
  }
}

function analyser(bloc: string): EvenementChat | undefined {
  let type: string | undefined
  let donnees = ''
  for (const ligne of bloc.split('\n')) {
    if (ligne.startsWith('event:')) type = ligne.slice(6).trim()
    else if (ligne.startsWith('data:')) donnees += ligne.slice(5).trim()
  }
  if (!type || !donnees) return undefined
  try {
    const resultat = evenementSchema.safeParse({ ...JSON.parse(donnees), type })
    return resultat.success ? resultat.data : undefined
  } catch {
    // Événement illisible : ignoré, la suite du flux reste utilisable.
    return undefined
  }
}

/** Envoie un message du client et rend la réponse de l'assistant événement par événement. */
export async function* envoyerMessage(
  jeton: string,
  message: string,
  signal?: AbortSignal,
): AsyncGenerator<EvenementChat> {
  const reponse = await fetch('/api/chat', {
    method: 'POST',
    headers: {
      Accept: 'text/event-stream',
      'Content-Type': 'application/json',
      [EN_TETE_JETON]: jeton,
    },
    body: JSON.stringify({ message }),
    signal,
  })
  if (!reponse.ok || !reponse.body) throw await erreurApi(reponse)
  yield* lireEvenements(reponse.body)
}

const sansAccents = (texte: string) =>
  texte
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase()

/** Mots entiers, sans accents ni ponctuation : « Costa-Rica » → « costa rica ». */
const mots = (texte: string) =>
  ` ${sansAccents(texte)
    .replace(/[^a-z0-9]+/g, ' ')
    .trim()} `

/** Régime, allergie, mobilité, santé : à signaler au conseiller dans le récapitulatif. */
const A_SIGNALER = /allerg|regime|vegetarien|vegan|sans gluten|mobilite|fauteuil|handicap|sante/

/**
 * Traduit la fiche de l'API dans la fiche du site, lue par le récapitulatif. Le lieu cité est rapproché des
 * destinations publiées du catalogue (GET /api/public/destinations) ; sans catalogue, il reste une zone libre.
 */
export function ficheVersDemande(
  fiche: Fiche,
  precedente: Demande,
  catalogue: DestinationPublique[],
): Demande {
  // Mots entiers : « Rome » ne doit pas désigner un voyage « romantique ».
  const lieu = fiche.lieuOuZone ? mots(fiche.lieuOuZone) : undefined
  const destination = lieu
    ? catalogue.find((d) => lieu.includes(mots(d.ville)) || lieu.includes(mots(d.slug)))
    : undefined
  const contraintes = fiche.contraintes.filter((c) => !/^(aucune?|rien|non)$/i.test(c.trim()))

  return {
    ...precedente,
    type: (fiche.typeDeVoyage ?? undefined) as TypeDeVoyage | undefined,
    destination: destination && { slug: destination.slug, nom: destination.nom },
    // Le lieu tel que le client l'a dit, s'il ne désigne pas une destination du catalogue.
    zone: destination ? undefined : (fiche.lieuOuZone ?? undefined),
    mois: fiche.dateDebut ? Number(fiche.dateDebut.slice(5, 7)) - 1 : undefined,
    datesFlexibles: fiche.datesFlexibles,
    jours: fiche.nombreDeJours ?? undefined,
    temperatureMin: fiche.temperatureMin ?? undefined,
    adultes: fiche.adultes,
    enfants: fiche.enfants,
    budget: fiche.budgetEuros ?? undefined,
    budgetParPersonne: fiche.budgetParPersonne,
    depart: fiche.villeDepart ?? undefined,
    envies: fiche.envies,
    // « Aucune » est une réponse : le critère est renseigné.
    contraintes:
      fiche.contraintes.length > 0 && contraintes.length === 0 ? ['aucune'] : contraintes,
    contraintesSignalees: contraintes.filter((c) => A_SIGNALER.test(sansAccents(c))),
  }
}
