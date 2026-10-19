import type { PageDuSite } from '@/features/pages/pageDuSite'

/** Contenus des pages servis par l'API simulée, repris des données de démo (api/src/DataFixtures/Donnees/Pages.php). */
/** Mention qui ouvre les modèles de pages légales tant que l'agence n'a pas fourni ses textes. */
const A_VALIDER =
  "<p><strong>Modèle à valider par l'agence</strong> : ce texte doit être relu et complété avant la mise en ligne.</p>"

export const PAGES_DE_DEMO: Record<string, PageDuSite> = {
  accueil: {
    slug: 'accueil',
    titre: 'Votre voyage sur mesure, proposé en quelques minutes',
    contenu: '<p>Dites-nous où vous rêvez d’aller.</p>',
    photos: ['/uploads/pages/accueil.jpg'],
    seoTitre: 'Agence de voyage sur mesure',
    seoDescription: 'Décrivez vos envies à notre assistant.',
  },
  'types-de-voyages': {
    slug: 'types-de-voyages',
    titre: 'Nos types de voyages',
    contenu:
      '<h2>Séjours</h2><p>Une destination, un hébergement choisi pour vous et des activités à la carte.</p>' +
      '<h2>Circuits</h2><p>Plusieurs étapes, un itinéraire pensé jour par jour.</p>' +
      '<h2>Groupes</h2><p>Amis, associations, scolaires.</p>' +
      '<h2>Voyages de noces</h2><p>Suites avec vue, villas avec piscine privée.</p>' +
      '<h2>Entreprise</h2><p>Séminaires, conventions et incentives.</p>',
    photos: ['sejour', 'circuit', 'groupe', 'noces', 'entreprise'].map(
      (p) => `/uploads/pages/${p}.jpg`,
    ),
    seoTitre: 'Séjours, circuits, groupes, voyages de noces et entreprise',
    seoDescription: 'Séjours, circuits, groupes, voyages de noces et séminaires.',
  },
  agence: {
    slug: 'agence',
    titre: "L'agence",
    contenu:
      '<p>Depuis plus de quinze ans, nos conseillers construisent des voyages sur mesure.</p>' +
      '<h2>Nos engagements</h2><ul><li>Une première proposition complète en quelques minutes.</li>' +
      '<li>Un conseiller référent qui confirme les prix.</li></ul>',
    photos: ['/uploads/pages/agence.jpg'],
    seoTitre: 'Notre agence et nos conseillers',
    seoDescription: 'Une équipe de conseillers spécialistes de chaque destination.',
  },
  contact: {
    slug: 'contact',
    titre: 'Contact',
    contenu:
      '<p>Le plus rapide : ouvrez l’assistant et décrivez votre projet.</p>' +
      '<h2>Horaires des conseillers</h2><p>Du lundi au vendredi de 9 h à 18 h 30.</p>' +
      '<h2>Adresse</h2><p>12 rue des Voyageurs, 75000 Paris.</p>',
    photos: ['/uploads/pages/contact.jpg'],
    seoTitre: "Contacter l'agence",
    seoDescription: 'Posez vos questions à notre assistant ou demandez à parler à un conseiller.',
  },
  'mentions-legales': {
    slug: 'mentions-legales',
    titre: 'Mentions légales',
    contenu:
      A_VALIDER +
      '<h2>Éditeur</h2><p>[Raison sociale], [forme juridique] au capital de [montant] €.</p>' +
      '<h2>Hébergement</h2><p>[Hébergeur], [adresse].</p>',
    photos: [],
    seoTitre: 'Mentions légales',
    seoDescription: "Éditeur du site, hébergement et immatriculation de l'agence.",
  },
  cgv: {
    slug: 'cgv',
    titre: 'Conditions générales de vente',
    contenu:
      A_VALIDER +
      '<h2>Frais de dossier</h2><p>Des frais de dossier de 100 à 200 € s’appliquent une fois par dossier.</p>',
    photos: [],
    seoTitre: 'Conditions générales de vente',
    seoDescription: 'Conditions de réservation, de paiement, de modification et d’annulation.',
  },
  confidentialite: {
    slug: 'confidentialite',
    titre: 'Politique de confidentialité et cookies',
    contenu:
      A_VALIDER +
      '<h2>Assistant IA</h2><p>L’assistant est une intelligence artificielle.</p>' +
      '<h2>Cookies</h2><p>[Liste des cookies et gestion du consentement.]</p>',
    photos: [],
    seoTitre: 'Confidentialité et cookies',
    seoDescription: 'Données collectées par l’assistant et le site, finalités et vos droits.',
  },
}
