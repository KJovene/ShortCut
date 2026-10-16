<?php

namespace App\DataFixtures\Donnees;

/**
 * Catalogue fictif de la démo (ticket #174) : 8 destinations, 15 prestataires, 30 logements, 20 activités.
 *
 * Prix en euros dans ce fichier (plus lisible), convertis en centimes au chargement.
 * Volontairement non couverts, pour tester le passage au conseiller :
 * - Islande : aucun tarif d'octobre à avril (logements et activités) ;
 * - Costa Rica, Cabinas Drake Bay : aucun tarif en septembre et octobre (fermeture saison des pluies).
 */
final class Catalogue
{
    public const ANNEE = 2027;
    public const VILLES_DE_DEPART = ['Paris', 'Lyon', 'Bordeaux'];

    /** Saisons tarifaires réutilisées : [mois de début, mois de fin, coefficient appliqué au prix de base]. */
    private const SAISONS_MEDITERRANEE = [[1, 3, 0.8], [4, 6, 1.0], [7, 8, 1.35], [9, 10, 1.0], [11, 12, 0.8]];

    /**
     * Destinations : climat mois par mois (température moyenne en °C, pluie en mm), temps de trajet en minutes.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function destinations(): array
    {
        return [
            'lisbonne' => [
                'pays' => 'Portugal', 'region' => 'Lisbonne et vallée du Tage', 'ville' => 'Lisbonne',
                'photo' => 'lisbonne',
                'description' => 'Capitale lumineuse sur les collines du Tage : ruelles de l\'Alfama, tramways jaunes, miradouros au coucher du soleil et cuisine généreuse. Idéale pour un long week-end, un voyage de groupe ou un séminaire.',
                'pointsForts' => ['Quartiers historiques à pied', 'Gastronomie et pastéis de nata', 'Plages à 30 minutes', 'Vols courts depuis la France'],
                'tempsDeTrajet' => ['Paris' => 150, 'Lyon' => 140, 'Bordeaux' => 115],
                'temperatures' => [12, 13, 15, 17, 19, 22, 24, 24, 23, 19, 15, 13],
                'pluie' => [100, 90, 60, 65, 50, 15, 5, 5, 30, 90, 110, 115],
                'saisons' => self::SAISONS_MEDITERRANEE,
                'miseEnAvant' => true,
            ],
            'marrakech' => [
                'pays' => 'Maroc', 'region' => 'Marrakech-Safi', 'ville' => 'Marrakech',
                'photo' => 'marrakech',
                'description' => 'La ville rouge : médina classée, souks, riads à patio et jardins, avec l\'Atlas en toile de fond. Une destination soleil toute l\'année, très demandée pour les groupes et les séminaires.',
                'pointsForts' => ['Riads de charme', 'Souks et place Jemaa el-Fna', 'Excursions dans l\'Atlas', 'Hammams et spas'],
                'tempsDeTrajet' => ['Paris' => 210, 'Lyon' => 195, 'Bordeaux' => 180],
                'temperatures' => [12, 14, 16, 18, 22, 25, 29, 29, 25, 21, 16, 13],
                'pluie' => [32, 38, 38, 39, 24, 7, 2, 3, 6, 24, 41, 31],
                'saisons' => [[1, 2, 1.0], [3, 5, 1.2], [6, 9, 0.8], [10, 12, 1.2]],
                'miseEnAvant' => true,
            ],
            'santorin' => [
                'pays' => 'Grèce', 'region' => 'Cyclades', 'ville' => 'Santorin',
                'photo' => 'santorin',
                'description' => 'Villages blancs accrochés à la caldeira, couchers de soleil sur la mer Égée et vins volcaniques : la destination romantique par excellence pour un voyage de noces.',
                'pointsForts' => ['Couchers de soleil à Oia', 'Suites avec piscine privée', 'Croisières dans la caldeira', 'Vignobles volcaniques'],
                'tempsDeTrajet' => ['Paris' => 240, 'Lyon' => 300, 'Bordeaux' => 330],
                'temperatures' => [12, 12, 14, 17, 21, 25, 27, 27, 24, 20, 17, 14],
                'pluie' => [70, 55, 45, 20, 10, 2, 1, 1, 10, 40, 55, 75],
                'saisons' => self::SAISONS_MEDITERRANEE,
                'miseEnAvant' => true,
            ],
            'tenerife' => [
                'pays' => 'Espagne', 'region' => 'Îles Canaries', 'ville' => 'Tenerife',
                'photo' => 'tenerife',
                'description' => 'L\'île du printemps éternel : plages au sud, volcan Teide classé à l\'UNESCO, baleines et dauphins toute l\'année. Le soleil d\'hiver à quatre heures de Paris, parfait pour les familles.',
                'pointsForts' => ['Plus de 20 °C en plein hiver', 'Parc national du Teide', 'Observation des cétacés', 'Hôtels familiaux et accessibles'],
                'tempsDeTrajet' => ['Paris' => 280, 'Lyon' => 300, 'Bordeaux' => 270],
                'temperatures' => [19, 19, 20, 20, 21, 23, 25, 26, 26, 24, 22, 20],
                'pluie' => [30, 30, 25, 10, 3, 1, 0, 1, 5, 20, 40, 40],
                'saisons' => [[1, 3, 1.2], [4, 6, 0.9], [7, 8, 1.1], [9, 10, 0.9], [11, 12, 1.2]],
                'miseEnAvant' => false,
            ],
            'rome' => [
                'pays' => 'Italie', 'region' => 'Latium', 'ville' => 'Rome',
                'photo' => 'rome',
                'description' => 'Trois mille ans d\'histoire à ciel ouvert : Colisée, forums, places baroques et trattorias du Trastevere. Une valeur sûre pour les voyages de groupe, scolaires ou d\'entreprise.',
                'pointsForts' => ['Colisée et Forum romain', 'Cuisine italienne', 'Hébergements pour groupes', 'Centre de congrès'],
                'tempsDeTrajet' => ['Paris' => 125, 'Lyon' => 85, 'Bordeaux' => 145],
                'temperatures' => [8, 9, 12, 15, 19, 23, 26, 26, 22, 18, 13, 9],
                'pluie' => [70, 75, 60, 70, 50, 30, 20, 30, 70, 110, 110, 85],
                'saisons' => self::SAISONS_MEDITERRANEE,
                'miseEnAvant' => false,
            ],
            'islande' => [
                'pays' => 'Islande', 'region' => 'Sud et Nord de l\'Islande', 'ville' => 'Reykjavik',
                'photo' => 'islande',
                'description' => 'Cascades, glaciers, plages de sable noir et sources chaudes : l\'Islande se découvre en circuit, de Reykjavik à Vík puis jusqu\'à Akureyri. Saison des circuits de mai à septembre.',
                'pointsForts' => ['Circuit de 7 à 10 jours', 'Cascades et glaciers', 'Baignade dans les sources chaudes', 'Observation des baleines'],
                'tempsDeTrajet' => ['Paris' => 205, 'Lyon' => 240, 'Bordeaux' => 250],
                'temperatures' => [0, 0, 1, 3, 7, 10, 12, 11, 8, 5, 2, 0],
                'pluie' => [90, 85, 85, 60, 45, 50, 50, 60, 70, 90, 85, 90],
                // Circuits uniquement de mai à septembre : le reste de l'année n'a aucun tarif.
                'saisons' => [[5, 5, 0.9], [6, 8, 1.3], [9, 9, 1.0]],
                'miseEnAvant' => false,
            ],
            'bali' => [
                'pays' => 'Indonésie', 'region' => 'Bali', 'ville' => 'Ubud',
                'photo' => 'bali',
                'description' => 'Rizières en terrasses, temples, plages de surf et villas avec piscine : Bali marie culture et détente. Un classique des voyages de noces, plus sec d\'avril à octobre.',
                'pointsForts' => ['Villas avec piscine privée', 'Temples et rizières', 'Surf à Canggu', 'Massages balinais'],
                'tempsDeTrajet' => ['Paris' => 1080, 'Lyon' => 1140, 'Bordeaux' => 1170],
                'temperatures' => [27, 27, 27, 28, 28, 27, 27, 27, 27, 28, 28, 27],
                'pluie' => [350, 300, 240, 100, 90, 70, 60, 40, 50, 110, 180, 290],
                'saisons' => [[1, 3, 0.85], [4, 6, 1.0], [7, 8, 1.3], [9, 9, 1.0], [10, 11, 0.85], [12, 12, 1.3]],
                'miseEnAvant' => true,
            ],
            'costa-rica' => [
                'pays' => 'Costa Rica', 'region' => 'Alajuela et Puntarenas', 'ville' => 'La Fortuna',
                'photo' => 'costa-rica',
                'description' => 'Volcan Arenal, forêts de nuages de Monteverde et côte sauvage de la péninsule d\'Osa : un circuit nature au milieu d\'une faune exceptionnelle. Saison sèche de décembre à avril.',
                'pointsForts' => ['Circuit nature de 10 à 14 jours', 'Volcans et sources chaudes', 'Faune et forêts tropicales', 'Écolodges'],
                'tempsDeTrajet' => ['Paris' => 660, 'Lyon' => 780, 'Bordeaux' => 780],
                'temperatures' => [24, 25, 26, 27, 27, 26, 26, 26, 26, 25, 25, 24],
                'pluie' => [150, 90, 60, 80, 250, 300, 280, 300, 330, 350, 250, 200],
                'saisons' => [[1, 4, 1.2], [5, 11, 0.9], [12, 12, 1.2]],
                'miseEnAvant' => false,
            ],
        ];
    }

    /**
     * Prestataires. Les transporteurs portent les fourchettes de prix des vols (aller-retour par personne, en euros).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function prestataires(): array
    {
        return [
            // Hébergeurs
            'azur' => ['nom' => 'Hôtels Azur Méditerranée', 'type' => 'hebergeur', 'contactNom' => 'Sofia Almeida', 'email' => 'reservations@azur-hotels.example', 'telephone' => '+351 21 000 00 01', 'conditionsAnnulation' => 'Gratuite jusqu\'à 14 jours avant l\'arrivée, puis 50 % du séjour.'],
            'riads' => ['nom' => 'Riads & Kasbahs du Sud', 'type' => 'hebergeur', 'contactNom' => 'Youssef Benali', 'email' => 'contact@riads-du-sud.example', 'telephone' => '+212 524 00 00 02', 'conditionsAnnulation' => 'Gratuite jusqu\'à 21 jours avant l\'arrivée ; acompte de 30 % non remboursable ensuite.'],
            'canaries' => ['nom' => 'Canaries Resorts', 'type' => 'hebergeur', 'contactNom' => 'Lucía Hernández', 'email' => 'groupes@canaries-resorts.example', 'telephone' => '+34 922 00 00 03', 'conditionsAnnulation' => 'Gratuite jusqu\'à 7 jours avant l\'arrivée.'],
            'nordur' => ['nom' => 'Norður Gisting', 'type' => 'hebergeur', 'contactNom' => 'Katrín Jónsdóttir', 'email' => 'booking@nordur-gisting.example', 'telephone' => '+354 500 0004', 'conditionsAnnulation' => 'Gratuite jusqu\'à 30 jours avant l\'arrivée, puis non remboursable.'],
            'bali-villas' => ['nom' => 'Bali Villas Collection', 'type' => 'hebergeur', 'contactNom' => 'Made Wirawan', 'email' => 'stay@bali-villas.example', 'telephone' => '+62 361 000 005', 'conditionsAnnulation' => 'Gratuite jusqu\'à 30 jours avant l\'arrivée ; 50 % entre 30 et 7 jours.'],
            'pura-vida' => ['nom' => 'Pura Vida Lodges', 'type' => 'hebergeur', 'contactNom' => 'Andrés Mora', 'email' => 'info@puravida-lodges.example', 'telephone' => '+506 2000 0006', 'conditionsAnnulation' => 'Gratuite jusqu\'à 15 jours avant l\'arrivée.'],
            'groupes' => ['nom' => 'Résidences Groupes & Séminaires', 'type' => 'hebergeur', 'contactNom' => 'Marco Rossi', 'email' => 'events@residences-groupes.example', 'telephone' => '+39 06 0000 0007', 'conditionsAnnulation' => 'Groupes : gratuite jusqu\'à 45 jours avant l\'arrivée, puis 30 % ; 100 % à moins de 15 jours.'],
            // Transporteurs
            'air-horizon' => [
                'nom' => 'Air Horizon', 'type' => 'transporteur', 'contactNom' => 'Service groupes', 'email' => 'groupes@air-horizon.example', 'telephone' => '+33 1 00 00 00 08',
                'conditionsAnnulation' => 'Billets non remboursables ; changement de nom possible jusqu\'à 7 jours avant le départ.',
                'vols' => [
                    'Lisbonne' => ['Paris' => [120, 320], 'Lyon' => [140, 360], 'Bordeaux' => [110, 300]],
                    'Marrakech' => ['Paris' => [150, 380], 'Lyon' => [170, 400], 'Bordeaux' => [160, 390]],
                    'Santorin' => ['Paris' => [220, 520], 'Lyon' => [260, 580], 'Bordeaux' => [280, 600]],
                    'Tenerife' => ['Paris' => [200, 480], 'Lyon' => [230, 520], 'Bordeaux' => [210, 490]],
                    'Rome' => ['Paris' => [100, 300], 'Lyon' => [90, 260], 'Bordeaux' => [120, 320]],
                    'Reykjavik' => ['Paris' => [220, 550], 'Lyon' => [280, 620], 'Bordeaux' => [290, 640]],
                ],
            ],
            'long-courrier' => [
                'nom' => 'Long Courrier Voyages', 'type' => 'transporteur', 'contactNom' => 'Pôle long-courrier', 'email' => 'tarifs@long-courrier.example', 'telephone' => '+33 1 00 00 00 09',
                'conditionsAnnulation' => 'Frais de 150 € par personne jusqu\'à 30 jours avant le départ, puis non remboursable.',
                'vols' => [
                    'Ubud' => ['Paris' => [750, 1400], 'Lyon' => [820, 1500], 'Bordeaux' => [850, 1550]],
                    'La Fortuna' => ['Paris' => [650, 1300], 'Lyon' => [720, 1400], 'Bordeaux' => [740, 1420]],
                ],
            ],
            'meridien' => [
                'nom' => 'Autocars Méridien', 'type' => 'transporteur', 'contactNom' => 'Chloé Perrin', 'email' => 'devis@autocars-meridien.example', 'telephone' => '+33 4 00 00 00 10',
                'conditionsAnnulation' => 'Gratuite jusqu\'à 72 heures avant le transfert.',
                // Transferts aller-retour aéroport ↔ hébergement, par personne.
                'transferts' => ['Lisbonne' => [20, 45], 'Marrakech' => [15, 40], 'Santorin' => [25, 60], 'Tenerife' => [20, 50], 'Rome' => [25, 55], 'Reykjavik' => [40, 90], 'Ubud' => [25, 60], 'La Fortuna' => [60, 140]],
            ],
            // Guides
            'guides-monde' => ['nom' => 'Guides du Monde francophones', 'type' => 'guide', 'contactNom' => 'Inès Moreau', 'email' => 'reservation@guides-du-monde.example', 'telephone' => '+33 1 00 00 00 11', 'conditionsAnnulation' => 'Gratuite jusqu\'à 48 heures avant la visite.'],
            'explora' => ['nom' => 'Explora Nature', 'type' => 'guide', 'contactNom' => 'Gunnar Pétursson', 'email' => 'hello@explora-nature.example', 'telephone' => '+354 500 0012', 'conditionsAnnulation' => 'Gratuite jusqu\'à 7 jours avant ; reportée sans frais si la météo l\'impose.'],
            // Activités
            'oceanes' => ['nom' => 'Aventures Océanes', 'type' => 'activite', 'contactNom' => 'Nikos Pappas', 'email' => 'booking@aventures-oceanes.example', 'telephone' => '+30 22860 00013', 'conditionsAnnulation' => 'Gratuite jusqu\'à 24 heures avant ; remboursée si la sortie est annulée pour météo.'],
            'saveurs' => ['nom' => 'Saveurs & Ateliers', 'type' => 'activite', 'contactNom' => 'Paolo Ferri', 'email' => 'ateliers@saveurs.example', 'telephone' => '+39 06 0000 0014', 'conditionsAnnulation' => 'Gratuite jusqu\'à 72 heures avant l\'atelier.'],
            'bien-etre' => ['nom' => 'Bien-Être Évasion', 'type' => 'activite', 'contactNom' => 'Amira Haddad', 'email' => 'spa@bien-etre-evasion.example', 'telephone' => '+212 524 00 00 15', 'conditionsAnnulation' => 'Gratuite jusqu\'à 24 heures avant le soin.'],
        ];
    }

    /**
     * 30 logements. Prix de base par nuit en euros (pour le logement entier), ajusté par les saisons de la destination.
     *
     * @return list<array<string, mixed>>
     */
    public static function logements(): array
    {
        return [
            // Lisbonne
            ['nom' => 'Hôtel Alfama Vista, chambre double', 'destination' => 'lisbonne', 'prestataire' => 'azur', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 2, 'prix' => 110, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'petit-déjeuner', 'ascenseur'], 'envies' => ['culture', 'gastronomie'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Appartement Bairro Alto', 'destination' => 'lisbonne', 'prestataire' => 'azur', 'categorie' => 'Appartement', 'capacite' => 4, 'prix' => 140, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'cuisine équipée', 'lave-linge'], 'envies' => ['culture', 'famille'], 'photos' => ['appartement']],
            ['nom' => 'Tejo Palace, suite vue fleuve', 'destination' => 'lisbonne', 'prestataire' => 'azur', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 2, 'prix' => 290, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'spa', 'vue fleuve', 'petit-déjeuner'], 'envies' => ['romantique', 'détente'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Lisboa Centro Groupes & Séminaires', 'destination' => 'lisbonne', 'prestataire' => 'groupes', 'categorie' => 'Résidence pour groupes', 'capacite' => 40, 'prix' => 1900, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 60 places', 'vidéoprojecteur', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['seminaire', 'auberge'], 'description' => 'Vingt chambres doubles et une salle plénière en plein centre, pour groupes et séminaires.'],
            // Marrakech
            ['nom' => 'Riad Dar Yasmine, riad entier', 'destination' => 'marrakech', 'prestataire' => 'riads', 'categorie' => 'Riad privatisé', 'capacite' => 12, 'prix' => 650, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'piscine', 'terrasse', 'cuisinière sur place', 'petit-déjeuner'], 'envies' => ['culture', 'détente', 'famille'], 'photos' => ['riad'], 'description' => 'Six chambres autour d\'un patio avec piscine, privatisé pour un groupe d\'amis ou une famille.'],
            ['nom' => 'Riad Lalla, chambre double', 'destination' => 'marrakech', 'prestataire' => 'riads', 'categorie' => 'Riad de charme', 'capacite' => 2, 'prix' => 95, 'equipements' => ['wifi', 'climatisation', 'petit-déjeuner', 'hammam'], 'envies' => ['romantique', 'culture'], 'photos' => ['riad']],
            ['nom' => 'Palmeraie Resort & Séminaires', 'destination' => 'marrakech', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 50, 'prix' => 2600, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'salle de réunion 80 places', 'salles de sous-commission', 'restauration de groupe'], 'envies' => ['détente'], 'photos' => ['seminaire', 'hotel-piscine'], 'description' => 'Vingt-cinq chambres réservées en bloc, salle plénière et jardins pour les séminaires et incentives.'],
            ['nom' => 'Kasbah Atlas, chambre familiale', 'destination' => 'marrakech', 'prestataire' => 'riads', 'categorie' => 'Kasbah', 'capacite' => 4, 'prix' => 160, 'equipements' => ['wifi', 'piscine', 'demi-pension'], 'envies' => ['nature', 'famille'], 'photos' => ['riad']],
            // Santorin
            ['nom' => 'Suite Caldera Oia, piscine privée', 'destination' => 'santorin', 'prestataire' => 'azur', 'categorie' => 'Suite de luxe', 'capacite' => 2, 'prix' => 420, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'climatisation', 'piscine privée', 'vue caldeira', 'petit-déjeuner'], 'envies' => ['romantique', 'détente'], 'photos' => ['villa'], 'description' => 'Suite troglodyte face au coucher du soleil, champagne offert pour les voyages de noces.'],
            ['nom' => 'Hôtel Fira Blue, chambre triple', 'destination' => 'santorin', 'prestataire' => 'azur', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 3, 'prix' => 150, 'equipements' => ['wifi', 'climatisation', 'piscine'], 'envies' => ['plage', 'culture'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Villa Imerovigli', 'destination' => 'santorin', 'prestataire' => 'azur', 'categorie' => 'Villa', 'capacite' => 6, 'prix' => 560, 'sejourMinimum' => 4, 'equipements' => ['wifi', 'piscine privée', 'cuisine équipée', 'vue mer'], 'envies' => ['famille', 'détente'], 'photos' => ['villa']],
            // Tenerife
            ['nom' => 'Canaries Beach Resort, chambre familiale', 'destination' => 'tenerife', 'prestataire' => 'canaries', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'prix' => 190, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'club enfants', 'demi-pension', 'accès plage'], 'envies' => ['plage', 'famille', 'détente'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Apartamentos Los Cristianos', 'destination' => 'tenerife', 'prestataire' => 'canaries', 'categorie' => 'Appartement', 'capacite' => 4, 'prix' => 115, 'sejourMinimum' => 4, 'equipements' => ['wifi', 'cuisine équipée', 'balcon vue mer'], 'envies' => ['plage', 'famille'], 'photos' => ['appartement']],
            ['nom' => 'Hôtel Teide Nature', 'destination' => 'tenerife', 'prestataire' => 'canaries', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 2, 'prix' => 95, 'equipements' => ['wifi', 'petit-déjeuner', 'parking'], 'envies' => ['nature', 'sport'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Club Costa Adeje, bloc groupe', 'destination' => 'tenerife', 'prestataire' => 'canaries', 'categorie' => 'Club de vacances', 'capacite' => 30, 'prix' => 1500, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'tout compris', 'animation'], 'envies' => ['plage', 'détente'], 'photos' => ['hotel-piscine']],
            // Rome
            ['nom' => 'Hôtel Colosseo, chambre double', 'destination' => 'rome', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 2, 'prix' => 130, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'petit-déjeuner', 'ascenseur'], 'envies' => ['culture'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Appartement Trastevere', 'destination' => 'rome', 'prestataire' => 'groupes', 'categorie' => 'Appartement', 'capacite' => 5, 'prix' => 170, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'cuisine équipée', 'climatisation'], 'envies' => ['culture', 'gastronomie', 'famille'], 'photos' => ['appartement']],
            ['nom' => 'Roma Congressi Hotel', 'destination' => 'rome', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 60, 'prix' => 3300, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 120 places', 'traduction simultanée', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['seminaire'], 'description' => 'Trente chambres en bloc et un centre de congrès pour les conventions d\'entreprise.'],
            ['nom' => 'Auberge Termini, dortoirs groupes', 'destination' => 'rome', 'prestataire' => 'groupes', 'categorie' => 'Auberge de groupe', 'capacite' => 40, 'prix' => 1100, 'equipements' => ['wifi', 'petit-déjeuner', 'salle commune'], 'envies' => ['culture'], 'photos' => ['auberge'], 'description' => 'Dortoirs de 4 à 8 lits près de la gare Termini, pour les groupes scolaires et associatifs.'],
            // Islande (tarifs de mai à septembre seulement)
            ['nom' => 'Guesthouse Reykjavik Centre', 'destination' => 'islande', 'prestataire' => 'nordur', 'categorie' => 'Maison d\'hôtes', 'capacite' => 2, 'prix' => 150, 'equipements' => ['wifi', 'petit-déjeuner'], 'envies' => ['culture', 'nature'], 'photos' => ['guesthouse']],
            ['nom' => 'Ferme de Hella, chambre familiale', 'destination' => 'islande', 'prestataire' => 'nordur', 'categorie' => 'Ferme-auberge', 'capacite' => 4, 'prix' => 210, 'equipements' => ['wifi', 'dîner', 'jacuzzi extérieur'], 'envies' => ['nature', 'famille'], 'photos' => ['guesthouse']],
            ['nom' => 'Hôtel Vík Glacier', 'destination' => 'islande', 'prestataire' => 'nordur', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 2, 'prix' => 190, 'accessible' => true, 'equipements' => ['wifi', 'petit-déjeuner', 'parking'], 'envies' => ['nature'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Cabane d\'Akureyri', 'destination' => 'islande', 'prestataire' => 'nordur', 'categorie' => 'Chalet', 'capacite' => 4, 'prix' => 230, 'sejourMinimum' => 2, 'equipements' => ['wifi', 'cuisine équipée', 'sauna'], 'envies' => ['nature', 'romantique'], 'photos' => ['guesthouse']],
            // Bali
            ['nom' => 'Villa Ubud Rizières, piscine privée', 'destination' => 'bali', 'prestataire' => 'bali-villas', 'categorie' => 'Villa de luxe', 'capacite' => 2, 'prix' => 260, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'piscine privée', 'petit-déjeuner flottant', 'vue rizières'], 'envies' => ['romantique', 'détente', 'nature'], 'photos' => ['villa-bali', 'villa'], 'description' => 'Villa nichée dans les rizières, dîner aux chandelles et massage en duo pour les jeunes mariés.'],
            ['nom' => 'Seminyak Beach Resort', 'destination' => 'bali', 'prestataire' => 'bali-villas', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 3, 'prix' => 170, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'spa', 'accès plage'], 'envies' => ['plage', 'détente'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Bungalow Canggu Surf', 'destination' => 'bali', 'prestataire' => 'bali-villas', 'categorie' => 'Bungalow', 'capacite' => 2, 'prix' => 70, 'equipements' => ['wifi', 'ventilateur', 'piscine commune'], 'envies' => ['sport', 'plage'], 'photos' => ['ecolodge']],
            ['nom' => 'Villa Uluwatu, 4 chambres', 'destination' => 'bali', 'prestataire' => 'bali-villas', 'categorie' => 'Villa', 'capacite' => 8, 'prix' => 480, 'sejourMinimum' => 5, 'equipements' => ['wifi', 'piscine privée', 'cuisinier', 'vue océan'], 'envies' => ['famille', 'détente'], 'photos' => ['villa']],
            // Costa Rica
            ['nom' => 'Lodge Arenal Volcán', 'destination' => 'costa-rica', 'prestataire' => 'pura-vida', 'categorie' => 'Lodge', 'capacite' => 3, 'prix' => 160, 'accessible' => true, 'equipements' => ['wifi', 'sources chaudes', 'petit-déjeuner', 'vue volcan'], 'envies' => ['nature', 'détente'], 'photos' => ['ecolodge']],
            ['nom' => 'Ecolodge Monteverde', 'destination' => 'costa-rica', 'prestataire' => 'pura-vida', 'categorie' => 'Écolodge', 'capacite' => 2, 'prix' => 120, 'equipements' => ['petit-déjeuner', 'balcon sur la forêt'], 'envies' => ['nature'], 'photos' => ['ecolodge']],
            // Fermé en septembre et octobre : aucun tarif sur ces mois.
            ['nom' => 'Cabinas Drake Bay', 'destination' => 'costa-rica', 'prestataire' => 'pura-vida', 'categorie' => 'Bungalow', 'capacite' => 4, 'prix' => 140, 'sejourMinimum' => 2, 'equipements' => ['pension complète', 'accès plage'], 'envies' => ['nature', 'plage', 'aventure'], 'photos' => ['ecolodge'], 'saisons' => [[1, 4, 1.2], [5, 8, 0.9], [11, 11, 0.9], [12, 12, 1.2]]],
        ];
    }

    /**
     * 20 activités. Prix par personne en euros, ajusté par les saisons de la destination.
     *
     * @return list<array<string, mixed>>
     */
    public static function activites(): array
    {
        return [
            // Lisbonne
            ['nom' => 'Visite guidée de l\'Alfama et du tram 28', 'destination' => 'lisbonne', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 20, 'prix' => 35, 'duree' => 180, 'accessible' => false, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            ['nom' => 'Atelier pastéis de nata', 'destination' => 'lisbonne', 'prestataire' => 'saveurs', 'categorie' => 'Atelier culinaire', 'capacite' => 12, 'prix' => 55, 'duree' => 120, 'accessible' => true, 'envies' => ['gastronomie', 'famille'], 'photos' => ['cuisine']],
            // Marrakech
            ['nom' => 'Visite guidée de la médina et des souks', 'destination' => 'marrakech', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 30, 'duree' => 210, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            ['nom' => 'Cours de cuisine marocaine et tajine', 'destination' => 'marrakech', 'prestataire' => 'saveurs', 'categorie' => 'Atelier culinaire', 'capacite' => 16, 'prix' => 45, 'duree' => 240, 'accessible' => true, 'envies' => ['gastronomie'], 'photos' => ['cuisine']],
            ['nom' => 'Hammam traditionnel et massage', 'destination' => 'marrakech', 'prestataire' => 'bien-etre', 'categorie' => 'Bien-être', 'capacite' => 2, 'prix' => 70, 'duree' => 120, 'ageMinimum' => 16, 'envies' => ['détente', 'romantique'], 'photos' => ['spa']],
            // Santorin
            ['nom' => 'Croisière en catamaran dans la caldeira', 'destination' => 'santorin', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 30, 'prix' => 120, 'duree' => 300, 'envies' => ['romantique', 'plage'], 'photos' => ['voilier']],
            ['nom' => 'Dégustation de vins volcaniques', 'destination' => 'santorin', 'prestataire' => 'saveurs', 'categorie' => 'Dégustation', 'capacite' => 14, 'prix' => 60, 'duree' => 150, 'ageMinimum' => 18, 'envies' => ['gastronomie', 'romantique'], 'photos' => ['cuisine']],
            // Tenerife
            ['nom' => 'Observation des baleines et dauphins', 'destination' => 'tenerife', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 40, 'prix' => 45, 'duree' => 180, 'accessible' => true, 'envies' => ['nature', 'famille'], 'photos' => ['baleines']],
            ['nom' => 'Randonnée guidée au Teide', 'destination' => 'tenerife', 'prestataire' => 'explora', 'categorie' => 'Randonnée', 'capacite' => 15, 'prix' => 65, 'duree' => 360, 'ageMinimum' => 12, 'envies' => ['nature', 'sport'], 'photos' => ['randonnee']],
            ['nom' => 'Baptême de plongée', 'destination' => 'tenerife', 'prestataire' => 'oceanes', 'categorie' => 'Plongée', 'capacite' => 6, 'prix' => 75, 'duree' => 150, 'ageMinimum' => 10, 'envies' => ['sport', 'plage', 'aventure'], 'photos' => ['plongee']],
            // Rome
            ['nom' => 'Visite guidée du Colisée et du Forum', 'destination' => 'rome', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 55, 'duree' => 180, 'accessible' => true, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            ['nom' => 'Cours de pâtes fraîches', 'destination' => 'rome', 'prestataire' => 'saveurs', 'categorie' => 'Atelier culinaire', 'capacite' => 20, 'prix' => 70, 'duree' => 180, 'accessible' => true, 'envies' => ['gastronomie', 'famille'], 'photos' => ['cuisine']],
            // Islande (tarifs de mai à septembre seulement)
            ['nom' => 'Baignade dans une source chaude', 'destination' => 'islande', 'prestataire' => 'bien-etre', 'categorie' => 'Bien-être', 'capacite' => 50, 'prix' => 85, 'duree' => 180, 'accessible' => true, 'envies' => ['détente', 'nature'], 'photos' => ['spa']],
            ['nom' => 'Randonnée sur le glacier Sólheimajökull', 'destination' => 'islande', 'prestataire' => 'explora', 'categorie' => 'Randonnée', 'capacite' => 12, 'prix' => 130, 'duree' => 240, 'ageMinimum' => 10, 'envies' => ['nature', 'aventure', 'sport'], 'photos' => ['randonnee']],
            ['nom' => 'Observation des baleines à Húsavík', 'destination' => 'islande', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 40, 'prix' => 110, 'duree' => 180, 'envies' => ['nature', 'famille'], 'photos' => ['baleines']],
            // Bali
            ['nom' => 'Cours de surf à Canggu', 'destination' => 'bali', 'prestataire' => 'oceanes', 'categorie' => 'Sport nautique', 'capacite' => 8, 'prix' => 40, 'duree' => 120, 'ageMinimum' => 8, 'envies' => ['sport', 'plage'], 'photos' => ['surf']],
            ['nom' => 'Massage balinais en duo', 'destination' => 'bali', 'prestataire' => 'bien-etre', 'categorie' => 'Bien-être', 'capacite' => 2, 'prix' => 35, 'duree' => 90, 'ageMinimum' => 16, 'envies' => ['détente', 'romantique'], 'photos' => ['spa']],
            ['nom' => 'Lever de soleil au mont Batur', 'destination' => 'bali', 'prestataire' => 'explora', 'categorie' => 'Randonnée', 'capacite' => 12, 'prix' => 55, 'duree' => 360, 'ageMinimum' => 12, 'envies' => ['nature', 'aventure'], 'photos' => ['randonnee']],
            // Costa Rica
            ['nom' => 'Kayak sur le lac Arenal', 'destination' => 'costa-rica', 'prestataire' => 'explora', 'categorie' => 'Sport nautique', 'capacite' => 10, 'prix' => 65, 'duree' => 180, 'ageMinimum' => 8, 'envies' => ['nature', 'sport'], 'photos' => ['kayak']],
            ['nom' => 'Balade nocturne dans la forêt de Monteverde', 'destination' => 'costa-rica', 'prestataire' => 'explora', 'categorie' => 'Randonnée', 'capacite' => 12, 'prix' => 35, 'duree' => 120, 'ageMinimum' => 6, 'envies' => ['nature', 'famille', 'aventure'], 'photos' => ['randonnee']],
        ];
    }
}
