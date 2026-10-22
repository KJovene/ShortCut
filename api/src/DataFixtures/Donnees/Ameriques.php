<?php

namespace App\DataFixtures\Donnees;

use App\DataFixtures\UtilisateurFixtures;

/**
 * Catalogue de démo, Amériques : 10 destinations, du Québec à la Patagonie.
 * Mêmes conventions que Catalogue (prix en euros, saisons, climat mois par mois).
 * Volontairement non couverts : au Pérou, le trek du chemin de l'Inca en février (fermé pour entretien).
 */
final class Ameriques
{
    /** @return array<string, array<string, mixed>> */
    public static function destinations(): array
    {
        return [
            'quebec' => [
                'pays' => 'Canada', 'region' => 'Québec', 'ville' => 'Québec', 'photo' => 'quebec',
                'nom' => 'Québec, Canada', 'typesDeVoyage' => ['circuit', 'groupe', 'entreprise'], 'moisIdeal' => 9, 'latitude' => 46.8, 'longitude' => -71.2,
                'zones' => ['Amérique du Nord', 'Amériques'],
                'photoAlt' => 'Rue du Vieux-Québec menant au Château Frontenac, avec un arbre aux feuilles dorées d\'automne',
                'accroche' => 'Vieux-Québec, baleines du Saint-Laurent et forêts de l’été indien.',
                'description' => 'La Belle Province en français : le Vieux-Québec fortifié, Montréal, les baleines de Tadoussac, les lacs et forêts de la Mauricie flamboyants à l\'été indien, et l\'hiver ses chiens de traîneau. Circuit en voiture ou séminaire francophone.',
                'pointsForts' => ['Voyage en français', 'Été indien fin septembre', 'Baleines du Saint-Laurent', 'Grands espaces'],
                'tempsDeTrajet' => ['Paris' => 480, 'Lyon' => 600, 'Bordeaux' => 620],
                'temperatures' => [-12, -11, -5, 3, 11, 17, 19, 18, 13, 6, 0, -8],
                'pluie' => [90, 75, 90, 85, 110, 115, 120, 110, 120, 95, 105, 115],
                'saisons' => [[1, 3, 1.0], [4, 5, 0.85], [6, 8, 1.2], [9, 10, 1.25], [11, 11, 0.85], [12, 12, 1.05]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'new-york' => [
                'pays' => 'États-Unis', 'region' => 'État de New York', 'ville' => 'New York', 'photo' => 'new-york',
                'nom' => 'New York, États-Unis', 'typesDeVoyage' => ['groupe', 'entreprise', 'sejour'], 'moisIdeal' => 5, 'latitude' => 40.7, 'longitude' => -74.0,
                'zones' => ['Amérique du Nord', 'Amériques'],
                'photoAlt' => 'Gratte-ciel illuminés de Manhattan au crépuscule, derrière une pile du pont de Brooklyn et l\'East River',
                'accroche' => 'Manhattan, Broadway et Central Park, la ville qui ne dort jamais.',
                'description' => 'La ville-monde : gratte-ciel de Manhattan, Central Park, musées, comédies musicales de Broadway et quartiers de Brooklyn. Inépuisable pour un séjour, un voyage de groupe ou un incentive, surtout au printemps et à l\'automne.',
                'pointsForts' => ['Broadway et musées', 'Central Park', 'Shopping', 'Incentives d\'exception'],
                'tempsDeTrajet' => ['Paris' => 510, 'Lyon' => 630, 'Bordeaux' => 660],
                'temperatures' => [1, 2, 6, 12, 17, 23, 25, 25, 21, 15, 9, 3],
                'pluie' => [90, 80, 105, 105, 105, 105, 115, 110, 100, 100, 90, 100],
                'saisons' => [[1, 2, 0.85], [3, 3, 0.95], [4, 6, 1.1], [7, 8, 1.0], [9, 11, 1.15], [12, 12, 1.3]],
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'tulum' => [
                'pays' => 'Mexique', 'region' => 'Yucatán, Quintana Roo', 'ville' => 'Tulum', 'photo' => 'tulum',
                'nom' => 'Tulum et Yucatán, Mexique', 'typesDeVoyage' => ['noces', 'sejour', 'circuit'], 'moisIdeal' => 3, 'latitude' => 20.2, 'longitude' => -87.5,
                'zones' => ['Amérique centrale', 'Amérique latine', 'Caraïbes', 'Amériques'],
                'photoAlt' => 'Ruines mayas de Tulum perchées sur une falaise au-dessus d\'une crique de sable et de la mer des Caraïbes',
                'accroche' => 'Ruines mayas face à la mer, cénotes et plages de sable blanc.',
                'description' => 'La Riviera maya : les ruines de Tulum au-dessus de la mer turquoise, Chichén Itzá, la baignade dans les cénotes, les villes coloniales de Valladolid et Mérida. Plages de sable blanc et hôtels bohèmes pour un voyage de noces.',
                'pointsForts' => ['Ruines mayas', 'Baignade dans les cénotes', 'Plages des Caraïbes', 'Hôtels bohèmes'],
                'tempsDeTrajet' => ['Paris' => 660, 'Lyon' => 780, 'Bordeaux' => 810],
                'temperatures' => [24, 25, 26, 27, 28, 29, 29, 29, 28, 27, 26, 25],
                'pluie' => [60, 40, 30, 30, 90, 170, 120, 130, 200, 170, 90, 60],
                'saisons' => Catalogue::SAISONS_TROPIQUES,
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'cuba' => [
                'pays' => 'Cuba', 'region' => 'La Havane et Viñales', 'ville' => 'La Havane', 'photo' => 'cuba',
                'nom' => 'Cuba', 'typesDeVoyage' => ['circuit', 'groupe', 'sejour'], 'moisIdeal' => 2, 'latitude' => 23.1, 'longitude' => -82.4,
                'zones' => ['Caraïbes', 'Amérique latine', 'Amériques', 'Îles'],
                'photoAlt' => 'Voiture américaine ancienne rouge garée devant des façades colorées et décrépies de La Havane',
                'accroche' => 'Voitures anciennes, salsa et vallée de Viñales, le temps suspendu.',
                'description' => 'La Havane et ses voitures des années cinquante, la vallée de Viñales et ses plantations de tabac, Trinidad la coloniale, puis les plages de Cayo Santa María. Nuits en casa particular et musique à chaque coin de rue.',
                'pointsForts' => ['La Havane en voiture ancienne', 'Nuits chez l\'habitant', 'Salsa et musique live', 'Plages des cayos'],
                'tempsDeTrajet' => ['Paris' => 630, 'Lyon' => 750, 'Bordeaux' => 780],
                'temperatures' => [22, 22, 24, 25, 27, 28, 28, 28, 28, 26, 25, 23],
                'pluie' => [65, 45, 45, 55, 100, 180, 105, 100, 145, 180, 80, 55],
                'saisons' => Catalogue::SAISONS_TROPIQUES,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'punta-cana' => [
                'pays' => 'République dominicaine', 'region' => 'La Altagracia', 'ville' => 'Punta Cana', 'photo' => 'punta-cana',
                'nom' => 'Punta Cana, République dominicaine', 'typesDeVoyage' => ['sejour', 'noces', 'groupe'], 'moisIdeal' => 1, 'latitude' => 18.6, 'longitude' => -68.4,
                'zones' => ['Caraïbes', 'Amérique latine', 'Amériques', 'Îles'],
                'photoAlt' => 'Plage de Bávaro avec cocotiers penchés, parasol de paille et bateaux sur une mer turquoise',
                'accroche' => 'Cocotiers, sable blanc et tout compris, au soleil de décembre à avril.',
                'description' => 'Des kilomètres de plages bordées de cocotiers, des resorts tout compris avec clubs enfants, l\'île de Saona en catamaran et des baleines à bosse de janvier à mars à Samaná. Le soleil des Caraïbes en toute simplicité.',
                'pointsForts' => ['Resorts tout compris', 'Île de Saona', 'Clubs enfants', 'Vol direct depuis Paris'],
                'tempsDeTrajet' => ['Paris' => 570, 'Lyon' => 690, 'Bordeaux' => 720],
                'temperatures' => [26, 26, 26, 27, 28, 28, 29, 29, 29, 28, 27, 26],
                'pluie' => [60, 50, 50, 80, 150, 110, 90, 110, 130, 140, 120, 80],
                'saisons' => Catalogue::SAISONS_TROPIQUES,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'perou' => [
                'pays' => 'Pérou', 'region' => 'Cusco et Vallée sacrée', 'ville' => 'Cusco', 'photo' => 'perou',
                'nom' => 'Pérou, Machu Picchu', 'typesDeVoyage' => ['circuit', 'groupe'], 'moisIdeal' => 6, 'latitude' => -13.5, 'longitude' => -72.0,
                'zones' => ['Amérique du Sud', 'Amérique latine', 'Amériques', 'Andes', 'Montagne'],
                'photoAlt' => 'Cité inca du Machu Picchu et ses terrasses, dominées par le pic du Huayna Picchu sous un ciel bleu',
                'accroche' => 'Machu Picchu, Vallée sacrée et lac Titicaca, sur les traces des Incas.',
                'description' => 'L\'empire inca : Cusco la coloniale, les marchés de la Vallée sacrée, le Machu Picchu au lever du jour, le lac Titicaca et ses îles flottantes. Un circuit en altitude, idéal pendant la saison sèche, de mai à septembre.',
                'pointsForts' => ['Machu Picchu', 'Circuit de 12 à 15 jours', 'Lac Titicaca', 'Saison sèche de mai à septembre'],
                'tempsDeTrajet' => ['Paris' => 870, 'Lyon' => 960, 'Bordeaux' => 990],
                'temperatures' => [13, 13, 13, 12, 11, 10, 10, 11, 12, 13, 14, 13],
                'pluie' => [160, 130, 110, 40, 10, 5, 5, 10, 25, 50, 75, 120],
                'saisons' => [[1, 3, 0.85], [4, 4, 0.95], [5, 9, 1.2], [10, 12, 0.95]],
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'buenos-aires' => [
                'pays' => 'Argentine', 'region' => 'Buenos Aires et Patagonie', 'ville' => 'Buenos Aires', 'photo' => 'buenos-aires',
                'nom' => 'Argentine, Buenos Aires et Patagonie', 'typesDeVoyage' => ['circuit', 'sejour', 'groupe'], 'moisIdeal' => 11, 'latitude' => -34.6, 'longitude' => -58.4,
                'zones' => ['Amérique du Sud', 'Amérique latine', 'Amériques', 'Patagonie', 'Hémisphère sud'],
                'photoAlt' => 'Maisons colorées en tôle ondulée du Caminito, dans le quartier de La Boca à Buenos Aires',
                'accroche' => 'Tango à Buenos Aires, glaciers de Patagonie et chutes d’Iguazú.',
                'description' => 'Buenos Aires, ses milongas et ses parrillas, puis les grands espaces : le glacier Perito Moreno, les sommets du Fitz Roy, les chutes d\'Iguazú et les vignobles de Mendoza. L\'été austral, de novembre à mars, est la meilleure saison pour la Patagonie.',
                'pointsForts' => ['Tango et cuisine argentine', 'Glacier Perito Moreno', 'Chutes d\'Iguazú', 'Été de novembre à mars'],
                'tempsDeTrajet' => ['Paris' => 810, 'Lyon' => 930, 'Bordeaux' => 960],
                'temperatures' => [25, 24, 22, 18, 15, 12, 11, 13, 15, 18, 21, 24],
                'pluie' => [120, 120, 110, 90, 80, 60, 60, 65, 70, 110, 120, 105],
                'saisons' => Catalogue::SAISONS_HEMISPHERE_SUD,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'atacama' => [
                'pays' => 'Chili', 'region' => 'Antofagasta', 'ville' => 'San Pedro de Atacama', 'photo' => 'atacama',
                'nom' => 'Chili, désert d\'Atacama', 'typesDeVoyage' => ['circuit', 'noces'], 'moisIdeal' => 10, 'latitude' => -22.9, 'longitude' => -68.2,
                'zones' => ['Amérique du Sud', 'Amérique latine', 'Amériques', 'Andes', 'Désert', 'Hémisphère sud'],
                'photoAlt' => 'Flamant rose se reflétant dans la lagune Tebenquiche, face aux montagnes du désert d\'Atacama',
                'accroche' => 'Le désert le plus aride du monde : geysers, lagunes et ciels étoilés.',
                'description' => 'Un désert d\'altitude aux couleurs irréelles : vallée de la Lune, geysers du Tatio à l\'aube, lagunes altiplaniques aux flamants roses et le ciel le plus pur de la planète pour observer les étoiles. Se combine avec Santiago et la Patagonie chilienne.',
                'pointsForts' => ['Observation des étoiles', 'Geysers et lagunes', 'Lodges de charme', 'Sec toute l\'année'],
                'tempsDeTrajet' => ['Paris' => 960, 'Lyon' => 1050, 'Bordeaux' => 1080],
                'temperatures' => [17, 17, 16, 14, 12, 10, 10, 11, 13, 14, 16, 17],
                'pluie' => [10, 15, 5, 0, 0, 1, 1, 2, 0, 0, 0, 2],
                'saisons' => [[1, 2, 1.2], [3, 6, 1.0], [7, 8, 1.1], [9, 11, 1.0], [12, 12, 1.2]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'rio-de-janeiro' => [
                'pays' => 'Brésil', 'region' => 'État de Rio de Janeiro', 'ville' => 'Rio de Janeiro', 'photo' => 'rio-de-janeiro',
                'nom' => 'Rio de Janeiro, Brésil', 'typesDeVoyage' => ['sejour', 'groupe', 'entreprise'], 'moisIdeal' => 5, 'latitude' => -22.9, 'longitude' => -43.2,
                'zones' => ['Amérique du Sud', 'Amérique latine', 'Amériques', 'Atlantique', 'Hémisphère sud'],
                'photoAlt' => 'Vue aérienne de la baie de Rio de Janeiro et du Pain de Sucre entourés d\'immeubles et de collines',
                'accroche' => 'Pain de Sucre, Copacabana et samba, entre mer et forêt.',
                'description' => 'La ville merveilleuse : le Christ rédempteur, le Pain de Sucre, les plages de Copacabana et d\'Ipanema, la forêt de Tijuca et la samba de Lapa. Le carnaval en février, la baie d\'Ilha Grande et Paraty pour prolonger.',
                'pointsForts' => ['Plages de Copacabana et Ipanema', 'Carnaval en février', 'Christ rédempteur', 'Ilha Grande et Paraty'],
                'tempsDeTrajet' => ['Paris' => 690, 'Lyon' => 810, 'Bordeaux' => 840],
                'temperatures' => [27, 27, 26, 25, 23, 22, 21, 22, 22, 24, 25, 26],
                'pluie' => [135, 120, 135, 95, 70, 50, 45, 45, 65, 80, 100, 140],
                'saisons' => [[1, 1, 1.15], [2, 2, 1.4], [3, 6, 0.95], [7, 8, 1.05], [9, 11, 0.95], [12, 12, 1.3]],
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'galapagos' => [
                'pays' => 'Équateur', 'region' => 'Îles Galápagos', 'ville' => 'Puerto Ayora', 'photo' => 'galapagos',
                'nom' => 'Équateur, îles Galápagos', 'typesDeVoyage' => ['circuit', 'noces'], 'moisIdeal' => 4, 'latitude' => -0.7, 'longitude' => -90.3,
                'zones' => ['Amérique du Sud', 'Amérique latine', 'Amériques', 'Pacifique', 'Îles'],
                'photoAlt' => 'Deux otaries se reposant sur une plage de sable bordée de rochers volcaniques, aux Galápagos',
                'accroche' => 'Tortues géantes, otaries et iguanes marins, une faune sans crainte de l’homme.',
                'description' => 'Le laboratoire de Darwin : tortues géantes, otaries joueuses, iguanes marins et fous à pattes bleues que l\'on approche à quelques mètres. Croisière ou séjour d\'île en île, snorkeling avec les tortues, après quelques jours à Quito.',
                'pointsForts' => ['Faune unique au monde', 'Snorkeling avec les otaries', 'Croisière d\'île en île', 'Naturalistes certifiés'],
                'tempsDeTrajet' => ['Paris' => 1020, 'Lyon' => 1110, 'Bordeaux' => 1140],
                'temperatures' => [26, 27, 27, 26, 25, 23, 22, 21, 21, 22, 23, 24],
                'pluie' => [20, 35, 30, 25, 5, 5, 5, 3, 5, 5, 5, 10],
                'saisons' => [[1, 5, 1.1], [6, 8, 1.2], [9, 11, 0.9], [12, 12, 1.3]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function prestataires(): array
    {
        return [
            'hotels-ameriques' => ['nom' => 'Hôtels des Amériques', 'type' => 'hebergeur', 'contactNom' => 'Emily Carter', 'email' => 'groups@hotels-ameriques.example', 'telephone' => '+1 212 000 0037', 'conditionsAnnulation' => 'Gratuite jusqu\'à 72 heures avant l\'arrivée ; groupes : 30 jours.'],
            'caraibes-resorts' => ['nom' => 'Caraïbes Resorts', 'type' => 'hebergeur', 'contactNom' => 'Carlos Méndez', 'email' => 'ventas@caraibes-resorts.example', 'telephone' => '+52 984 000 0038', 'conditionsAnnulation' => 'Gratuite jusqu\'à 21 jours avant l\'arrivée ; 50 % ensuite. Fêtes de fin d\'année : 45 jours.'],
            'andes-lodges' => ['nom' => 'Andes & Galápagos Lodges', 'type' => 'hebergeur', 'contactNom' => 'Valeria Quispe', 'email' => 'reservas@andes-lodges.example', 'telephone' => '+51 84 000 039', 'conditionsAnnulation' => 'Gratuite jusqu\'à 30 jours avant l\'arrivée, puis 50 % ; Galápagos : 60 jours.'],
            'transatlantique' => [
                'nom' => 'Transatlantique Voyages', 'type' => 'transporteur', 'contactNom' => 'Pôle Amériques', 'email' => 'tarifs@transatlantique.example', 'telephone' => '+33 1 00 00 00 40',
                'conditionsAnnulation' => 'Frais de 200 € par personne jusqu\'à 45 jours avant le départ, puis non remboursable.',
                'vols' => [
                    'Québec' => ['Paris' => [450, 950], 'Lyon' => [520, 1050], 'Bordeaux' => [540, 1080]],
                    'New York' => ['Paris' => [450, 1000], 'Lyon' => [520, 1100], 'Bordeaux' => [540, 1150]],
                    'Tulum' => ['Paris' => [550, 1100], 'Lyon' => [620, 1200], 'Bordeaux' => [640, 1230]],
                    'La Havane' => ['Paris' => [550, 1050], 'Lyon' => [620, 1150], 'Bordeaux' => [640, 1180]],
                    'Punta Cana' => ['Paris' => [500, 1000], 'Lyon' => [560, 1100], 'Bordeaux' => [580, 1120]],
                    'Cusco' => ['Paris' => [750, 1400], 'Lyon' => [820, 1500], 'Bordeaux' => [840, 1530]],
                    'Buenos Aires' => ['Paris' => [700, 1350], 'Lyon' => [780, 1450], 'Bordeaux' => [800, 1480]],
                    'San Pedro de Atacama' => ['Paris' => [800, 1500], 'Lyon' => [860, 1600], 'Bordeaux' => [880, 1630]],
                    'Rio de Janeiro' => ['Paris' => [600, 1200], 'Lyon' => [680, 1300], 'Bordeaux' => [700, 1330]],
                    'Puerto Ayora' => ['Paris' => [1000, 1800], 'Lyon' => [1080, 1900], 'Bordeaux' => [1100, 1950]],
                ],
                // Circuits (Québec, Cuba, Pérou, Argentine) : voiture, chauffeur ou vols intérieurs compris.
                'transferts' => ['Québec' => [150, 350], 'New York' => [30, 80], 'Tulum' => [40, 90], 'La Havane' => [120, 280], 'Punta Cana' => [20, 50], 'Cusco' => [180, 400], 'Buenos Aires' => [250, 550], 'San Pedro de Atacama' => [60, 140], 'Rio de Janeiro' => [25, 60], 'Puerto Ayora' => [80, 180]],
            ],
            'guias-del-sur' => ['nom' => 'Guías del Sur', 'type' => 'guide', 'contactNom' => 'Diego Fernández', 'email' => 'guias@guias-del-sur.example', 'telephone' => '+56 2 0000 0041', 'conditionsAnnulation' => 'Gratuite jusqu\'à 7 jours avant ; 50 % ensuite.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function logements(): array
    {
        return [
            // Québec
            ['nom' => 'Hôtel du Vieux-Québec, chambre double', 'destination' => 'quebec', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 210, 'accessible' => true, 'equipements' => ['wifi', 'petit-déjeuner', 'ascenseur'], 'envies' => ['culture', 'romantique', 'gastronomie'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Chalet en rondins de la Mauricie', 'destination' => 'quebec', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Chalet', 'capacite' => 6, 'chambres' => 3, 'prix' => 260, 'sejourMinimum' => 2, 'equipements' => ['wifi', 'cuisine équipée', 'spa extérieur', 'canot', 'cheminée'], 'envies' => ['nature', 'famille'], 'photos' => ['chalet']],
            ['nom' => 'Auberge de Tadoussac', 'destination' => 'quebec', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Auberge', 'capacite' => 3, 'chambres' => 1, 'prix' => 170, 'equipements' => ['wifi', 'demi-pension', 'vue sur le fjord'], 'envies' => ['nature'], 'photos' => ['guesthouse']],
            ['nom' => 'Québec Centre des congrès Hôtel', 'destination' => 'quebec', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 80, 'chambres' => 40, 'prix' => 5600, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 150 places', 'salles de sous-commission', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['seminaire']],
            // New York
            ['nom' => 'Hôtel de Midtown, chambre double', 'destination' => 'new-york', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 320, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'ascenseur'], 'envies' => ['culture', 'shopping'], 'photos' => ['hotel-ville']],
            ['nom' => 'Appartement de Brooklyn', 'destination' => 'new-york', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Appartement', 'capacite' => 5, 'chambres' => 2, 'prix' => 290, 'sejourMinimum' => 4, 'equipements' => ['wifi', 'climatisation', 'cuisine équipée', 'lave-linge'], 'envies' => ['famille', 'culture', 'gastronomie'], 'photos' => ['appartement']],
            ['nom' => 'Hôtel de Times Square, bloc groupe', 'destination' => 'new-york', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 60, 'chambres' => 30, 'prix' => 8400, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'salle de réunion 100 places', 'restauration de groupe'], 'envies' => ['culture', 'shopping'], 'photos' => ['seminaire', 'hotel-ville']],
            // Tulum
            ['nom' => 'Cabaña de luxe sur la plage de Tulum', 'destination' => 'tulum', 'prestataire' => 'caraibes-resorts', 'categorie' => 'Hôtel de charme', 'capacite' => 2, 'chambres' => 1, 'prix' => 290, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'accès plage', 'petit-déjeuner', 'spa'], 'envies' => ['romantique', 'plage', 'détente'], 'photos' => ['plage']],
            ['nom' => 'Hacienda de Valladolid', 'destination' => 'tulum', 'prestataire' => 'caraibes-resorts', 'categorie' => 'Hacienda', 'capacite' => 4, 'chambres' => 2, 'prix' => 180, 'equipements' => ['wifi', 'piscine', 'climatisation', 'petit-déjeuner'], 'envies' => ['culture', 'famille'], 'photos' => ['riad']],
            ['nom' => 'Resort de Playa del Carmen, chambre familiale', 'destination' => 'tulum', 'prestataire' => 'caraibes-resorts', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 230, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'tout compris', 'club enfants', 'accès plage'], 'envies' => ['plage', 'famille'], 'photos' => ['hotel-piscine']],
            // Cuba
            ['nom' => 'Casa particular de La Havane Vieja', 'destination' => 'cuba', 'prestataire' => 'caraibes-resorts', 'categorie' => 'Chambre chez l\'habitant', 'capacite' => 2, 'chambres' => 1, 'prix' => 60, 'equipements' => ['climatisation', 'petit-déjeuner', 'terrasse'], 'envies' => ['culture', 'romantique'], 'photos' => ['riad']],
            ['nom' => 'Hôtel colonial de Trinidad', 'destination' => 'cuba', 'prestataire' => 'caraibes-resorts', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 140, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'petit-déjeuner'], 'envies' => ['culture'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Resort de Cayo Santa María, chambre familiale', 'destination' => 'cuba', 'prestataire' => 'caraibes-resorts', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 190, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'tout compris', 'accès plage'], 'envies' => ['plage', 'famille', 'détente'], 'photos' => ['plage']],
            ['nom' => 'Hôtel de La Havane, bloc groupe', 'destination' => 'cuba', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 40, 'chambres' => 20, 'prix' => 2600, 'equipements' => ['wifi', 'climatisation', 'piscine', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['hotel-piscine']],
            // Punta Cana
            ['nom' => 'Resort tout compris de Bávaro, chambre familiale', 'destination' => 'punta-cana', 'prestataire' => 'caraibes-resorts', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 320, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'tout compris', 'club enfants', 'accès plage'], 'envies' => ['plage', 'famille', 'détente'], 'photos' => ['plage']],
            ['nom' => 'Suite adultes seulement, vue mer', 'destination' => 'punta-cana', 'prestataire' => 'caraibes-resorts', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 360, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'climatisation', 'jacuzzi privé', 'tout compris', 'spa'], 'envies' => ['romantique', 'détente', 'plage'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Club de Punta Cana, bloc groupe', 'destination' => 'punta-cana', 'prestataire' => 'caraibes-resorts', 'categorie' => 'Club de vacances', 'capacite' => 60, 'chambres' => 30, 'prix' => 6200, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'tout compris', 'animation', 'salle de réunion 80 places'], 'envies' => ['plage', 'détente'], 'photos' => ['hotel-piscine']],
            // Pérou
            ['nom' => 'Hôtel colonial de Cusco', 'destination' => 'perou', 'prestataire' => 'andes-lodges', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 150, 'accessible' => true, 'equipements' => ['wifi', 'oxygène', 'chauffage', 'petit-déjeuner'], 'envies' => ['culture'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Lodge de la Vallée sacrée', 'destination' => 'perou', 'prestataire' => 'andes-lodges', 'categorie' => 'Lodge', 'capacite' => 4, 'chambres' => 2, 'prix' => 190, 'equipements' => ['wifi', 'demi-pension', 'jardin', 'cheminée'], 'envies' => ['nature', 'famille', 'détente'], 'photos' => ['ecolodge']],
            ['nom' => 'Hôtel d\'Aguas Calientes, au pied du Machu Picchu', 'destination' => 'perou', 'prestataire' => 'andes-lodges', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 130, 'equipements' => ['wifi', 'petit-déjeuner'], 'envies' => ['culture', 'aventure'], 'photos' => ['guesthouse']],
            ['nom' => 'Hôtel de Cusco, bloc groupe', 'destination' => 'perou', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 30, 'chambres' => 15, 'prix' => 1600, 'equipements' => ['wifi', 'chauffage', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['hotel-chambre']],
            // Argentine
            ['nom' => 'Hôtel de Palermo, Buenos Aires', 'destination' => 'buenos-aires', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 140, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine sur le toit', 'petit-déjeuner'], 'envies' => ['culture', 'gastronomie', 'romantique'], 'photos' => ['hotel-ville']],
            ['nom' => 'Estancia de Patagonie, El Calafate', 'destination' => 'buenos-aires', 'prestataire' => 'andes-lodges', 'categorie' => 'Estancia', 'capacite' => 4, 'chambres' => 2, 'prix' => 280, 'equipements' => ['wifi', 'pension complète', 'balades à cheval'], 'envies' => ['nature', 'famille', 'aventure'], 'photos' => ['ecolodge']],
            ['nom' => 'Hôtel d\'Iguazú, chambre familiale', 'destination' => 'buenos-aires', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 190, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'petit-déjeuner'], 'envies' => ['nature', 'famille'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Hôtel de San Telmo, bloc groupe', 'destination' => 'buenos-aires', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 40, 'chambres' => 20, 'prix' => 2100, 'equipements' => ['wifi', 'climatisation', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['hotel-chambre']],
            // Atacama
            ['nom' => 'Lodge d\'adobe de San Pedro, chambre double', 'destination' => 'atacama', 'prestataire' => 'andes-lodges', 'categorie' => 'Lodge', 'capacite' => 2, 'chambres' => 1, 'prix' => 340, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'pension complète', 'piscine', 'excursions comprises'], 'envies' => ['nature', 'romantique', 'désert'], 'photos' => ['desert'], 'description' => 'Excursions quotidiennes avec guide et observatoire astronomique privé.'],
            ['nom' => 'Hostal familial de San Pedro', 'destination' => 'atacama', 'prestataire' => 'andes-lodges', 'categorie' => 'Maison d\'hôtes', 'capacite' => 4, 'chambres' => 2, 'prix' => 120, 'equipements' => ['wifi', 'petit-déjeuner', 'patio'], 'envies' => ['nature', 'famille'], 'photos' => ['riad']],
            ['nom' => 'Hôtel de Santiago, étape', 'destination' => 'atacama', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 130, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine'], 'envies' => ['culture'], 'photos' => ['hotel-ville']],
            // Rio de Janeiro
            ['nom' => 'Hôtel de Copacabana, chambre vue mer', 'destination' => 'rio-de-janeiro', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 210, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'vue mer', 'petit-déjeuner'], 'envies' => ['plage', 'romantique', 'détente'], 'photos' => ['hotel-ville']],
            ['nom' => 'Appartement d\'Ipanema', 'destination' => 'rio-de-janeiro', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Appartement', 'capacite' => 5, 'chambres' => 2, 'prix' => 190, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'climatisation', 'cuisine équipée'], 'envies' => ['plage', 'famille'], 'photos' => ['appartement']],
            ['nom' => 'Pousada d\'Ilha Grande', 'destination' => 'rio-de-janeiro', 'prestataire' => 'hotels-ameriques', 'categorie' => 'Pousada', 'capacite' => 3, 'chambres' => 1, 'prix' => 120, 'equipements' => ['wifi', 'ventilateur', 'petit-déjeuner', 'accès plage'], 'envies' => ['nature', 'plage'], 'photos' => ['ecolodge']],
            ['nom' => 'Rio Convention Hotel', 'destination' => 'rio-de-janeiro', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 80, 'chambres' => 40, 'prix' => 6400, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 180 places', 'salles de sous-commission', 'piscine', 'restauration de groupe'], 'envies' => ['plage', 'culture'], 'photos' => ['seminaire']],
            // Galápagos
            ['nom' => 'Croisière d\'île en île, cabine double', 'destination' => 'galapagos', 'prestataire' => 'andes-lodges', 'categorie' => 'Yacht de croisière', 'capacite' => 2, 'chambres' => 1, 'prix' => 750, 'sejourMinimum' => 4, 'equipements' => ['pension complète', 'guide naturaliste', 'matériel de snorkeling'], 'envies' => ['nature', 'romantique', 'aventure'], 'photos' => ['voilier'], 'description' => 'Quatre nuits à bord avec un naturaliste, débarquements et snorkeling chaque jour.'],
            ['nom' => 'Lodge de Santa Cruz, chambre familiale', 'destination' => 'galapagos', 'prestataire' => 'andes-lodges', 'categorie' => 'Lodge', 'capacite' => 4, 'chambres' => 1, 'prix' => 320, 'equipements' => ['wifi', 'piscine', 'demi-pension'], 'envies' => ['nature', 'famille'], 'photos' => ['ecolodge']],
            ['nom' => 'Hôtel de Puerto Ayora', 'destination' => 'galapagos', 'prestataire' => 'andes-lodges', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 160, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'petit-déjeuner'], 'envies' => ['nature'], 'photos' => ['hotel-chambre']],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function activites(): array
    {
        return [
            // Québec
            ['nom' => 'Observation des baleines à Tadoussac', 'destination' => 'quebec', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 40, 'prix' => 85, 'duree' => 180, 'accessible' => true, 'envies' => ['nature', 'famille'], 'photos' => ['baleines'], 'saisons' => [[6, 10, 1.0]]],
            ['nom' => 'Canot dans le parc de la Mauricie', 'destination' => 'quebec', 'prestataire' => 'explora', 'categorie' => 'Sport nautique', 'capacite' => 12, 'prix' => 55, 'duree' => 240, 'ageMinimum' => 8, 'envies' => ['nature', 'sport'], 'photos' => ['canoe'], 'saisons' => [[6, 10, 1.0]]],
            ['nom' => 'Visite guidée du Vieux-Québec', 'destination' => 'quebec', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 30, 'duree' => 150, 'accessible' => true, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            // New York
            ['nom' => 'Comédie musicale à Broadway', 'destination' => 'new-york', 'prestataire' => 'saveurs', 'categorie' => 'Spectacle', 'capacite' => 50, 'prix' => 140, 'duree' => 150, 'accessible' => true, 'envies' => ['culture', 'famille', 'romantique'], 'photos' => ['spectacle']],
            ['nom' => 'Visite guidée de Manhattan et Brooklyn', 'destination' => 'new-york', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 60, 'duree' => 360, 'envies' => ['culture', 'shopping'], 'photos' => ['visite-guidee']],
            // Tulum
            ['nom' => 'Baignade dans les cénotes', 'destination' => 'tulum', 'prestataire' => 'oceanes', 'categorie' => 'Excursion nature', 'capacite' => 15, 'prix' => 70, 'duree' => 300, 'ageMinimum' => 6, 'envies' => ['nature', 'aventure', 'famille'], 'photos' => ['plongee']],
            ['nom' => 'Visite guidée de Chichén Itzá', 'destination' => 'tulum', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 85, 'duree' => 600, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            // Cuba
            ['nom' => 'La Havane en voiture américaine ancienne', 'destination' => 'cuba', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 4, 'prix' => 45, 'duree' => 120, 'envies' => ['culture', 'romantique'], 'photos' => ['visite-guidee']],
            ['nom' => 'Cours de salsa et soirée musicale', 'destination' => 'cuba', 'prestataire' => 'saveurs', 'categorie' => 'Atelier culturel', 'capacite' => 20, 'prix' => 30, 'duree' => 150, 'envies' => ['culture', 'romantique'], 'photos' => ['spectacle']],
            ['nom' => 'Randonnée à cheval dans la vallée de Viñales', 'destination' => 'cuba', 'prestataire' => 'explora', 'categorie' => 'Randonnée', 'capacite' => 10, 'prix' => 40, 'duree' => 240, 'ageMinimum' => 10, 'envies' => ['nature', 'aventure'], 'photos' => ['randonnee']],
            // Punta Cana
            ['nom' => 'Catamaran à l\'île de Saona', 'destination' => 'punta-cana', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 50, 'prix' => 95, 'duree' => 540, 'accessible' => true, 'envies' => ['plage', 'famille', 'détente'], 'photos' => ['voilier']],
            ['nom' => 'Observation des baleines à bosse à Samaná', 'destination' => 'punta-cana', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 40, 'prix' => 120, 'duree' => 600, 'envies' => ['nature', 'famille'], 'photos' => ['baleines'], 'saisons' => [[1, 3, 1.0]]],
            // Pérou (chemin de l'Inca fermé en février)
            ['nom' => 'Visite guidée du Machu Picchu', 'destination' => 'perou', 'prestataire' => 'guias-del-sur', 'categorie' => 'Visite guidée', 'capacite' => 16, 'prix' => 180, 'duree' => 600, 'envies' => ['culture', 'aventure'], 'photos' => ['visite-guidee']],
            ['nom' => 'Trek du chemin de l\'Inca, 4 jours', 'destination' => 'perou', 'prestataire' => 'guias-del-sur', 'categorie' => 'Trek', 'capacite' => 12, 'prix' => 650, 'duree' => 2400, 'ageMinimum' => 14, 'envies' => ['aventure', 'sport', 'nature'], 'photos' => ['trek'], 'saisons' => [[1, 1, 0.9], [3, 4, 0.95], [5, 9, 1.15], [10, 12, 0.95]]],
            ['nom' => 'Îles flottantes du lac Titicaca', 'destination' => 'perou', 'prestataire' => 'guias-del-sur', 'categorie' => 'Excursion guidée', 'capacite' => 20, 'prix' => 60, 'duree' => 480, 'envies' => ['culture', 'nature', 'famille'], 'photos' => ['bateau']],
            // Argentine
            ['nom' => 'Soirée tango dans une milonga', 'destination' => 'buenos-aires', 'prestataire' => 'saveurs', 'categorie' => 'Spectacle', 'capacite' => 40, 'prix' => 75, 'duree' => 180, 'envies' => ['culture', 'romantique', 'gastronomie'], 'photos' => ['spectacle']],
            ['nom' => 'Mini-trek sur le glacier Perito Moreno', 'destination' => 'buenos-aires', 'prestataire' => 'guias-del-sur', 'categorie' => 'Randonnée', 'capacite' => 15, 'prix' => 190, 'duree' => 360, 'ageMinimum' => 10, 'envies' => ['nature', 'aventure', 'sport'], 'photos' => ['trek'], 'saisons' => [[1, 4, 1.1], [9, 12, 1.0]]],
            // Atacama
            ['nom' => 'Geysers du Tatio au lever du soleil', 'destination' => 'atacama', 'prestataire' => 'guias-del-sur', 'categorie' => 'Excursion guidée', 'capacite' => 15, 'prix' => 65, 'duree' => 420, 'ageMinimum' => 6, 'envies' => ['nature', 'aventure'], 'photos' => ['desert']],
            ['nom' => 'Observation des étoiles avec un astronome', 'destination' => 'atacama', 'prestataire' => 'guias-del-sur', 'categorie' => 'Astronomie', 'capacite' => 20, 'prix' => 55, 'duree' => 150, 'ageMinimum' => 6, 'envies' => ['nature', 'romantique', 'famille'], 'photos' => ['aurores']],
            // Rio de Janeiro
            ['nom' => 'Visite guidée du Christ rédempteur et du Pain de Sucre', 'destination' => 'rio-de-janeiro', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 90, 'duree' => 480, 'accessible' => true, 'envies' => ['culture', 'famille'], 'photos' => ['visite-guidee']],
            ['nom' => 'Cours de surf à Ipanema', 'destination' => 'rio-de-janeiro', 'prestataire' => 'oceanes', 'categorie' => 'Sport nautique', 'capacite' => 8, 'prix' => 45, 'duree' => 120, 'ageMinimum' => 8, 'envies' => ['sport', 'plage'], 'photos' => ['surf']],
            // Galápagos
            ['nom' => 'Snorkeling avec les otaries et les tortues', 'destination' => 'galapagos', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 16, 'prix' => 140, 'duree' => 360, 'ageMinimum' => 6, 'envies' => ['nature', 'famille', 'aventure'], 'photos' => ['faune', 'plongee']],
            ['nom' => 'Réserve des tortues géantes avec un naturaliste', 'destination' => 'galapagos', 'prestataire' => 'guias-del-sur', 'categorie' => 'Visite guidée', 'capacite' => 16, 'prix' => 50, 'duree' => 180, 'accessible' => true, 'envies' => ['nature', 'famille'], 'photos' => ['faune']],
        ];
    }
}
