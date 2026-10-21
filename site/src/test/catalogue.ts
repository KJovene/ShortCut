import { TYPES_DE_VOYAGE, type TypeDeVoyage } from '@/data/catalogue'

/**
 * Catalogue de démo des tests, recopié des premières fixtures de l'API (api/src/DataFixtures/Donnees). Le site
 * lit le vrai catalogue dans l'API (GET /api/public/destinations) : ces données ne servent qu'à l'API simulée
 * (src/test/api.ts) et ne sont jamais importées par le code de l'application.
 */

export interface Destination {
  slug: string
  ville: string
  pays: string
  region: string
  /** Nom court affiché sur la carte et les cartes de voyage. */
  nom: string
  types: TypeDeVoyage[]
  description: string
  accroche: string
  pointsForts: string[]
  /** Minutes de vol depuis chaque ville de départ. */
  tempsDeTrajet: Record<string, number>
  /** Températures moyennes de janvier à décembre, en °C. */
  temperatures: number[]
  /** Pluie de janvier à décembre, en mm. */
  pluie: number[]
  /** Mois conseillé (index 0 à 11), mis en avant sur la carte. */
  moisIdeal: number
  /** Coordonnées pour placer l'épingle sur la carte du monde. */
  lon: number
  lat: number
  /** Fourchette d'un vol aller-retour depuis Paris, par personne. */
  volParis: [number, number]
  /** Fourchette des transferts aéroport ↔ hébergement, par personne. */
  transfert: [number, number]
  photo: string
  alt: string
  miseEnAvant: boolean
}

export interface Prestation {
  nom: string
  destination: string
  categorie: string
  capacite: number
  /** Logement : prix par nuit pour le logement entier. Activité : prix par personne. */
  prix: number
  photo: string
  envies: string[]
  accessible?: boolean
  /** Activité : durée en minutes. */
  duree?: number
}

const photoDestination = (slug: string) => `/uploads/destinations/${slug}.jpg`
const photoPrestation = (nom: string) => `/uploads/prestations/${nom}.jpg`

