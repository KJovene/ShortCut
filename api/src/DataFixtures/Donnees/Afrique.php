<?php

namespace App\DataFixtures\Donnees;

use App\DataFixtures\UtilisateurFixtures;

/**
 * Catalogue de démo, Afrique et océan Indien : 10 destinations, du Nil aux Seychelles.
 * Mêmes conventions que Catalogue (prix en euros, saisons, climat mois par mois).
 * Volontairement non couverts : au Sénégal, l'écolodge du Sine Saloum fermé d'août à octobre (saison des pluies).
 */
final class Afrique
{
    /** Safari en Afrique de l'Est : grande migration de juillet à octobre, pluies longues en avril et mai. */
    private const SAISONS_SAFARI = [[1, 2, 1.1], [3, 5, 0.8], [6, 6, 1.0], [7, 10, 1.3], [11, 11, 0.9], [12, 12, 1.2]];

    /** @return array<string, array<string, mixed>> */
    public static function destinations(): array
    {
        return [
            'egypte' => [
                'pays' => 'Égypte', 'region' => 'Haute-Égypte, vallée du Nil', 'ville' => 'Louxor', 'photo' => 'egypte',
                'nom' => 'Égypte, croisière sur le Nil', 'typesDeVoyage' => ['circuit', 'groupe'], 'moisIdeal' => 2, 'latitude' => 25.7, 'longitude' => 32.6,
                'zones' => ['Afrique', 'Afrique du Nord', 'Moyen-Orient', 'Proche-Orient'],
                'photoAlt' => 'Felouque à voile blanche sur le Nil au pied d’une colline de sable',
                'accroche' => 'Temples de Louxor, vallée des Rois et croisière sur le Nil en hiver.',
                'description' => 'Le berceau des pharaons, de Louxor à Assouan : temples de Karnak, vallée des Rois, Abou Simbel et croisière sur le Nil en bateau ou en dahabieh. Un grand classique de l\'hiver, idéal en groupe avec un égyptologue francophone.',
                'pointsForts' => ['Croisière sur le Nil', 'Vallée des Rois', 'Égyptologue francophone', 'Soleil sec en hiver'],
                'tempsDeTrajet' => ['Paris' => 290, 'Lyon' => 330, 'Bordeaux' => 360],
                'temperatures' => [14, 16, 20, 25, 29, 32, 32, 32, 30, 26, 20, 16],
                'pluie' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                'saisons' => [[1, 3, 1.2], [4, 5, 0.9], [6, 9, 0.75], [10, 10, 1.0], [11, 12, 1.2]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'djerba' => [
                'pays' => 'Tunisie', 'region' => 'Médenine', 'ville' => 'Djerba', 'photo' => 'djerba',
                'nom' => 'Djerba, Tunisie', 'typesDeVoyage' => ['sejour', 'groupe'], 'moisIdeal' => 6, 'latitude' => 33.8, 'longitude' => 10.9,
                'zones' => ['Afrique', 'Afrique du Nord', 'Maghreb', 'Méditerranée', 'Îles'],
                'photoAlt' => 'Plage de sable blanc et mer turquoise à Djerba, sous une paillote',
                'accroche' => 'Plages de sable fin, médinas et thalasso, à 2 h 30 de Paris.',
                'description' => 'L\'île douce du sud tunisien : longues plages de sable fin, villages blancs, souks de Houmt Souk et thalassothérapie. Des clubs familiaux à petit prix, à deux heures et demie de vol.',
                'pointsForts' => ['Plages de sable fin', 'Clubs familiaux', 'Thalassothérapie', 'Excursion dans le désert'],
                'tempsDeTrajet' => ['Paris' => 165, 'Lyon' => 140, 'Bordeaux' => 175],
                'temperatures' => [12, 13, 15, 18, 22, 26, 28, 29, 27, 23, 18, 14],
                'pluie' => [30, 20, 20, 10, 5, 1, 0, 1, 15, 45, 35, 40],
                'saisons' => [[1, 3, 0.75], [4, 6, 1.0], [7, 8, 1.4], [9, 10, 1.0], [11, 12, 0.75]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'senegal' => [
                'pays' => 'Sénégal', 'region' => 'Petite-Côte et Sine Saloum', 'ville' => 'Saly', 'photo' => 'senegal',
                'nom' => 'Sénégal, Petite-Côte', 'typesDeVoyage' => ['sejour', 'groupe', 'circuit'], 'moisIdeal' => 1, 'latitude' => 14.4, 'longitude' => -17.0,
                'zones' => ['Afrique', 'Afrique de l\'Ouest', 'Atlantique'],
                'photoAlt' => 'Pirogues colorées sur le fleuve à Saint-Louis du Sénégal',
                'accroche' => 'Teranga, pirogues colorées et mangroves du Sine Saloum, sans décalage horaire.',
                'description' => 'La Teranga sénégalaise à six heures de vol, sans décalage horaire : plages de la Petite-Côte, île de Gorée, Lac Rose et mangroves du Sine Saloum en pirogue. Soleil sec de novembre à mai.',
                'pointsForts' => ['Sans décalage horaire', 'Mangroves du Sine Saloum', 'Île de Gorée', 'Francophone'],
                'tempsDeTrajet' => ['Paris' => 330, 'Lyon' => 400, 'Bordeaux' => 420],
                'temperatures' => [24, 25, 25, 25, 26, 27, 28, 28, 28, 28, 27, 25],
                'pluie' => [0, 0, 0, 0, 0, 15, 70, 180, 140, 30, 2, 0],
                'saisons' => [[1, 4, 1.1], [5, 6, 0.9], [7, 10, 0.75], [11, 11, 1.0], [12, 12, 1.25]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'cap-vert' => [
                'pays' => 'Cap-Vert', 'region' => 'Île de Sal', 'ville' => 'Santa Maria', 'photo' => 'cap-vert',
                'nom' => 'Cap-Vert, île de Sal', 'typesDeVoyage' => ['sejour', 'groupe'], 'moisIdeal' => 3, 'latitude' => 16.6, 'longitude' => -22.9,
                'zones' => ['Afrique', 'Afrique de l\'Ouest', 'Atlantique', 'Îles'],
                'photoAlt' => 'Plage de sable et vagues turquoise près de Santa Maria, sur l’île de Sal',
                'accroche' => 'Plages de sable blanc, kitesurf et morna, au soleil toute l’année.',
                'description' => 'Un archipel au large de l\'Afrique, au soleil toute l\'année : plages de Santa Maria, kitesurf, plongée avec les tortues, salines de Pedra de Lume et musique morna. Seulement une heure de décalage.',
                'pointsForts' => ['Soleil toute l\'année', 'Kitesurf et plongée', 'Une heure de décalage', 'Ambiance créole'],
                'tempsDeTrajet' => ['Paris' => 360, 'Lyon' => 380, 'Bordeaux' => 420],
                'temperatures' => [22, 22, 22, 23, 23, 24, 25, 27, 27, 27, 25, 23],
                'pluie' => [3, 2, 1, 0, 0, 0, 5, 20, 30, 10, 5, 3],
                'saisons' => [[1, 4, 1.15], [5, 6, 0.9], [7, 8, 1.1], [9, 10, 0.85], [11, 11, 1.0], [12, 12, 1.3]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'kenya' => [
                'pays' => 'Kenya', 'region' => 'Masai Mara et Amboseli', 'ville' => 'Nairobi', 'photo' => 'kenya',
                'nom' => 'Kenya, safari', 'typesDeVoyage' => ['circuit', 'noces', 'groupe'], 'moisIdeal' => 8, 'latitude' => -1.4, 'longitude' => 35.5,
                'zones' => ['Afrique', 'Afrique de l\'Est', 'Savane'],
                'photoAlt' => 'Une lionne couchée dans les hautes herbes du Masai Mara',
                'accroche' => 'Grande migration du Masai Mara et éléphants au pied du Kilimandjaro.',
                'description' => 'Le safari par excellence : les Big Five au Masai Mara, la grande migration des gnous de juillet à octobre, les éléphants d\'Amboseli face au Kilimandjaro. Nuits en lodge ou sous tente de luxe, en famille, en groupe ou en voyage de noces.',
                'pointsForts' => ['Safari de 7 à 10 jours', 'Grande migration de juillet à octobre', 'Camps de tentes de luxe', 'Guides naturalistes'],
                'tempsDeTrajet' => ['Paris' => 510, 'Lyon' => 660, 'Bordeaux' => 690],
                'temperatures' => [21, 21, 21, 20, 19, 18, 17, 18, 19, 20, 20, 20],
                'pluie' => [60, 60, 100, 190, 120, 30, 20, 25, 30, 60, 140, 90],
                'saisons' => self::SAISONS_SAFARI,
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'zanzibar' => [
                'pays' => 'Tanzanie', 'region' => 'Archipel de Zanzibar', 'ville' => 'Zanzibar', 'photo' => 'zanzibar',
                'nom' => 'Zanzibar, Tanzanie', 'typesDeVoyage' => ['noces', 'sejour'], 'moisIdeal' => 9, 'latitude' => -6.2, 'longitude' => 39.2,
                'zones' => ['Afrique', 'Afrique de l\'Est', 'Océan Indien', 'Îles'],
                'photoAlt' => 'Boutre à voile blanche sur l’eau turquoise au large de Zanzibar',
                'accroche' => 'Plages de sable blanc, Stone Town et boutres au coucher du soleil.',
                'description' => 'L\'île aux épices : ruelles de Stone Town, plages de sable blanc de Nungwi et Kendwa, boutres au coucher du soleil et snorkeling à Mnemba. Se combine parfaitement avec un safari en Tanzanie ou au Kenya.',
                'pointsForts' => ['Plages de sable blanc', 'Stone Town classée à l\'UNESCO', 'Snorkeling à Mnemba', 'Extension idéale d\'un safari'],
                'tempsDeTrajet' => ['Paris' => 600, 'Lyon' => 690, 'Bordeaux' => 720],
                'temperatures' => [28, 28, 28, 27, 26, 25, 24, 24, 25, 26, 27, 28],
                'pluie' => [75, 60, 150, 350, 250, 50, 45, 40, 50, 90, 200, 150],
                'saisons' => [[1, 2, 1.1], [3, 5, 0.75], [6, 6, 1.0], [7, 10, 1.2], [11, 11, 0.9], [12, 12, 1.3]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'le-cap' => [
                'pays' => 'Afrique du Sud', 'region' => 'Cap-Occidental', 'ville' => 'Le Cap', 'photo' => 'le-cap',
                'nom' => 'Le Cap, Afrique du Sud', 'typesDeVoyage' => ['circuit', 'noces', 'entreprise'], 'moisIdeal' => 12, 'latitude' => -33.9, 'longitude' => 18.4,
                'zones' => ['Afrique', 'Afrique australe', 'Hémisphère sud'],
                'photoAlt' => 'La montagne de la Table coiffée d’un nuage au Cap',
                'accroche' => 'Montagne de la Table, route des vins et manchots, l’été en hiver.',
                'description' => 'L\'été austral pendant notre hiver : la montagne de la Table, le cap de Bonne-Espérance, les manchots de Boulders Beach, la route des vins de Stellenbosch et la Garden Route. Un circuit varié, et une ville de congrès réputée.',
                'pointsForts' => ['Été de novembre à mars', 'Route des vins', 'Garden Route en circuit', 'Centre de congrès'],
                'tempsDeTrajet' => ['Paris' => 690, 'Lyon' => 840, 'Bordeaux' => 870],
                'temperatures' => [21, 21, 20, 18, 16, 14, 13, 14, 15, 17, 19, 20],
                'pluie' => [15, 15, 20, 40, 70, 95, 90, 80, 45, 30, 20, 15],
                'saisons' => Catalogue::SAISONS_HEMISPHERE_SUD,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'namibie' => [
                'pays' => 'Namibie', 'region' => 'Namib et Etosha', 'ville' => 'Windhoek', 'photo' => 'namibie',
                'nom' => 'Namibie', 'typesDeVoyage' => ['circuit', 'noces'], 'moisIdeal' => 7, 'latitude' => -23.0, 'longitude' => 16.5,
                'zones' => ['Afrique', 'Afrique australe', 'Désert', 'Savane', 'Hémisphère sud'],
                'photoAlt' => 'Arbres morts devant les dunes orangées du Deadvlei en Namibie',
                'accroche' => 'Dunes rouges de Sossusvlei et faune d’Etosha, en autotour.',
                'description' => 'Des paysages hors du temps : les dunes rouges de Sossusvlei, Swakopmund entre désert et océan, le Damaraland et le parc d\'Etosha où la faune se rassemble aux points d\'eau en saison sèche. Un autotour spectaculaire.',
                'pointsForts' => ['Autotour de 12 à 15 jours', 'Dunes de Sossusvlei', 'Safari à Etosha', 'Ciels étoilés'],
                'tempsDeTrajet' => ['Paris' => 660, 'Lyon' => 780, 'Bordeaux' => 800],
                'temperatures' => [24, 23, 22, 20, 17, 14, 14, 17, 20, 23, 24, 24],
                'pluie' => [80, 80, 80, 40, 8, 0, 0, 0, 2, 10, 25, 45],
                'saisons' => [[1, 3, 0.85], [4, 6, 1.0], [7, 10, 1.25], [11, 12, 1.0]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'maurice' => [
                'pays' => 'Maurice', 'region' => 'Côte nord', 'ville' => 'Grand Baie', 'photo' => 'maurice',
                'nom' => 'Île Maurice', 'typesDeVoyage' => ['noces', 'sejour'], 'moisIdeal' => 10, 'latitude' => -20.2, 'longitude' => 57.5,
                'zones' => ['Afrique', 'Océan Indien', 'Îles', 'Hémisphère sud'],
                'photoAlt' => 'Le Morne Brabant dominant le lagon calme de l\'île Maurice, avec un rivage arboré et des barques',
                'accroche' => 'Lagons turquoise, hôtels les pieds dans l’eau et accueil francophone.',
                'description' => 'Lagons protégés par la barrière de corail, plages de sable blanc, Le Morne et ses sentiers, Chamarel et la cuisine créole. Des hôtels les pieds dans l\'eau pour un voyage de noces, et des clubs enfants pour les familles.',
                'pointsForts' => ['Lagons turquoise', 'Hôtels les pieds dans l\'eau', 'Accueil francophone', 'Vol direct depuis Paris'],
                'tempsDeTrajet' => ['Paris' => 690, 'Lyon' => 780, 'Bordeaux' => 810],
                'temperatures' => [27, 27, 27, 26, 24, 22, 21, 21, 22, 23, 25, 26],
                'pluie' => [215, 240, 220, 120, 80, 60, 60, 55, 35, 40, 75, 150],
                'saisons' => [[1, 3, 0.9], [4, 6, 1.0], [7, 8, 1.15], [9, 9, 1.0], [10, 11, 1.1], [12, 12, 1.35]],
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'seychelles' => [
                'pays' => 'Seychelles', 'region' => 'Mahé, Praslin et La Digue', 'ville' => 'Mahé', 'photo' => 'seychelles',
                'nom' => 'Seychelles', 'typesDeVoyage' => ['noces', 'sejour'], 'moisIdeal' => 5, 'latitude' => -4.6, 'longitude' => 55.5,
                'zones' => ['Afrique', 'Océan Indien', 'Îles'],
                'photoAlt' => 'Îlot Saint-Pierre aux Seychelles : rochers de granit et cocotiers au milieu d\'une mer turquoise',
                'accroche' => 'Rochers de granit, plages de carte postale et tortues géantes.',
                'description' => 'Les plus belles plages du monde : rochers de granit rose de l\'Anse Source d\'Argent, vallée de Mai et ses cocos de mer, tortues géantes et combiné d\'îles Mahé, Praslin, La Digue. Le voyage de noces par excellence.',
                'pointsForts' => ['Combiné de trois îles', 'Plages de granit rose', 'Vallée de Mai', 'Villas pour les noces'],
                'tempsDeTrajet' => ['Paris' => 630, 'Lyon' => 780, 'Bordeaux' => 800],
                'temperatures' => [27, 28, 28, 28, 28, 27, 26, 26, 26, 27, 27, 27],
                'pluie' => [380, 260, 230, 180, 150, 100, 80, 90, 140, 200, 230, 300],
                'saisons' => [[1, 3, 1.0], [4, 5, 1.1], [6, 6, 0.95], [7, 8, 1.2], [9, 9, 0.95], [10, 11, 1.1], [12, 12, 1.35]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function prestataires(): array
    {
        return [
            'soleil-maghreb' => ['nom' => 'Soleil du Nil & du Maghreb', 'type' => 'hebergeur', 'contactNom' => 'Karim Mansour', 'email' => 'contrats@soleil-nil-maghreb.example', 'telephone' => '+216 75 000 026', 'conditionsAnnulation' => 'Gratuite jusqu\'à 14 jours avant l\'arrivée ; 30 % ensuite, 100 % à moins de 3 jours.'],
            'teranga' => ['nom' => 'Teranga Hôtels', 'type' => 'hebergeur', 'contactNom' => 'Awa Diop', 'email' => 'reservation@teranga-hotels.example', 'telephone' => '+221 33 000 0027', 'conditionsAnnulation' => 'Gratuite jusqu\'à 21 jours avant l\'arrivée, puis 50 %.'],
            'safari-lodges' => ['nom' => 'Safari Lodges d\'Afrique', 'type' => 'hebergeur', 'contactNom' => 'Joseph Kamau', 'email' => 'reservations@safari-lodges.example', 'telephone' => '+254 20 000 0028', 'conditionsAnnulation' => 'Acompte de 30 % non remboursable ; solde remboursable jusqu\'à 60 jours avant l\'arrivée.'],
            'iles-ocean-indien' => ['nom' => 'Îles de l\'océan Indien', 'type' => 'hebergeur', 'contactNom' => 'Nathalie Ramdin', 'email' => 'contact@iles-ocean-indien.example', 'telephone' => '+230 200 0029', 'conditionsAnnulation' => 'Gratuite jusqu\'à 30 jours avant l\'arrivée ; 50 % entre 30 et 15 jours, puis 100 %.'],
            'air-savane' => [
                'nom' => 'Air Savane', 'type' => 'transporteur', 'contactNom' => 'Pôle Afrique', 'email' => 'tarifs@air-savane.example', 'telephone' => '+33 1 00 00 00 30',
                'conditionsAnnulation' => 'Frais de 150 € par personne jusqu\'à 30 jours avant le départ, puis non remboursable.',
                'vols' => [
                    'Louxor' => ['Paris' => [250, 600], 'Lyon' => [280, 640], 'Bordeaux' => [300, 660]],
                    'Djerba' => ['Paris' => [150, 400], 'Lyon' => [160, 420], 'Bordeaux' => [180, 450]],
                    'Saly' => ['Paris' => [350, 750], 'Lyon' => [400, 820], 'Bordeaux' => [420, 850]],
                    'Santa Maria' => ['Paris' => [300, 650], 'Lyon' => [340, 700], 'Bordeaux' => [360, 720]],
                    'Nairobi' => ['Paris' => [600, 1200], 'Lyon' => [680, 1300], 'Bordeaux' => [700, 1350]],
                    'Zanzibar' => ['Paris' => [650, 1250], 'Lyon' => [700, 1350], 'Bordeaux' => [720, 1380]],
                    'Le Cap' => ['Paris' => [700, 1400], 'Lyon' => [780, 1500], 'Bordeaux' => [800, 1550]],
                    'Windhoek' => ['Paris' => [750, 1450], 'Lyon' => [800, 1550], 'Bordeaux' => [820, 1600]],
                    'Grand Baie' => ['Paris' => [700, 1300], 'Lyon' => [760, 1400], 'Bordeaux' => [780, 1450]],
                    'Mahé' => ['Paris' => [750, 1400], 'Lyon' => [800, 1500], 'Bordeaux' => [820, 1550]],
                ],
                // Safari, autotour et combiné d'îles : véhicule ou liaisons inter-îles comprises.
                'transferts' => ['Louxor' => [20, 50], 'Djerba' => [15, 40], 'Saly' => [30, 70], 'Santa Maria' => [15, 35], 'Nairobi' => [250, 600], 'Zanzibar' => [30, 70], 'Le Cap' => [25, 60], 'Windhoek' => [300, 700], 'Grand Baie' => [40, 90], 'Mahé' => [120, 300]],
            ],
            'rangers' => ['nom' => 'Rangers & Pisteurs', 'type' => 'guide', 'contactNom' => 'Thabo Nkosi', 'email' => 'safaris@rangers-pisteurs.example', 'telephone' => '+27 21 000 0031', 'conditionsAnnulation' => 'Gratuite jusqu\'à 30 jours avant ; 50 % ensuite.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function logements(): array
    {
        return [
            // Égypte
            ['nom' => 'Bateau de croisière sur le Nil, cabine double', 'destination' => 'egypte', 'prestataire' => 'soleil-maghreb', 'categorie' => 'Bateau de croisière', 'capacite' => 2, 'chambres' => 1, 'prix' => 190, 'sejourMinimum' => 4, 'equipements' => ['climatisation', 'pension complète', 'piscine sur le pont'], 'envies' => ['culture', 'romantique', 'détente'], 'photos' => ['croisiere-fluviale'], 'description' => 'Quatre nuits de Louxor à Assouan, visites des temples comprises avec un égyptologue.'],
            ['nom' => 'Hôtel Winter Palace, Louxor', 'destination' => 'egypte', 'prestataire' => 'soleil-maghreb', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 210, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'jardin', 'petit-déjeuner'], 'envies' => ['culture', 'détente'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Hôtel de la rive ouest, chambre familiale', 'destination' => 'egypte', 'prestataire' => 'soleil-maghreb', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 85, 'equipements' => ['wifi', 'climatisation', 'demi-pension'], 'envies' => ['culture', 'famille'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Bateau de croisière privatisé, groupe', 'destination' => 'egypte', 'prestataire' => 'soleil-maghreb', 'categorie' => 'Bateau de croisière', 'capacite' => 40, 'chambres' => 20, 'prix' => 3200, 'sejourMinimum' => 4, 'equipements' => ['climatisation', 'pension complète', 'piscine sur le pont', 'salle commune'], 'envies' => ['culture'], 'photos' => ['croisiere-fluviale']],
            // Djerba
            ['nom' => 'Club Djerba Plage, chambre familiale', 'destination' => 'djerba', 'prestataire' => 'soleil-maghreb', 'categorie' => 'Club de vacances', 'capacite' => 4, 'chambres' => 1, 'prix' => 140, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'club enfants', 'tout compris', 'accès plage'], 'envies' => ['plage', 'famille', 'détente'], 'photos' => ['plage']],
            ['nom' => 'Hôtel thalasso Djerba, chambre double', 'destination' => 'djerba', 'prestataire' => 'soleil-maghreb', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 170, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'thalassothérapie', 'spa', 'demi-pension'], 'envies' => ['détente', 'romantique', 'plage'], 'photos' => ['spa', 'hotel-piscine']],
            ['nom' => 'Menzel traditionnel de Djerba', 'destination' => 'djerba', 'prestataire' => 'soleil-maghreb', 'categorie' => 'Maison d\'hôtes', 'capacite' => 6, 'chambres' => 3, 'prix' => 150, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'piscine', 'petit-déjeuner', 'patio'], 'envies' => ['culture', 'famille'], 'photos' => ['riad']],
            ['nom' => 'Resort Djerba, bloc groupe', 'destination' => 'djerba', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 60, 'chambres' => 30, 'prix' => 2900, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'tout compris', 'salle de réunion 80 places'], 'envies' => ['plage', 'détente'], 'photos' => ['hotel-piscine']],
            // Sénégal (l'écolodge ferme d'août à octobre)
            ['nom' => 'Hôtel de Saly, chambre vue mer', 'destination' => 'senegal', 'prestataire' => 'teranga', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 120, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'accès plage', 'demi-pension'], 'envies' => ['plage', 'détente', 'famille'], 'photos' => ['plage']],
            ['nom' => 'Écolodge du Sine Saloum', 'destination' => 'senegal', 'prestataire' => 'teranga', 'categorie' => 'Écolodge', 'capacite' => 2, 'chambres' => 1, 'prix' => 95, 'equipements' => ['pension complète', 'ventilateur', 'pirogue'], 'envies' => ['nature', 'romantique', 'aventure'], 'photos' => ['ecolodge'], 'saisons' => [[1, 4, 1.1], [5, 7, 0.9], [11, 11, 1.0], [12, 12, 1.25]]],
            ['nom' => 'Villa de la Somone', 'destination' => 'senegal', 'prestataire' => 'teranga', 'categorie' => 'Villa', 'capacite' => 8, 'chambres' => 4, 'prix' => 280, 'sejourMinimum' => 4, 'equipements' => ['wifi', 'piscine privée', 'cuisinière sur place', 'climatisation'], 'envies' => ['famille', 'détente', 'plage'], 'photos' => ['villa']],
            ['nom' => 'Village de la Petite-Côte, bloc groupe', 'destination' => 'senegal', 'prestataire' => 'teranga', 'categorie' => 'Village vacances', 'capacite' => 36, 'chambres' => 18, 'prix' => 1500, 'equipements' => ['wifi', 'piscine', 'pension complète', 'salle commune'], 'envies' => ['plage', 'culture'], 'photos' => ['hotel-piscine']],
            // Cap-Vert
            ['nom' => 'Resort Santa Maria, chambre double', 'destination' => 'cap-vert', 'prestataire' => 'teranga', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 160, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'tout compris', 'accès plage'], 'envies' => ['plage', 'détente', 'romantique'], 'photos' => ['plage']],
            ['nom' => 'Appartement face au ponton de Santa Maria', 'destination' => 'cap-vert', 'prestataire' => 'teranga', 'categorie' => 'Appartement', 'capacite' => 4, 'chambres' => 2, 'prix' => 110, 'sejourMinimum' => 4, 'equipements' => ['wifi', 'cuisine équipée', 'balcon vue mer'], 'envies' => ['plage', 'famille', 'sport'], 'photos' => ['appartement']],
            ['nom' => 'Club de Sal, bloc groupe', 'destination' => 'cap-vert', 'prestataire' => 'teranga', 'categorie' => 'Club de vacances', 'capacite' => 40, 'chambres' => 20, 'prix' => 2500, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'tout compris', 'animation'], 'envies' => ['plage', 'détente'], 'photos' => ['hotel-piscine']],
            // Kenya
            ['nom' => 'Camp de tentes de luxe du Masai Mara', 'destination' => 'kenya', 'prestataire' => 'safari-lodges', 'categorie' => 'Camp de safari', 'capacite' => 2, 'chambres' => 1, 'prix' => 520, 'sejourMinimum' => 2, 'equipements' => ['pension complète', 'safaris en 4x4 compris', 'douche extérieure', 'terrasse sur la savane'], 'envies' => ['nature', 'aventure', 'romantique', 'safari'], 'photos' => ['camp-safari'], 'description' => 'Tentes de luxe face à la rivière Mara, deux safaris par jour avec un guide naturaliste.'],
            ['nom' => 'Lodge d\'Amboseli, chambre familiale', 'destination' => 'kenya', 'prestataire' => 'safari-lodges', 'categorie' => 'Lodge', 'capacite' => 4, 'chambres' => 1, 'prix' => 380, 'accessible' => true, 'equipements' => ['pension complète', 'piscine', 'vue sur le Kilimandjaro'], 'envies' => ['nature', 'famille', 'safari'], 'photos' => ['ecolodge']],
            ['nom' => 'Hôtel de Nairobi, chambre double', 'destination' => 'kenya', 'prestataire' => 'safari-lodges', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 150, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'petit-déjeuner'], 'envies' => ['détente'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Lodge du lac Naivasha, bloc groupe', 'destination' => 'kenya', 'prestataire' => 'safari-lodges', 'categorie' => 'Lodge', 'capacite' => 30, 'chambres' => 15, 'prix' => 3900, 'equipements' => ['pension complète', 'piscine', 'salle commune'], 'envies' => ['nature', 'safari'], 'photos' => ['ecolodge']],
            // Zanzibar
            ['nom' => 'Villa de Nungwi, piscine privée', 'destination' => 'zanzibar', 'prestataire' => 'iles-ocean-indien', 'categorie' => 'Villa de luxe', 'capacite' => 2, 'chambres' => 1, 'prix' => 340, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'piscine privée', 'accès plage', 'petit-déjeuner'], 'envies' => ['romantique', 'plage', 'détente'], 'photos' => ['villa']],
            ['nom' => 'Hôtel de Stone Town, chambre double', 'destination' => 'zanzibar', 'prestataire' => 'iles-ocean-indien', 'categorie' => 'Hôtel de charme', 'capacite' => 2, 'chambres' => 1, 'prix' => 110, 'equipements' => ['wifi', 'climatisation', 'terrasse sur le toit', 'petit-déjeuner'], 'envies' => ['culture'], 'photos' => ['riad']],
            ['nom' => 'Bungalows de Kendwa, famille', 'destination' => 'zanzibar', 'prestataire' => 'iles-ocean-indien', 'categorie' => 'Bungalow', 'capacite' => 4, 'chambres' => 2, 'prix' => 170, 'equipements' => ['wifi', 'piscine', 'accès plage', 'demi-pension'], 'envies' => ['plage', 'famille'], 'photos' => ['plage']],
            // Le Cap
            ['nom' => 'Hôtel du Waterfront, chambre vue sur la Table', 'destination' => 'le-cap', 'prestataire' => 'safari-lodges', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 260, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'spa'], 'envies' => ['romantique', 'détente', 'gastronomie'], 'photos' => ['hotel-ville']],
            ['nom' => 'Domaine viticole de Stellenbosch', 'destination' => 'le-cap', 'prestataire' => 'safari-lodges', 'categorie' => 'Maison d\'hôtes', 'capacite' => 2, 'chambres' => 1, 'prix' => 190, 'equipements' => ['wifi', 'piscine', 'petit-déjeuner', 'dégustation de vins'], 'envies' => ['gastronomie', 'romantique', 'nature'], 'photos' => ['vignoble']],
            ['nom' => 'Lodge de la Garden Route, famille', 'destination' => 'le-cap', 'prestataire' => 'safari-lodges', 'categorie' => 'Lodge', 'capacite' => 5, 'chambres' => 2, 'prix' => 210, 'equipements' => ['wifi', 'cuisine équipée', 'terrasse', 'parking'], 'envies' => ['nature', 'famille'], 'photos' => ['ecolodge']],
            ['nom' => 'Cape Town Convention Hotel', 'destination' => 'le-cap', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 80, 'chambres' => 40, 'prix' => 4400, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 150 places', 'salles de sous-commission', 'restauration de groupe'], 'envies' => ['gastronomie', 'nature'], 'photos' => ['seminaire']],
            // Namibie
            ['nom' => 'Lodge du désert de Sossusvlei', 'destination' => 'namibie', 'prestataire' => 'safari-lodges', 'categorie' => 'Lodge', 'capacite' => 2, 'chambres' => 1, 'prix' => 300, 'equipements' => ['demi-pension', 'piscine', 'terrasse sous les étoiles'], 'envies' => ['nature', 'romantique', 'désert'], 'photos' => ['desert']],
            ['nom' => 'Camp de tentes d\'Etosha', 'destination' => 'namibie', 'prestataire' => 'safari-lodges', 'categorie' => 'Camp de safari', 'capacite' => 4, 'chambres' => 2, 'prix' => 340, 'equipements' => ['pension complète', 'point d\'eau éclairé', 'piscine'], 'envies' => ['nature', 'aventure', 'famille', 'safari'], 'photos' => ['camp-safari']],
            ['nom' => 'Guesthouse de Swakopmund', 'destination' => 'namibie', 'prestataire' => 'safari-lodges', 'categorie' => 'Maison d\'hôtes', 'capacite' => 3, 'chambres' => 1, 'prix' => 110, 'equipements' => ['wifi', 'petit-déjeuner', 'parking'], 'envies' => ['détente'], 'photos' => ['guesthouse']],
            // Île Maurice
            ['nom' => 'Hôtel les pieds dans l\'eau, suite junior', 'destination' => 'maurice', 'prestataire' => 'iles-ocean-indien', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 420, 'sejourMinimum' => 3, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'spa', 'accès plage', 'demi-pension'], 'envies' => ['romantique', 'plage', 'détente'], 'photos' => ['plage'], 'description' => 'Suite face au lagon, dîner sur la plage et massage en duo offerts aux jeunes mariés.'],
            ['nom' => 'Resort familial de Grand Baie', 'destination' => 'maurice', 'prestataire' => 'iles-ocean-indien', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 260, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'club enfants', 'demi-pension', 'accès plage'], 'envies' => ['plage', 'famille'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Villa créole de Trou aux Biches', 'destination' => 'maurice', 'prestataire' => 'iles-ocean-indien', 'categorie' => 'Villa', 'capacite' => 6, 'chambres' => 3, 'prix' => 390, 'sejourMinimum' => 5, 'equipements' => ['wifi', 'piscine privée', 'cuisinière sur place', 'jardin'], 'envies' => ['famille', 'détente'], 'photos' => ['villa']],
            // Seychelles
            ['nom' => 'Villa de Praslin sur la plage', 'destination' => 'seychelles', 'prestataire' => 'iles-ocean-indien', 'categorie' => 'Villa de luxe', 'capacite' => 2, 'chambres' => 1, 'prix' => 560, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'piscine privée', 'accès plage', 'petit-déjeuner'], 'envies' => ['romantique', 'plage', 'détente'], 'photos' => ['villa']],
            ['nom' => 'Guesthouse créole de La Digue', 'destination' => 'seychelles', 'prestataire' => 'iles-ocean-indien', 'categorie' => 'Maison d\'hôtes', 'capacite' => 3, 'chambres' => 1, 'prix' => 160, 'equipements' => ['wifi', 'demi-pension', 'vélos'], 'envies' => ['nature', 'plage'], 'photos' => ['guesthouse']],
            ['nom' => 'Hôtel de Beau Vallon, chambre familiale', 'destination' => 'seychelles', 'prestataire' => 'iles-ocean-indien', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 320, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'accès plage', 'demi-pension'], 'envies' => ['plage', 'famille'], 'photos' => ['hotel-piscine']],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function activites(): array
    {
        return [
            // Égypte
            ['nom' => 'Visite de la vallée des Rois avec un égyptologue', 'destination' => 'egypte', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 65, 'duree' => 300, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            ['nom' => 'Survol de Louxor en montgolfière', 'destination' => 'egypte', 'prestataire' => 'desert-explorers', 'categorie' => 'Montgolfière', 'capacite' => 20, 'prix' => 110, 'duree' => 180, 'ageMinimum' => 7, 'envies' => ['aventure', 'romantique'], 'photos' => ['montgolfiere']],
            // Djerba
            ['nom' => 'Excursion de deux jours dans le désert de Douz', 'destination' => 'djerba', 'prestataire' => 'desert-explorers', 'categorie' => 'Excursion en 4x4', 'capacite' => 20, 'prix' => 140, 'duree' => 1800, 'ageMinimum' => 6, 'envies' => ['aventure', 'famille', 'désert'], 'photos' => ['desert']],
            ['nom' => 'Cure de thalassothérapie, 3 soins', 'destination' => 'djerba', 'prestataire' => 'bien-etre', 'categorie' => 'Bien-être', 'capacite' => 2, 'prix' => 90, 'duree' => 180, 'ageMinimum' => 16, 'envies' => ['détente'], 'photos' => ['spa']],
            // Sénégal
            ['nom' => 'Pirogue dans les mangroves du Sine Saloum', 'destination' => 'senegal', 'prestataire' => 'guides-monde', 'categorie' => 'Excursion guidée', 'capacite' => 15, 'prix' => 50, 'duree' => 300, 'envies' => ['nature', 'famille'], 'photos' => ['canoe']],
            ['nom' => 'Visite guidée de l\'île de Gorée et de Dakar', 'destination' => 'senegal', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 55, 'duree' => 480, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            // Cap-Vert
            ['nom' => 'Cours de kitesurf', 'destination' => 'cap-vert', 'prestataire' => 'oceanes', 'categorie' => 'Sport nautique', 'capacite' => 6, 'prix' => 90, 'duree' => 180, 'ageMinimum' => 12, 'envies' => ['sport', 'plage', 'aventure'], 'photos' => ['surf']],
            ['nom' => 'Plongée avec les tortues', 'destination' => 'cap-vert', 'prestataire' => 'oceanes', 'categorie' => 'Plongée', 'capacite' => 8, 'prix' => 70, 'duree' => 150, 'ageMinimum' => 10, 'envies' => ['nature', 'sport'], 'photos' => ['plongee']],
            // Kenya
            ['nom' => 'Safari en 4x4 dans le Masai Mara', 'destination' => 'kenya', 'prestataire' => 'rangers', 'categorie' => 'Safari', 'capacite' => 6, 'prix' => 160, 'duree' => 480, 'ageMinimum' => 6, 'envies' => ['nature', 'aventure', 'famille', 'safari'], 'photos' => ['safari']],
            ['nom' => 'Survol du Masai Mara en montgolfière', 'destination' => 'kenya', 'prestataire' => 'rangers', 'categorie' => 'Montgolfière', 'capacite' => 16, 'prix' => 420, 'duree' => 240, 'ageMinimum' => 7, 'envies' => ['romantique', 'aventure', 'nature'], 'photos' => ['montgolfiere']],
            // Zanzibar
            ['nom' => 'Snorkeling à l\'atoll de Mnemba', 'destination' => 'zanzibar', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 15, 'prix' => 60, 'duree' => 300, 'ageMinimum' => 6, 'envies' => ['plage', 'nature', 'famille'], 'photos' => ['plongee']],
            ['nom' => 'Croisière en boutre au coucher du soleil', 'destination' => 'zanzibar', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 20, 'prix' => 45, 'duree' => 150, 'envies' => ['romantique', 'détente'], 'photos' => ['voilier']],
            ['nom' => 'Tour des épices et de Stone Town', 'destination' => 'zanzibar', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 20, 'prix' => 35, 'duree' => 300, 'envies' => ['culture', 'gastronomie'], 'photos' => ['marche']],
            // Le Cap
            ['nom' => 'Dégustation sur la route des vins de Stellenbosch', 'destination' => 'le-cap', 'prestataire' => 'saveurs', 'categorie' => 'Dégustation', 'capacite' => 14, 'prix' => 80, 'duree' => 420, 'ageMinimum' => 18, 'envies' => ['gastronomie', 'romantique'], 'photos' => ['vignoble']],
            ['nom' => 'Excursion guidée au cap de Bonne-Espérance', 'destination' => 'le-cap', 'prestataire' => 'rangers', 'categorie' => 'Excursion guidée', 'capacite' => 20, 'prix' => 95, 'duree' => 540, 'accessible' => true, 'envies' => ['nature', 'famille'], 'photos' => ['faune']],
            // Namibie
            ['nom' => 'Lever du soleil sur les dunes de Sossusvlei', 'destination' => 'namibie', 'prestataire' => 'rangers', 'categorie' => 'Excursion guidée', 'capacite' => 8, 'prix' => 120, 'duree' => 360, 'ageMinimum' => 8, 'envies' => ['nature', 'aventure', 'désert'], 'photos' => ['desert']],
            ['nom' => 'Safari guidé dans le parc d\'Etosha', 'destination' => 'namibie', 'prestataire' => 'rangers', 'categorie' => 'Safari', 'capacite' => 8, 'prix' => 140, 'duree' => 480, 'ageMinimum' => 6, 'envies' => ['nature', 'famille', 'safari'], 'photos' => ['safari']],
            // Île Maurice
            ['nom' => 'Catamaran à l\'île aux Cerfs', 'destination' => 'maurice', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 30, 'prix' => 85, 'duree' => 420, 'accessible' => true, 'envies' => ['plage', 'famille', 'romantique'], 'photos' => ['voilier']],
            ['nom' => 'Randonnée guidée au Morne Brabant', 'destination' => 'maurice', 'prestataire' => 'explora', 'categorie' => 'Randonnée', 'capacite' => 12, 'prix' => 50, 'duree' => 240, 'ageMinimum' => 12, 'envies' => ['nature', 'sport'], 'photos' => ['randonnee']],
            ['nom' => 'Massage en duo au spa du lagon', 'destination' => 'maurice', 'prestataire' => 'bien-etre', 'categorie' => 'Bien-être', 'capacite' => 2, 'prix' => 110, 'duree' => 90, 'ageMinimum' => 16, 'envies' => ['détente', 'romantique'], 'photos' => ['spa']],
            // Seychelles
            ['nom' => 'Balade guidée dans la vallée de Mai', 'destination' => 'seychelles', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 15, 'prix' => 60, 'duree' => 180, 'envies' => ['nature'], 'photos' => ['randonnee']],
            ['nom' => 'Journée en bateau vers les îles de Curieuse et Saint-Pierre', 'destination' => 'seychelles', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 20, 'prix' => 130, 'duree' => 420, 'envies' => ['plage', 'nature', 'romantique'], 'photos' => ['faune', 'bateau']],
        ];
    }
}
