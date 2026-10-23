<?php

namespace App\DataFixtures\Donnees;

use App\DataFixtures\UtilisateurFixtures;

/**
 * Catalogue de démo, Moyen-Orient : Istanbul, la Jordanie, Dubaï et Oman.
 * Mêmes conventions que Catalogue (prix en euros, saisons, climat mois par mois).
 * Volontairement non couverts : le bivouac du Wadi Rum en juillet et août, trop chaud.
 */
final class MoyenOrient
{
    /** Le Golfe : haute saison en hiver, très creuse en été. */
    private const SAISONS_GOLFE = [[1, 3, 1.2], [4, 5, 1.0], [6, 9, 0.7], [10, 10, 1.0], [11, 12, 1.25]];

    /** @return array<string, array<string, mixed>> */
    public static function destinations(): array
    {
        return [
            'istanbul' => [
                'pays' => 'Turquie', 'region' => 'Marmara', 'ville' => 'Istanbul', 'photo' => 'istanbul',
                'nom' => 'Istanbul, Turquie', 'typesDeVoyage' => ['entreprise', 'groupe', 'sejour'], 'moisIdeal' => 5, 'latitude' => 41.0, 'longitude' => 29.0,
                'zones' => ['Europe', 'Moyen-Orient', 'Méditerranée', 'Proche-Orient'],
                'photoAlt' => 'Sainte-Sophie et ses minarets derrière un parterre de tulipes blanches',
                'accroche' => 'Entre Europe et Asie : bazars, mosquées et croisière sur le Bosphore.',
                'description' => 'À cheval sur deux continents : Sainte-Sophie, la Mosquée bleue, le Grand Bazar et les rives du Bosphore. Une capitale gourmande et animée, très bien reliée à la France, idéale pour les groupes et les séminaires.',
                'pointsForts' => ['Sainte-Sophie et Topkapı', 'Croisière sur le Bosphore', 'Bazars et cuisine turque', 'Hôtels de congrès'],
                'tempsDeTrajet' => ['Paris' => 210, 'Lyon' => 200, 'Bordeaux' => 230],
                'temperatures' => [6, 6, 8, 12, 17, 22, 24, 25, 21, 16, 12, 8],
                'pluie' => [100, 80, 70, 45, 35, 30, 20, 30, 45, 85, 100, 120],
                'saisons' => Catalogue::SAISONS_VILLE,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'jordanie' => [
                'pays' => 'Jordanie', 'region' => 'Pétra et Wadi Rum', 'ville' => 'Amman', 'photo' => 'jordanie',
                'nom' => 'Jordanie, Pétra et Wadi Rum', 'typesDeVoyage' => ['circuit', 'groupe'], 'moisIdeal' => 4, 'latitude' => 30.3, 'longitude' => 35.4,
                'zones' => ['Moyen-Orient', 'Proche-Orient', 'Asie', 'Désert'],
                'photoAlt' => 'Falaises de grès et sable rouge du désert du Wadi Rum',
                'accroche' => 'Pétra la rose, nuit sous les étoiles du Wadi Rum et baignade dans la mer Morte.',
                'description' => 'Un circuit de légende : Amman et Jerash, la cité nabatéenne de Pétra, le désert rouge du Wadi Rum en 4x4 avec une nuit en camp bédouin, puis la flottaison dans la mer Morte. Au printemps et à l\'automne, la lumière est parfaite.',
                'pointsForts' => ['Circuit de 8 jours', 'Pétra classée à l\'UNESCO', 'Bivouac dans le Wadi Rum', 'Mer Morte'],
                'tempsDeTrajet' => ['Paris' => 285, 'Lyon' => 300, 'Bordeaux' => 330],
                'temperatures' => [8, 9, 12, 16, 20, 23, 25, 25, 23, 19, 14, 10],
                'pluie' => [60, 60, 40, 15, 3, 0, 0, 0, 0, 7, 30, 45],
                'saisons' => [[1, 2, 0.8], [3, 5, 1.2], [6, 8, 0.85], [9, 11, 1.2], [12, 12, 0.85]],
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'dubai' => [
                'pays' => 'Émirats arabes unis', 'region' => 'Émirat de Dubaï', 'ville' => 'Dubaï', 'photo' => 'dubai',
                'nom' => 'Dubaï, Émirats', 'typesDeVoyage' => ['entreprise', 'sejour', 'noces'], 'moisIdeal' => 1, 'latitude' => 25.2, 'longitude' => 55.3,
                'zones' => ['Moyen-Orient', 'Golfe persique', 'Asie', 'Désert'],
                'photoAlt' => 'Gratte-ciel de Dubaï illuminés se reflétant dans l’eau à la nuit tombée',
                'accroche' => 'Soleil d’hiver, gratte-ciel et désert, à six heures de vol.',
                'description' => 'Le soleil garanti en plein hiver : plages et resorts, la Burj Khalifa, les souks de l\'or et des épices, puis le désert en 4x4 au coucher du soleil. Une destination de choix pour les incentives et les séminaires.',
                'pointsForts' => ['Plus de 20 °C de novembre à mars', 'Safari dans le désert', 'Resorts et plages', 'Incentives et salons'],
                'tempsDeTrajet' => ['Paris' => 405, 'Lyon' => 400, 'Bordeaux' => 480],
                'temperatures' => [19, 20, 23, 27, 31, 33, 35, 35, 33, 29, 25, 21],
                'pluie' => [15, 25, 20, 8, 1, 0, 1, 0, 0, 1, 3, 15],
                'saisons' => self::SAISONS_GOLFE,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'oman' => [
                'pays' => 'Oman', 'region' => 'Mascate et Charqiya', 'ville' => 'Mascate', 'photo' => 'oman',
                'nom' => 'Sultanat d\'Oman', 'typesDeVoyage' => ['circuit', 'noces', 'sejour'], 'moisIdeal' => 11, 'latitude' => 23.6, 'longitude' => 58.6,
                'zones' => ['Moyen-Orient', 'Golfe persique', 'Péninsule arabique', 'Asie', 'Désert'],
                'photoAlt' => 'Dunes de sable doré parsemées d’arbustes dans le désert d’Oman',
                'accroche' => 'Forts, wadis turquoise et dunes de Wahiba, un Orient préservé.',
                'description' => 'Un Orient authentique : Mascate et sa grande mosquée, les forts de Nizwa, les wadis aux vasques turquoise, une nuit dans les dunes de Wahiba et les tortues de Ras al-Jinz. Un circuit doux pour des noces ou un premier voyage au Moyen-Orient.',
                'pointsForts' => ['Wadis et baignade', 'Nuit dans le désert', 'Forts et souks', 'Ponte des tortues'],
                'tempsDeTrajet' => ['Paris' => 420, 'Lyon' => 540, 'Bordeaux' => 560],
                'temperatures' => [21, 22, 25, 29, 33, 34, 34, 32, 31, 29, 25, 22],
                'pluie' => [10, 20, 10, 10, 0, 0, 5, 0, 0, 5, 10, 10],
                'saisons' => self::SAISONS_GOLFE,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function prestataires(): array
    {
        return [
            'palais-orient' => ['nom' => 'Palais & Déserts d\'Orient', 'type' => 'hebergeur', 'contactNom' => 'Rania Haddad', 'email' => 'reservations@palais-orient.example', 'telephone' => '+962 6 000 0023', 'conditionsAnnulation' => 'Gratuite jusqu\'à 21 jours avant l\'arrivée ; 50 % ensuite, 100 % à moins de 7 jours.'],
            'orient-connexions' => [
                'nom' => 'Orient Connexions', 'type' => 'transporteur', 'contactNom' => 'Pôle Moyen-Orient', 'email' => 'tarifs@orient-connexions.example', 'telephone' => '+33 1 00 00 00 24',
                'conditionsAnnulation' => 'Frais de 100 € par personne jusqu\'à 21 jours avant le départ, puis non remboursable.',
                'vols' => [
                    'Istanbul' => ['Paris' => [150, 400], 'Lyon' => [160, 420], 'Bordeaux' => [180, 440]],
                    'Amman' => ['Paris' => [250, 550], 'Lyon' => [280, 600], 'Bordeaux' => [290, 620]],
                    'Dubaï' => ['Paris' => [350, 800], 'Lyon' => [380, 850], 'Bordeaux' => [400, 880]],
                    'Mascate' => ['Paris' => [450, 950], 'Lyon' => [500, 1000], 'Bordeaux' => [520, 1050]],
                ],
                // Jordanie et Oman : chauffeur pour tout le circuit, pas seulement l'aéroport.
                'transferts' => ['Istanbul' => [20, 50], 'Amman' => [90, 220], 'Dubaï' => [20, 50], 'Mascate' => [110, 260]],
            ],
            'desert-explorers' => ['nom' => 'Désert Explorers', 'type' => 'activite', 'contactNom' => 'Salem Al Balushi', 'email' => 'booking@desert-explorers.example', 'telephone' => '+968 24 000 025', 'conditionsAnnulation' => 'Gratuite jusqu\'à 48 heures avant ; remboursée en cas de tempête de sable.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function logements(): array
    {
        return [
            // Istanbul
            ['nom' => 'Hôtel Sultanahmet, chambre double', 'destination' => 'istanbul', 'prestataire' => 'palais-orient', 'categorie' => 'Hôtel de charme', 'capacite' => 2, 'chambres' => 1, 'prix' => 120, 'equipements' => ['wifi', 'climatisation', 'petit-déjeuner', 'terrasse'], 'envies' => ['culture', 'romantique'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Appartement Galata', 'destination' => 'istanbul', 'prestataire' => 'palais-orient', 'categorie' => 'Appartement', 'capacite' => 5, 'chambres' => 2, 'prix' => 130, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'climatisation', 'cuisine équipée'], 'envies' => ['culture', 'famille', 'gastronomie'], 'photos' => ['appartement']],
            ['nom' => 'Bosphorus Congress Hotel', 'destination' => 'istanbul', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 100, 'chambres' => 50, 'prix' => 5500, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 250 places', 'salles de sous-commission', 'traduction simultanée', 'spa', 'restauration de groupe'], 'envies' => ['culture', 'détente'], 'photos' => ['seminaire', 'hotel-ville'], 'description' => 'Cinquante chambres en bloc face au Bosphore et un centre de congrès de 250 places.'],
            // Jordanie
            ['nom' => 'Hôtel de Wadi Musa, aux portes de Pétra', 'destination' => 'jordanie', 'prestataire' => 'palais-orient', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 140, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'demi-pension', 'piscine'], 'envies' => ['culture'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Camp bédouin du Wadi Rum, tente bulle', 'destination' => 'jordanie', 'prestataire' => 'palais-orient', 'categorie' => 'Camp dans le désert', 'capacite' => 2, 'chambres' => 1, 'prix' => 180, 'equipements' => ['pension complète', 'toit transparent', 'salle de bains privée'], 'envies' => ['aventure', 'romantique', 'nature', 'désert'], 'photos' => ['desert'], 'saisons' => [[1, 6, 1.0], [9, 12, 1.0]]],
            ['nom' => 'Resort de la mer Morte, chambre familiale', 'destination' => 'jordanie', 'prestataire' => 'palais-orient', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 260, 'accessible' => true, 'equipements' => ['wifi', 'spa', 'piscine', 'accès plage', 'demi-pension'], 'envies' => ['détente', 'famille'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Hôtel d\'Amman, bloc groupe', 'destination' => 'jordanie', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 40, 'chambres' => 20, 'prix' => 2400, 'accessible' => true, 'equipements' => ['wifi', 'restauration de groupe', 'salle de réunion 60 places'], 'envies' => ['culture'], 'photos' => ['hotel-ville']],
            // Dubaï
            ['nom' => 'Resort de Jumeirah, chambre vue mer', 'destination' => 'dubai', 'prestataire' => 'palais-orient', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 380, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'spa', 'accès plage', 'club enfants'], 'envies' => ['plage', 'détente', 'famille', 'romantique'], 'photos' => ['plage']],
            ['nom' => 'Hôtel Downtown, chambre double', 'destination' => 'dubai', 'prestataire' => 'palais-orient', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 190, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'vue sur la Burj Khalifa'], 'envies' => ['shopping', 'culture'], 'photos' => ['hotel-ville']],
            ['nom' => 'Resort du désert de Dubaï, villa avec piscine privée', 'destination' => 'dubai', 'prestataire' => 'palais-orient', 'categorie' => 'Villa de luxe', 'capacite' => 2, 'chambres' => 1, 'prix' => 620, 'sejourMinimum' => 2, 'equipements' => ['wifi', 'climatisation', 'piscine privée', 'demi-pension', 'spa'], 'envies' => ['romantique', 'détente', 'désert'], 'photos' => ['desert', 'villa'], 'description' => 'Villa face aux dunes, dîner sous les étoiles et balade à dos de dromadaire au coucher du soleil.'],
            ['nom' => 'Dubai Marina Convention Hotel', 'destination' => 'dubai', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 150, 'chambres' => 75, 'prix' => 11000, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 300 places', 'salles de sous-commission', 'traduction simultanée', 'piscine', 'restauration de groupe'], 'envies' => ['détente', 'plage'], 'photos' => ['seminaire', 'hotel-piscine'], 'description' => 'Soixante-quinze chambres en bloc et une salle plénière de 300 places sur la marina, pour les incentives et les conventions.'],
            // Oman
            ['nom' => 'Hôtel de Mascate, chambre vue mer', 'destination' => 'oman', 'prestataire' => 'palais-orient', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 280, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'spa', 'accès plage'], 'envies' => ['détente', 'plage', 'romantique'], 'photos' => ['plage']],
            ['nom' => 'Camp de luxe des Wahiba Sands', 'destination' => 'oman', 'prestataire' => 'palais-orient', 'categorie' => 'Camp dans le désert', 'capacite' => 2, 'chambres' => 1, 'prix' => 240, 'equipements' => ['pension complète', 'salle de bains privée', 'terrasse'], 'envies' => ['aventure', 'romantique', 'désert'], 'photos' => ['desert']],
            ['nom' => 'Maison d\'hôtes de Nizwa', 'destination' => 'oman', 'prestataire' => 'palais-orient', 'categorie' => 'Maison d\'hôtes', 'capacite' => 4, 'chambres' => 2, 'prix' => 120, 'equipements' => ['wifi', 'climatisation', 'demi-pension'], 'envies' => ['culture', 'famille'], 'photos' => ['riad']],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function activites(): array
    {
        return [
            // Istanbul
            ['nom' => 'Visite guidée de Sainte-Sophie et du Grand Bazar', 'destination' => 'istanbul', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 50, 'duree' => 240, 'envies' => ['culture', 'shopping'], 'photos' => ['visite-guidee']],
            ['nom' => 'Croisière sur le Bosphore', 'destination' => 'istanbul', 'prestataire' => 'oceanes', 'categorie' => 'Croisière', 'capacite' => 80, 'prix' => 35, 'duree' => 120, 'accessible' => true, 'envies' => ['romantique', 'famille'], 'photos' => ['croisiere-fluviale']],
            ['nom' => 'Balade gourmande dans les marchés d\'Istanbul', 'destination' => 'istanbul', 'prestataire' => 'saveurs', 'categorie' => 'Atelier culinaire', 'capacite' => 12, 'prix' => 65, 'duree' => 210, 'envies' => ['gastronomie'], 'photos' => ['marche']],
            // Jordanie
            ['nom' => 'Visite guidée de Pétra', 'destination' => 'jordanie', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 95, 'duree' => 420, 'envies' => ['culture', 'aventure'], 'photos' => ['visite-guidee']],
            ['nom' => 'Safari en 4x4 dans le Wadi Rum', 'destination' => 'jordanie', 'prestataire' => 'desert-explorers', 'categorie' => 'Excursion en 4x4', 'capacite' => 6, 'prix' => 70, 'duree' => 240, 'envies' => ['aventure', 'nature', 'désert'], 'photos' => ['desert']],
            ['nom' => 'Flottaison et boue de la mer Morte', 'destination' => 'jordanie', 'prestataire' => 'bien-etre', 'categorie' => 'Bien-être', 'capacite' => 30, 'prix' => 40, 'duree' => 180, 'accessible' => true, 'envies' => ['détente', 'famille'], 'photos' => ['spa']],
            // Dubaï
            ['nom' => 'Safari dans le désert au coucher du soleil', 'destination' => 'dubai', 'prestataire' => 'desert-explorers', 'categorie' => 'Excursion en 4x4', 'capacite' => 30, 'prix' => 85, 'duree' => 360, 'ageMinimum' => 5, 'envies' => ['aventure', 'famille', 'désert'], 'photos' => ['desert']],
            ['nom' => 'Visite guidée des souks et du vieux Dubaï', 'destination' => 'dubai', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 45, 'duree' => 180, 'envies' => ['culture', 'shopping', 'gastronomie'], 'photos' => ['marche']],
            // Oman
            ['nom' => 'Baignade guidée au Wadi Shab', 'destination' => 'oman', 'prestataire' => 'desert-explorers', 'categorie' => 'Randonnée', 'capacite' => 12, 'prix' => 75, 'duree' => 300, 'ageMinimum' => 8, 'envies' => ['nature', 'aventure', 'sport'], 'photos' => ['randonnee']],
            ['nom' => 'Observation nocturne des tortues de Ras al-Jinz', 'destination' => 'oman', 'prestataire' => 'guides-monde', 'categorie' => 'Observation de la faune', 'capacite' => 20, 'prix' => 35, 'duree' => 120, 'envies' => ['nature', 'famille'], 'photos' => ['faune']],
        ];
    }
}