export const DESTINATIONS: Destination[] = [
  {
    slug: 'lisbonne',
    ville: 'Lisbonne',
    pays: 'Portugal',
    region: 'Lisbonne et vallée du Tage',
    nom: 'Lisbonne, Portugal',
    types: ['sejour', 'groupe', 'entreprise'],
    description:
      "Capitale lumineuse sur les collines du Tage : ruelles de l'Alfama, tramways jaunes, miradouros au coucher du soleil et cuisine généreuse. Idéale pour un long week-end, un voyage de groupe ou un séminaire.",
    accroche: 'Ruelles de l’Alfama, tramways jaunes et pastéis de nata, à 2 h 30 de Paris.',
    pointsForts: [
      'Quartiers historiques à pied',
      'Gastronomie et pastéis de nata',
      'Plages à 30 minutes',
      'Vols courts depuis la France',
    ],
    tempsDeTrajet: { Paris: 150, Lyon: 140, Bordeaux: 115 },
    temperatures: [12, 13, 15, 17, 19, 22, 24, 24, 23, 19, 15, 13],
    pluie: [100, 90, 60, 65, 50, 15, 5, 5, 30, 90, 110, 115],
    moisIdeal: 5,
    lon: -9.1,
    lat: 38.7,
    volParis: [120, 320],
    transfert: [20, 45],
    photo: photoDestination('lisbonne'),
    alt: 'Le pont du 25-Avril au coucher du soleil sur le Tage',
    miseEnAvant: true,
  },
  {
    slug: 'marrakech',
    ville: 'Marrakech',
    pays: 'Maroc',
    region: 'Marrakech-Safi',
    nom: 'Marrakech, Maroc',
    types: ['sejour', 'groupe', 'entreprise'],
    description:
      "La ville rouge : médina classée, souks, riads à patio et jardins, avec l'Atlas en toile de fond. Une destination soleil toute l'année, très demandée pour les groupes et les séminaires.",
    accroche: 'Riads à patio, souks et hammams, avec l’Atlas en toile de fond.',
    pointsForts: [
      'Riads de charme',
      'Souks et place Jemaa el-Fna',
      "Excursions dans l'Atlas",
      'Hammams et spas',
    ],
    tempsDeTrajet: { Paris: 210, Lyon: 195, Bordeaux: 180 },
    temperatures: [12, 14, 16, 18, 22, 25, 29, 29, 25, 21, 16, 13],
    pluie: [32, 38, 38, 39, 24, 7, 2, 3, 6, 24, 41, 31],
    moisIdeal: 3,
    lon: -8,
    lat: 31.6,
    volParis: [150, 380],
    transfert: [15, 40],
    photo: photoDestination('marrakech'),
    alt: 'Le minaret de la Koutoubia sous un ciel bleu',
    miseEnAvant: true,
  },
  {
    slug: 'santorin',
    ville: 'Santorin',
    pays: 'Grèce',
    region: 'Cyclades',
    nom: 'Santorin, Grèce',
    types: ['noces', 'sejour'],
    description:
      'Villages blancs accrochés à la caldeira, couchers de soleil sur la mer Égée et vins volcaniques : la destination romantique par excellence pour un voyage de noces.',
    accroche: 'Villages blancs sur la caldeira et couchers de soleil à Oia, rien que pour deux.',
    pointsForts: [
      'Couchers de soleil à Oia',
      'Suites avec piscine privée',
      'Croisières dans la caldeira',
      'Vignobles volcaniques',
    ],
    tempsDeTrajet: { Paris: 240, Lyon: 300, Bordeaux: 330 },
    temperatures: [12, 12, 14, 17, 21, 25, 27, 27, 24, 20, 17, 14],
    pluie: [70, 55, 45, 20, 10, 2, 1, 1, 10, 40, 55, 75],
    moisIdeal: 5,
    lon: 25.4,
    lat: 36.4,
    volParis: [220, 520],
    transfert: [25, 60],
    photo: photoDestination('santorin'),
    alt: 'Maisons blanches et dômes bleus d’Oia face à la mer',
    miseEnAvant: true,
  },
  {
    slug: 'tenerife',
    ville: 'Tenerife',
    pays: 'Espagne',
    region: 'Îles Canaries',
    nom: 'Tenerife, Canaries',
    types: ['sejour', 'groupe'],
    description:
      "L'île du printemps éternel : plages au sud, volcan Teide classé à l'UNESCO, baleines et dauphins toute l'année. Le soleil d'hiver à quatre heures de Paris, parfait pour les familles.",
    accroche: 'Plus de 20 °C en plein hiver, le Teide et les baleines, en famille.',
    pointsForts: [
      'Plus de 20 °C en plein hiver',
      'Parc national du Teide',
      'Observation des cétacés',
      'Hôtels familiaux et accessibles',
    ],
    tempsDeTrajet: { Paris: 280, Lyon: 300, Bordeaux: 270 },
    temperatures: [19, 19, 20, 20, 21, 23, 25, 26, 26, 24, 22, 20],
    pluie: [30, 30, 25, 10, 3, 1, 0, 1, 5, 20, 40, 40],
    moisIdeal: 1,
    lon: -16.6,
    lat: 28.3,
    volParis: [200, 480],
    transfert: [20, 50],
    photo: photoDestination('tenerife'),
    alt: 'Le volcan Teide au-dessus des nuages',
    miseEnAvant: false,
  },
  {
    slug: 'rome',
    ville: 'Rome',
    pays: 'Italie',
    region: 'Latium',
    nom: 'Rome, Italie',
    types: ['groupe', 'entreprise', 'sejour'],
    description:
      "Trois mille ans d'histoire à ciel ouvert : Colisée, forums, places baroques et trattorias du Trastevere. Une valeur sûre pour les voyages de groupe, scolaires ou d'entreprise.",
    accroche: 'Colisée, places baroques et trattorias, idéale pour les groupes.',
    pointsForts: [
      'Colisée et Forum romain',
      'Cuisine italienne',
      'Hébergements pour groupes',
      'Centre de congrès',
    ],
    tempsDeTrajet: { Paris: 125, Lyon: 85, Bordeaux: 145 },
    temperatures: [8, 9, 12, 15, 19, 23, 26, 26, 22, 18, 13, 9],
    pluie: [70, 75, 60, 70, 50, 30, 20, 30, 70, 110, 110, 85],
    moisIdeal: 4,
    lon: 12.5,
    lat: 41.9,
    volParis: [100, 300],
    transfert: [25, 55],
    photo: photoDestination('rome'),
    alt: 'Le Colisée de Rome sous un ciel clair',
    miseEnAvant: false,
  },
  {
    slug: 'islande',
    ville: 'Reykjavik',
    pays: 'Islande',
    region: "Sud et Nord de l'Islande",
    nom: 'Islande',
    types: ['circuit'],
    description:
      "Cascades, glaciers, plages de sable noir et sources chaudes : l'Islande se découvre en circuit, de Reykjavik à Vík puis jusqu'à Akureyri. Saison des circuits de mai à septembre.",
    accroche: 'Cascades, glaciers et sources chaudes, en circuit de mai à septembre.',
    pointsForts: [
      'Circuit de 7 à 10 jours',
      'Cascades et glaciers',
      'Baignade dans les sources chaudes',
      'Observation des baleines',
    ],
    tempsDeTrajet: { Paris: 205, Lyon: 240, Bordeaux: 250 },
    temperatures: [0, 0, 1, 3, 7, 10, 12, 11, 8, 5, 2, 0],
    pluie: [90, 85, 85, 60, 45, 50, 50, 60, 70, 90, 85, 90],
    moisIdeal: 6,
    lon: -21.9,
    lat: 64.1,
    volParis: [220, 550],
    transfert: [40, 90],
    photo: photoDestination('islande'),
    alt: 'Cascade écumante au milieu des falaises vertes',
    miseEnAvant: false,
  },
  {
    slug: 'bali',
    ville: 'Ubud',
    pays: 'Indonésie',
    region: 'Bali',
    nom: 'Bali, Indonésie',
    types: ['noces', 'sejour'],
    description:
      "Rizières en terrasses, temples, plages de surf et villas avec piscine : Bali marie culture et détente. Un classique des voyages de noces, plus sec d'avril à octobre.",
    accroche: 'Rizières, temples et villa avec piscine privée, un classique des noces.',
    pointsForts: [
      'Villas avec piscine privée',
      'Temples et rizières',
      'Surf à Canggu',
      'Massages balinais',
    ],
    tempsDeTrajet: { Paris: 1080, Lyon: 1140, Bordeaux: 1170 },
    temperatures: [27, 27, 27, 28, 28, 27, 27, 27, 27, 28, 28, 27],
    pluie: [350, 300, 240, 100, 90, 70, 60, 40, 50, 110, 180, 290],
    moisIdeal: 6,
    lon: 115.3,
    lat: -8.5,
    volParis: [750, 1400],
    transfert: [25, 60],
    photo: photoDestination('bali'),
    alt: 'Le temple d’Uluwatu au bord de la falaise',
    miseEnAvant: true,
  },
  {
    slug: 'costa-rica',
    ville: 'La Fortuna',
    pays: 'Costa Rica',
    region: 'Alajuela et Puntarenas',
    nom: 'Costa Rica',
    types: ['circuit'],
    description:
      "Volcan Arenal, forêts de nuages de Monteverde et côte sauvage de la péninsule d'Osa : un circuit nature au milieu d'une faune exceptionnelle. Saison sèche de décembre à avril.",
    accroche: 'Volcans, forêts de nuages et faune exceptionnelle, en circuit nature.',
    pointsForts: [
      'Circuit nature de 10 à 14 jours',
      'Volcans et sources chaudes',
      'Faune et forêts tropicales',
      'Écolodges',
    ],
    tempsDeTrajet: { Paris: 660, Lyon: 780, Bordeaux: 780 },
    temperatures: [24, 25, 26, 27, 27, 26, 26, 26, 26, 25, 25, 24],
    pluie: [150, 90, 60, 80, 250, 300, 280, 300, 330, 350, 250, 200],
    moisIdeal: 1,
    lon: -84.6,
    lat: 10.5,
    volParis: [650, 1300],
    transfert: [60, 140],
    photo: photoDestination('costa-rica'),
    alt: 'La cascade de La Fortuna dans la forêt tropicale',
    miseEnAvant: false,
  },
]

