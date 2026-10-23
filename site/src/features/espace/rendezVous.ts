/**
 * Prise de rendez-vous avec un conseiller (« Modifier le voyage »). Calendly en version gratuite pour l'instant :
 * la page de réservation de l'agence se règle dans VITE_CALENDLY_URL (site/.env.local), sans toucher au code.
 * Aucune donnée du client n'est ajoutée à l'adresse : il saisit ses coordonnées sur Calendly.
 */
export function urlRendezVous(): string | null {
  return (import.meta.env.VITE_CALENDLY_URL as string | undefined) || null
}