export const LOGEMENTS: Prestation[] = [
  {
    nom: 'Hôtel Alfama Vista, chambre double',
    destination: 'lisbonne',
    categorie: 'Hôtel 3 étoiles',
    capacite: 2,
    prix: 110,
    accessible: true,
    envies: ['culture', 'gastronomie'],
    photo: photoPrestation('hotel-chambre'),
  },
  {
    nom: 'Appartement Bairro Alto',
    destination: 'lisbonne',
    categorie: 'Appartement',
    capacite: 4,
    prix: 140,
    envies: ['culture', 'famille'],
    photo: photoPrestation('appartement'),
  },
  {
    nom: 'Tejo Palace, suite vue fleuve',
    destination: 'lisbonne',
    categorie: 'Hôtel 5 étoiles',
    capacite: 2,
    prix: 290,
    accessible: true,
    envies: ['romantique', 'détente'],
    photo: photoPrestation('hotel-piscine'),
  },
  {
    nom: 'Riad Lalla, chambre double',
    destination: 'marrakech',
    categorie: 'Riad de charme',
    capacite: 2,
    prix: 95,
    envies: ['romantique', 'culture'],
    photo: photoPrestation('riad'),
  },
  {
    nom: 'Kasbah Atlas, chambre familiale',
    destination: 'marrakech',
    categorie: 'Kasbah',
    capacite: 4,
    prix: 160,
    envies: ['nature', 'famille'],
    photo: photoPrestation('riad'),
  },
  {
    nom: 'Riad Dar Yasmine, riad entier',
    destination: 'marrakech',
    categorie: 'Riad privatisé',
    capacite: 12,
    prix: 650,
    envies: ['culture', 'détente', 'famille'],
    photo: photoPrestation('riad'),
  },
  {
    nom: 'Hôtel Fira Blue, chambre triple',
    destination: 'santorin',
    categorie: 'Hôtel 3 étoiles',
    capacite: 3,
    prix: 150,
    envies: ['plage', 'culture'],
    photo: photoPrestation('hotel-chambre'),
  },
  {
    nom: 'Suite Caldera Oia, piscine privée',
    destination: 'santorin',
    categorie: 'Suite de luxe',
    capacite: 2,
    prix: 420,
    envies: ['romantique', 'détente'],
    photo: photoPrestation('villa'),
  },
  {
    nom: 'Villa Imerovigli',
    destination: 'santorin',
    categorie: 'Villa',
    capacite: 6,
    prix: 560,
    envies: ['famille', 'détente'],
    photo: photoPrestation('villa'),
  },
  {
    nom: 'Hôtel Teide Nature',
    destination: 'tenerife',
    categorie: 'Hôtel 3 étoiles',
    capacite: 2,
    prix: 95,
    envies: ['nature', 'sport'],
    photo: photoPrestation('hotel-chambre'),
  },
  {
    nom: 'Apartamentos Los Cristianos',
    destination: 'tenerife',
    categorie: 'Appartement',
    capacite: 4,
    prix: 115,
    envies: ['plage', 'famille'],
    photo: photoPrestation('appartement'),
  },
  {
    nom: 'Canaries Beach Resort, chambre familiale',
    destination: 'tenerife',
    categorie: 'Hôtel 4 étoiles',
    capacite: 4,
    prix: 190,
    accessible: true,
    envies: ['plage', 'famille', 'détente'],
    photo: photoPrestation('hotel-piscine'),
  },
  {
    nom: 'Hôtel Colosseo, chambre double',
    destination: 'rome',
    categorie: 'Hôtel 3 étoiles',
    capacite: 2,
    prix: 130,
    accessible: true,
    envies: ['culture'],
    photo: photoPrestation('hotel-chambre'),
  },
  {
    nom: 'Appartement Trastevere',
    destination: 'rome',
    categorie: 'Appartement',
    capacite: 5,
    prix: 170,
    envies: ['culture', 'gastronomie', 'famille'],
    photo: photoPrestation('appartement'),
  },
  {
    nom: 'Guesthouse Reykjavik Centre',
    destination: 'islande',
    categorie: "Maison d'hôtes",
    capacite: 2,
    prix: 150,
    envies: ['culture', 'nature'],
    photo: photoPrestation('guesthouse'),
  },
  {
    nom: 'Hôtel Vík Glacier',
    destination: 'islande',
    categorie: 'Hôtel 3 étoiles',
    capacite: 2,
    prix: 190,
    accessible: true,
    envies: ['nature'],
    photo: photoPrestation('hotel-chambre'),
  },
  {
    nom: "Cabane d'Akureyri",
    destination: 'islande',
    categorie: 'Chalet',
    capacite: 4,
    prix: 230,
    envies: ['nature', 'romantique'],
    photo: photoPrestation('guesthouse'),
  },
  {
    nom: 'Bungalow Canggu Surf',
    destination: 'bali',
    categorie: 'Bungalow',
    capacite: 2,
    prix: 70,
    envies: ['sport', 'plage'],
    photo: photoPrestation('ecolodge'),
  },
  {
    nom: 'Seminyak Beach Resort',
    destination: 'bali',
    categorie: 'Hôtel 4 étoiles',
    capacite: 3,
    prix: 170,
    accessible: true,
    envies: ['plage', 'détente'],
    photo: photoPrestation('hotel-piscine'),
  },
  {
    nom: 'Villa Ubud Rizières, piscine privée',
    destination: 'bali',
    categorie: 'Villa de luxe',
    capacite: 2,
    prix: 260,
    envies: ['romantique', 'détente', 'nature'],
    photo: photoPrestation('villa-bali'),
  },
  {
    nom: 'Ecolodge Monteverde',
    destination: 'costa-rica',
    categorie: 'Écolodge',
    capacite: 2,
    prix: 120,
    envies: ['nature'],
    photo: photoPrestation('ecolodge'),
  },
  {
    nom: 'Cabinas Drake Bay',
    destination: 'costa-rica',
    categorie: 'Bungalow',
    capacite: 4,
    prix: 140,
    envies: ['nature', 'plage', 'aventure'],
    photo: photoPrestation('ecolodge'),
  },
  {
    nom: 'Lodge Arenal Volcán',
    destination: 'costa-rica',
    categorie: 'Lodge',
    capacite: 3,
    prix: 160,
    accessible: true,
    envies: ['nature', 'détente'],
    photo: photoPrestation('ecolodge'),
  },
]

export const ACTIVITES: Prestation[] = [
  {
    nom: "Visite guidée de l'Alfama et du tram 28",
    destination: 'lisbonne',
    categorie: 'Visite guidée',
    capacite: 20,
    prix: 35,
    duree: 180,
    envies: ['culture'],
    photo: photoPrestation('visite-guidee'),
  },
  {
    nom: 'Atelier pastéis de nata',
    destination: 'lisbonne',
    categorie: 'Atelier culinaire',
    capacite: 12,
    prix: 55,
    duree: 120,
    accessible: true,
    envies: ['gastronomie', 'famille'],
    photo: photoPrestation('cuisine'),
  },
  {
    nom: 'Visite guidée de la médina et des souks',
    destination: 'marrakech',
    categorie: 'Visite guidée',
    capacite: 25,
    prix: 30,
    duree: 210,
    envies: ['culture'],
    photo: photoPrestation('visite-guidee'),
  },
  {
    nom: 'Cours de cuisine marocaine et tajine',
    destination: 'marrakech',
    categorie: 'Atelier culinaire',
    capacite: 16,
    prix: 45,
    duree: 240,
    accessible: true,
    envies: ['gastronomie'],
    photo: photoPrestation('cuisine'),
  },
  {
    nom: 'Hammam traditionnel et massage',
    destination: 'marrakech',
    categorie: 'Bien-être',
    capacite: 2,
    prix: 70,
    duree: 120,
    envies: ['détente', 'romantique'],
    photo: photoPrestation('spa'),
  },
  {
    nom: 'Croisière en catamaran dans la caldeira',
    destination: 'santorin',
    categorie: 'Sortie en mer',
    capacite: 30,
    prix: 120,
    duree: 300,
    envies: ['romantique', 'plage'],
    photo: photoPrestation('voilier'),
  },
  {
    nom: 'Dégustation de vins volcaniques',
    destination: 'santorin',
    categorie: 'Dégustation',
    capacite: 14,
    prix: 60,
    duree: 150,
    envies: ['gastronomie', 'romantique'],
    photo: photoPrestation('cuisine'),
  },
  {
    nom: 'Observation des baleines et dauphins',
    destination: 'tenerife',
    categorie: 'Sortie en mer',
    capacite: 40,
    prix: 45,
    duree: 180,
    accessible: true,
    envies: ['nature', 'famille'],
    photo: photoPrestation('baleines'),
  },
  {
    nom: 'Randonnée guidée au Teide',
    destination: 'tenerife',
    categorie: 'Randonnée',
    capacite: 15,
    prix: 65,
    duree: 360,
    envies: ['nature', 'sport'],
    photo: photoPrestation('randonnee'),
  },
  {
    nom: 'Baptême de plongée',
    destination: 'tenerife',
    categorie: 'Plongée',
    capacite: 6,
    prix: 75,
    duree: 150,
    envies: ['sport', 'plage', 'aventure'],
    photo: photoPrestation('plongee'),
  },
  {
    nom: 'Visite guidée du Colisée et du Forum',
    destination: 'rome',
    categorie: 'Visite guidée',
    capacite: 30,
    prix: 55,
    duree: 180,
    accessible: true,
    envies: ['culture'],
    photo: photoPrestation('visite-guidee'),
  },
  {
    nom: 'Cours de pâtes fraîches',
    destination: 'rome',
    categorie: 'Atelier culinaire',
    capacite: 20,
    prix: 70,
    duree: 180,
    accessible: true,
    envies: ['gastronomie', 'famille'],
    photo: photoPrestation('cuisine'),
  },
  {
    nom: 'Baignade dans une source chaude',
    destination: 'islande',
    categorie: 'Bien-être',
    capacite: 50,
    prix: 85,
    duree: 180,
    accessible: true,
    envies: ['détente', 'nature'],
    photo: photoPrestation('spa'),
  },
  {
    nom: 'Randonnée sur le glacier Sólheimajökull',
    destination: 'islande',
    categorie: 'Randonnée',
    capacite: 12,
    prix: 130,
    duree: 240,
    envies: ['nature', 'aventure', 'sport'],
    photo: photoPrestation('randonnee'),
  },
  {
    nom: 'Observation des baleines à Húsavík',
    destination: 'islande',
    categorie: 'Sortie en mer',
    capacite: 40,
    prix: 110,
    duree: 180,
    envies: ['nature', 'famille'],
    photo: photoPrestation('baleines'),
  },
  {
    nom: 'Cours de surf à Canggu',
    destination: 'bali',
    categorie: 'Sport nautique',
    capacite: 8,
    prix: 40,
    duree: 120,
    envies: ['sport', 'plage'],
    photo: photoPrestation('surf'),
  },
  {
    nom: 'Massage balinais en duo',
    destination: 'bali',
    categorie: 'Bien-être',
    capacite: 2,
    prix: 35,
    duree: 90,
    envies: ['détente', 'romantique'],
    photo: photoPrestation('spa'),
  },
  {
    nom: 'Lever de soleil au mont Batur',
    destination: 'bali',
    categorie: 'Randonnée',
    capacite: 12,
    prix: 55,
    duree: 360,
    envies: ['nature', 'aventure'],
    photo: photoPrestation('randonnee'),
  },
  {
    nom: 'Kayak sur le lac Arenal',
    destination: 'costa-rica',
    categorie: 'Sport nautique',
    capacite: 10,
    prix: 65,
    duree: 180,
    envies: ['nature', 'sport'],
    photo: photoPrestation('kayak'),
  },
  {
    nom: 'Balade nocturne dans la forêt de Monteverde',
    destination: 'costa-rica',
    categorie: 'Randonnée',
    capacite: 12,
    prix: 35,
    duree: 120,
    envies: ['nature', 'famille', 'aventure'],
    photo: photoPrestation('randonnee'),
  },
]

const MARGE = 0.2

/**
 * Prix indicatif « à partir de » par personne : 7 nuits dans le logement le moins cher pour deux,
 * vol depuis Paris et transferts au bas de la fourchette, marge de 20 % et frais de dossier partagés.
 */
export function prixAPartirDe(destination: Destination) {
  const logements = LOGEMENTS.filter((l) => l.destination === destination.slug && l.capacite >= 2)
  const nuit = Math.min(...logements.map((l) => l.prix))
  const frais = TYPES_DE_VOYAGE.find((t) => t.valeur === destination.types[0])!.fraisDossier
  const parPersonne =
    ((nuit * 7) / 2 + destination.volParis[0] + destination.transfert[0]) * (1 + MARGE) + frais / 2
  return Math.round(parPersonne / 10) * 10
}
