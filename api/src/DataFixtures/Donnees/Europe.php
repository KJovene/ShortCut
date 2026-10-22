<?php

namespace App\DataFixtures\Donnees;

use App\DataFixtures\UtilisateurFixtures;

/**
 * Catalogue de démo, Europe : 13 destinations, des îles de Méditerranée à la Laponie.
 * Mêmes conventions que Catalogue (prix en euros, saisons, climat mois par mois).
 * Volontairement non couverts : en Laponie, chiens de traîneau et aurores boréales hors saison (d'avril à
 * novembre) ; dans les fjords, la croisière d'octobre à avril ; au Tyrol, le ski de mai à novembre.
 */
final class Europe
{
    /** @return array<string, array<string, mixed>> */
    public static function destinations(): array
    {
        return [
            'corse' => [
                'pays' => 'France', 'region' => 'Corse', 'ville' => 'Ajaccio', 'photo' => 'corse',
                'nom' => 'Corse, France', 'typesDeVoyage' => ['sejour', 'circuit', 'groupe'], 'moisIdeal' => 6, 'latitude' => 41.9, 'longitude' => 8.7,
                'zones' => ['Europe', 'Europe du Sud', 'Méditerranée', 'Îles'],
                'photoAlt' => 'Collines couvertes de maquis au-dessus d’un golfe de la côte ouest de la Corse',
                'accroche' => 'Criques turquoise, villages perchés et GR20, à 1 h 30 de Paris.',
                'description' => 'L\'île de Beauté sans passeport : golfe d\'Ajaccio, calanques de Piana, Bonifacio sur ses falaises et villages de montagne. Plage, randonnée et charcuterie corse, en séjour familial ou en petit circuit.',
                'pointsForts' => ['Plages et criques turquoise', 'Randonnée dans le maquis', 'Vols courts sans passeport', 'Gastronomie corse'],
                'tempsDeTrajet' => ['Paris' => 95, 'Lyon' => 75, 'Bordeaux' => 100],
                'temperatures' => [9, 9, 11, 13, 17, 21, 24, 24, 21, 17, 13, 10],
                'pluie' => [70, 60, 55, 50, 40, 15, 10, 15, 50, 90, 95, 85],
                'saisons' => Catalogue::SAISONS_MEDITERRANEE,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'dubrovnik' => [
                'pays' => 'Croatie', 'region' => 'Dalmatie', 'ville' => 'Dubrovnik', 'photo' => 'dubrovnik',
                'nom' => 'Dubrovnik, Croatie', 'typesDeVoyage' => ['sejour', 'noces', 'groupe'], 'moisIdeal' => 6, 'latitude' => 42.6, 'longitude' => 18.1,
                'zones' => ['Europe', 'Europe du Sud', 'Méditerranée', 'Balkans', 'Adriatique'],
                'photoAlt' => 'Toits de tuiles rouges et coupole de la vieille ville de Dubrovnik',
                'accroche' => 'Remparts sur l’Adriatique, îles en bateau et fruits de mer.',
                'description' => 'La perle de l\'Adriatique : vieille ville fortifiée classée à l\'UNESCO, remparts au coucher du soleil, îles Élaphites en bateau et villages de pêcheurs. Romantique au printemps, très animée l\'été.',
                'pointsForts' => ['Tour des remparts', 'Îles en bateau', 'Eaux claires de l\'Adriatique', 'Cuisine de la mer'],
                'tempsDeTrajet' => ['Paris' => 140, 'Lyon' => 170, 'Bordeaux' => 210],
                'temperatures' => [9, 10, 12, 15, 19, 23, 26, 26, 22, 18, 14, 10],
                'pluie' => [95, 100, 95, 90, 70, 45, 25, 55, 100, 155, 190, 140],
                'saisons' => Catalogue::SAISONS_MEDITERRANEE,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'malte' => [
                'pays' => 'Malte', 'region' => 'Île de Malte', 'ville' => 'La Valette', 'photo' => 'malte',
                'nom' => 'Malte', 'typesDeVoyage' => ['groupe', 'entreprise', 'sejour'], 'moisIdeal' => 5, 'latitude' => 35.9, 'longitude' => 14.5,
                'zones' => ['Europe', 'Europe du Sud', 'Méditerranée', 'Îles'],
                'photoAlt' => 'Façades de calcaire doré et coupole de basilique à La Valette',
                'accroche' => 'Remparts des chevaliers, criques et plongée, au soleil toute l’année.',
                'description' => 'Un archipel au cœur de la Méditerranée : La Valette et ses remparts, la cité silencieuse de Mdina, le lagon bleu de Comino et des fonds parfaits pour la plongée. Anglophone, compact et ensoleillé : idéal pour les groupes et les séminaires.',
                'pointsForts' => ['La Valette classée à l\'UNESCO', 'Lagon bleu de Comino', 'Plongée sur épaves', 'Hôtels de séminaire'],
                'tempsDeTrajet' => ['Paris' => 165, 'Lyon' => 135, 'Bordeaux' => 175],
                'temperatures' => [13, 13, 14, 17, 20, 24, 27, 27, 25, 22, 18, 15],
                'pluie' => [90, 60, 40, 20, 10, 5, 0, 10, 40, 90, 90, 105],
                'saisons' => Catalogue::SAISONS_MEDITERRANEE,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'irlande' => [
                'pays' => 'Irlande', 'region' => 'Leinster et Connemara', 'ville' => 'Dublin', 'photo' => 'irlande',
                'nom' => 'Irlande', 'typesDeVoyage' => ['circuit', 'groupe', 'entreprise'], 'moisIdeal' => 7, 'latitude' => 53.3, 'longitude' => -6.3,
                'zones' => ['Europe', 'Europe du Nord', 'Îles britanniques', 'Atlantique', 'Îles'],
                'photoAlt' => 'Falaises verdoyantes de la péninsule de Dingle plongeant dans l’Atlantique',
                'accroche' => 'Pubs de Dublin, falaises de Moher et routes du Connemara.',
                'description' => 'De Dublin et ses pubs aux falaises de Moher, en passant par les lacs du Connemara : l\'Irlande se découvre en circuit au volant ou en groupe. Accueil chaleureux, musique live et paysages d\'un vert intense.',
                'pointsForts' => ['Circuit de 7 à 10 jours', 'Falaises de Moher', 'Pubs et musique traditionnelle', 'Vols directs depuis la France'],
                'tempsDeTrajet' => ['Paris' => 100, 'Lyon' => 115, 'Bordeaux' => 105],
                'temperatures' => [5, 5, 7, 8, 11, 14, 15, 15, 13, 10, 7, 5],
                'pluie' => [65, 50, 55, 55, 60, 65, 55, 75, 60, 80, 75, 75],
                'saisons' => [[1, 3, 0.8], [4, 6, 1.0], [7, 8, 1.25], [9, 10, 1.0], [11, 12, 0.85]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'edimbourg' => [
                'pays' => 'Royaume-Uni', 'region' => 'Écosse', 'ville' => 'Édimbourg', 'photo' => 'edimbourg',
                'nom' => 'Édimbourg et Highlands', 'typesDeVoyage' => ['circuit', 'groupe', 'entreprise'], 'moisIdeal' => 8, 'latitude' => 56.0, 'longitude' => -3.2,
                'zones' => ['Europe', 'Europe du Nord', 'Îles britanniques', 'Écosse'],
                'photoAlt' => 'Une voyageuse contemple les toits de la vieille ville d’Édimbourg',
                'accroche' => 'Château, whisky et lochs des Highlands, en circuit.',
                'description' => 'Édimbourg, sa vieille ville médiévale et son festival d\'août, puis la route des Highlands : Loch Ness, île de Skye, distilleries de whisky et châteaux. Un circuit de caractère, aussi apprécié des groupes et des séminaires.',
                'pointsForts' => ['Festival d\'Édimbourg en août', 'Lochs et Highlands', 'Distilleries de whisky', 'Châteaux écossais'],
                'tempsDeTrajet' => ['Paris' => 110, 'Lyon' => 130, 'Bordeaux' => 115],
                'temperatures' => [4, 5, 6, 8, 11, 14, 16, 15, 13, 10, 6, 4],
                'pluie' => [65, 45, 50, 40, 50, 55, 65, 65, 60, 70, 60, 60],
                'saisons' => [[1, 3, 0.8], [4, 6, 1.0], [7, 8, 1.35], [9, 10, 1.0], [11, 11, 0.8], [12, 12, 1.0]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'fjords-norvegiens' => [
                'pays' => 'Norvège', 'region' => 'Vestland', 'ville' => 'Bergen', 'photo' => 'fjords-norvegiens',
                'nom' => 'Fjords de Norvège', 'typesDeVoyage' => ['circuit', 'sejour'], 'moisIdeal' => 7, 'latitude' => 60.4, 'longitude' => 5.3,
                'zones' => ['Europe', 'Europe du Nord', 'Scandinavie', 'Pays nordiques'],
                'photoAlt' => 'Hameau sur un versant verdoyant au-dessus d’un fjord près de Geiranger',
                'accroche' => 'Fjords vertigineux, cascades et maisons de bois de Bergen.',
                'description' => 'De Bergen et ses maisons hanséatiques aux fjords de Sogne et de Geiranger : croisières entre les falaises, train de Flåm, randonnées et cascades. Les longues journées d\'été en font un circuit nature inoubliable.',
                'pointsForts' => ['Croisière dans les fjords', 'Train de Flåm', 'Randonnées et cascades', 'Journées sans fin en été'],
                'tempsDeTrajet' => ['Paris' => 135, 'Lyon' => 240, 'Bordeaux' => 260],
                'temperatures' => [2, 2, 4, 7, 11, 14, 16, 16, 13, 9, 5, 3],
                'pluie' => [190, 150, 170, 110, 105, 130, 150, 190, 250, 240, 250, 220],
                'saisons' => [[1, 4, 0.8], [5, 5, 1.0], [6, 8, 1.3], [9, 9, 1.0], [10, 12, 0.8]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'laponie' => [
                'pays' => 'Finlande', 'region' => 'Laponie', 'ville' => 'Rovaniemi', 'photo' => 'laponie',
                'nom' => 'Laponie, Finlande', 'typesDeVoyage' => ['sejour', 'groupe', 'noces'], 'moisIdeal' => 2, 'latitude' => 66.5, 'longitude' => 25.7,
                'zones' => ['Europe', 'Europe du Nord', 'Scandinavie', 'Pays nordiques', 'Cercle polaire'],
                'photoAlt' => 'Aurore boréale verte au-dessus d’une forêt enneigée de Laponie',
                'accroche' => 'Chiens de traîneau, aurores boréales et nuits en igloo de verre.',
                'description' => 'Au cercle polaire, l\'hiver devient une aventure : chiens de traîneau, motoneige, chasse aux aurores boréales et nuits dans un igloo de verre. Magique en famille à Noël, et pour des noces sous les étoiles.',
                'pointsForts' => ['Aurores boréales de septembre à mars', 'Chiens de traîneau', 'Igloos de verre', 'Village du Père Noël'],
                'tempsDeTrajet' => ['Paris' => 225, 'Lyon' => 300, 'Bordeaux' => 320],
                'temperatures' => [-13, -12, -7, -1, 6, 12, 15, 12, 7, 0, -6, -10],
                'pluie' => [40, 35, 30, 30, 40, 60, 75, 70, 55, 55, 45, 40],
                'saisons' => [[1, 3, 1.2], [4, 5, 0.8], [6, 8, 0.9], [9, 11, 0.9], [12, 12, 1.5]],
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'amsterdam' => [
                'pays' => 'Pays-Bas', 'region' => 'Hollande-Septentrionale', 'ville' => 'Amsterdam', 'photo' => 'amsterdam',
                'nom' => 'Amsterdam, Pays-Bas', 'typesDeVoyage' => ['entreprise', 'groupe', 'sejour'], 'moisIdeal' => 4, 'latitude' => 52.4, 'longitude' => 4.9,
                'zones' => ['Europe', 'Europe de l\'Ouest', 'Europe du Nord', 'Benelux'],
                'photoAlt' => 'Maisons à pignons et péniches le long d’un canal d’Amsterdam',
                'accroche' => 'Canaux, musées et vélos, à 1 h 15 de Paris.',
                'description' => 'Canaux classés, maisons à pignons, Rijksmuseum et musée Van Gogh : Amsterdam se visite à vélo ou en bateau. Champs de tulipes en avril, centres de congrès modernes : une valeur sûre pour les séminaires et les groupes.',
                'pointsForts' => ['Musées de renommée mondiale', 'Balade en bateau sur les canaux', 'Champs de tulipes en avril', 'Lieux de séminaire'],
                'tempsDeTrajet' => ['Paris' => 80, 'Lyon' => 95, 'Bordeaux' => 110],
                'temperatures' => [4, 4, 7, 10, 13, 16, 18, 18, 15, 11, 7, 4],
                'pluie' => [65, 50, 55, 40, 55, 65, 75, 80, 80, 85, 85, 75],
                'saisons' => Catalogue::SAISONS_VILLE,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'prague' => [
                'pays' => 'Tchéquie', 'region' => 'Bohême', 'ville' => 'Prague', 'photo' => 'prague',
                'nom' => 'Prague, Tchéquie', 'typesDeVoyage' => ['groupe', 'entreprise', 'sejour'], 'moisIdeal' => 5, 'latitude' => 50.1, 'longitude' => 14.4,
                'zones' => ['Europe', 'Europe centrale', 'Europe de l\'Est'],
                'photoAlt' => 'Toits rouges et flèche gothique de Prague vus d’en haut',
                'accroche' => 'Pont Charles, château et brasseries, une capitale à petits prix.',
                'description' => 'La ville aux cent clochers : pont Charles, château, place de la Vieille-Ville et son horloge astronomique, brasseries et concerts classiques. Abordable et compacte, parfaite pour les groupes scolaires, les associations et les séminaires.',
                'pointsForts' => ['Centre historique à pied', 'Concerts et opéra', 'Brasseries traditionnelles', 'Budget maîtrisé'],
                'tempsDeTrajet' => ['Paris' => 100, 'Lyon' => 105, 'Bordeaux' => 200],
                'temperatures' => [0, 1, 5, 10, 15, 18, 20, 19, 15, 10, 4, 1],
                'pluie' => [25, 25, 30, 35, 60, 70, 70, 65, 40, 30, 35, 30],
                'saisons' => Catalogue::SAISONS_VILLE,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'budapest' => [
                'pays' => 'Hongrie', 'region' => 'Hongrie centrale', 'ville' => 'Budapest', 'photo' => 'budapest',
                'nom' => 'Budapest, Hongrie', 'typesDeVoyage' => ['sejour', 'groupe', 'entreprise'], 'moisIdeal' => 5, 'latitude' => 47.5, 'longitude' => 19.0,
                'zones' => ['Europe', 'Europe centrale', 'Europe de l\'Est'],
                'photoAlt' => 'Le Parlement de Budapest illuminé au bord du Danube',
                'accroche' => 'Bains thermaux, Danube et cafés Art nouveau.',
                'description' => 'Deux villes de part et d\'autre du Danube : Buda et son château, Pest et son Parlement. On s\'y baigne dans des bains thermaux centenaires, on dîne en croisière sur le fleuve et on sort dans les bars en ruines.',
                'pointsForts' => ['Bains thermaux', 'Croisière sur le Danube', 'Architecture Art nouveau', 'Bon rapport qualité-prix'],
                'tempsDeTrajet' => ['Paris' => 130, 'Lyon' => 120, 'Bordeaux' => 145],
                'temperatures' => [0, 2, 7, 12, 17, 20, 22, 22, 17, 11, 5, 1],
                'pluie' => [35, 30, 30, 45, 60, 65, 55, 55, 45, 40, 50, 45],
                'saisons' => Catalogue::SAISONS_VILLE,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
            'tyrol' => [
                'pays' => 'Autriche', 'region' => 'Tyrol', 'ville' => 'Innsbruck', 'photo' => 'tyrol',
                'nom' => 'Tyrol, Autriche', 'typesDeVoyage' => ['sejour', 'groupe', 'entreprise'], 'moisIdeal' => 2, 'latitude' => 47.3, 'longitude' => 11.4,
                'zones' => ['Europe', 'Europe centrale', 'Alpes', 'Montagne'],
                'photoAlt' => 'Sommets enneigés et forêts de sapins des Alpes du Tyrol',
                'accroche' => 'Ski au pied des sommets l’hiver, alpages et lacs l’été.',
                'description' => 'Innsbruck et ses vallées : domaines skiables reliés, chalets au pied des pistes et marchés de Noël l\'hiver ; randonnées, lacs alpins et refuges l\'été. Une montagne accessible pour les familles, les groupes et les séminaires au grand air.',
                'pointsForts' => ['Ski de décembre à avril', 'Chalets au pied des pistes', 'Randonnée et lacs l\'été', 'Marchés de Noël'],
                'tempsDeTrajet' => ['Paris' => 105, 'Lyon' => 300, 'Bordeaux' => 240],
                'temperatures' => [-2, 0, 5, 9, 14, 17, 19, 19, 15, 10, 4, -1],
                'pluie' => [50, 45, 55, 60, 90, 115, 140, 120, 80, 60, 60, 55],
                'saisons' => [[1, 3, 1.3], [4, 4, 1.0], [5, 6, 0.8], [7, 8, 1.0], [9, 11, 0.8], [12, 12, 1.4]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'slovenie' => [
                'pays' => 'Slovénie', 'region' => 'Haute-Carniole', 'ville' => 'Ljubljana', 'photo' => 'slovenie',
                'nom' => 'Slovénie, lac de Bled', 'typesDeVoyage' => ['circuit', 'sejour', 'noces'], 'moisIdeal' => 6, 'latitude' => 46.1, 'longitude' => 14.5,
                'zones' => ['Europe', 'Europe centrale', 'Balkans', 'Alpes'],
                'photoAlt' => 'Le lac de Bled, son île et les Alpes juliennes en arrière-plan',
                'accroche' => 'Lac de Bled, grottes et Alpes juliennes, une Europe verte et secrète.',
                'description' => 'Un petit pays très vert : Ljubljana la piétonne, le lac de Bled et son île, les Alpes juliennes, les grottes de Postojna et la côte adriatique de Piran. Un circuit nature facile, romantique pour des noces discrètes.',
                'pointsForts' => ['Lac de Bled', 'Grottes de Postojna', 'Alpes juliennes', 'Circuit court et varié'],
                'tempsDeTrajet' => ['Paris' => 115, 'Lyon' => 200, 'Bordeaux' => 220],
                'temperatures' => [0, 2, 6, 11, 15, 19, 21, 20, 16, 11, 6, 1],
                'pluie' => [70, 70, 90, 100, 110, 140, 115, 130, 140, 140, 130, 100],
                'saisons' => [[1, 3, 0.8], [4, 6, 1.0], [7, 8, 1.3], [9, 10, 1.0], [11, 12, 0.8]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'chypre' => [
                'pays' => 'Chypre', 'region' => 'Paphos', 'ville' => 'Paphos', 'photo' => 'chypre',
                'nom' => 'Chypre', 'typesDeVoyage' => ['sejour', 'noces', 'groupe'], 'moisIdeal' => 5, 'latitude' => 34.8, 'longitude' => 32.4,
                'zones' => ['Europe', 'Europe du Sud', 'Méditerranée', 'Îles'],
                'photoAlt' => 'Une famille au bord des vagues sur une plage de sable de Paphos',
                'accroche' => 'L’île d’Aphrodite : plages chaudes d’avril à novembre et mosaïques antiques.',
                'description' => 'L\'île d\'Aphrodite, la plus chaude de Méditerranée : plages jusqu\'en novembre, mosaïques de Paphos, villages du Troodos et vins de Commandaria. Douce pour des noces au printemps comme pour un séjour en famille.',
                'pointsForts' => ['Baignade d\'avril à novembre', 'Site archéologique de Paphos', 'Villages du Troodos', 'Resorts familiaux'],
                'tempsDeTrajet' => ['Paris' => 260, 'Lyon' => 245, 'Bordeaux' => 300],
                'temperatures' => [13, 13, 15, 18, 21, 25, 28, 28, 26, 23, 18, 15],
                'pluie' => [90, 65, 40, 20, 5, 1, 0, 0, 5, 30, 60, 95],
                'saisons' => Catalogue::SAISONS_MEDITERRANEE,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::LEA,
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function prestataires(): array
    {
        return [
            'europe-hotels' => ['nom' => 'Hôtels & Maisons d\'Europe', 'type' => 'hebergeur', 'contactNom' => 'Annelies de Vries', 'email' => 'reservations@maisons-europe.example', 'telephone' => '+31 20 000 0016', 'conditionsAnnulation' => 'Gratuite jusqu\'à 7 jours avant l\'arrivée, puis première nuit due.'],
            'rivages' => ['nom' => 'Rivages de Méditerranée', 'type' => 'hebergeur', 'contactNom' => 'Marija Kovač', 'email' => 'booking@rivages-med.example', 'telephone' => '+385 20 000 017', 'conditionsAnnulation' => 'Gratuite jusqu\'à 21 jours avant l\'arrivée ; 50 % ensuite.'],
            'nordic-stays' => ['nom' => 'Nordic Stays', 'type' => 'hebergeur', 'contactNom' => 'Aino Virtanen', 'email' => 'stay@nordic-stays.example', 'telephone' => '+358 16 000 018', 'conditionsAnnulation' => 'Gratuite jusqu\'à 30 jours avant l\'arrivée en hiver, 14 jours le reste de l\'année.'],
            'chalets-alpes' => ['nom' => 'Chalets des Alpes', 'type' => 'hebergeur', 'contactNom' => 'Lukas Gruber', 'email' => 'info@chalets-alpes.example', 'telephone' => '+43 512 000 019', 'conditionsAnnulation' => 'Gratuite jusqu\'à 30 jours avant l\'arrivée ; 40 % entre 30 et 14 jours, puis 80 %.'],
            'cap-europe' => [
                'nom' => 'Cap Europe Aviation', 'type' => 'transporteur', 'contactNom' => 'Service tarifs', 'email' => 'tarifs@cap-europe.example', 'telephone' => '+33 1 00 00 00 20',
                'conditionsAnnulation' => 'Billets non remboursables ; changement de date contre 60 € par personne.',
                'vols' => [
                    'Ajaccio' => ['Paris' => [90, 280], 'Lyon' => [100, 300], 'Bordeaux' => [120, 320]],
                    'Dubrovnik' => ['Paris' => [140, 400], 'Lyon' => [170, 450], 'Bordeaux' => [190, 480]],
                    'La Valette' => ['Paris' => [110, 330], 'Lyon' => [120, 340], 'Bordeaux' => [140, 360]],
                    'Dublin' => ['Paris' => [90, 280], 'Lyon' => [110, 300], 'Bordeaux' => [100, 290]],
                    'Édimbourg' => ['Paris' => [100, 320], 'Lyon' => [130, 360], 'Bordeaux' => [120, 340]],
                    'Bergen' => ['Paris' => [150, 450], 'Lyon' => [200, 520], 'Bordeaux' => [210, 540]],
                    'Rovaniemi' => ['Paris' => [250, 650], 'Lyon' => [300, 720], 'Bordeaux' => [320, 750]],
                    'Amsterdam' => ['Paris' => [80, 250], 'Lyon' => [100, 280], 'Bordeaux' => [110, 290]],
                    'Prague' => ['Paris' => [80, 250], 'Lyon' => [110, 300], 'Bordeaux' => [130, 330]],
                    'Budapest' => ['Paris' => [80, 260], 'Lyon' => [90, 270], 'Bordeaux' => [110, 300]],
                    'Innsbruck' => ['Paris' => [120, 360], 'Lyon' => [160, 420], 'Bordeaux' => [170, 440]],
                    'Ljubljana' => ['Paris' => [130, 380], 'Lyon' => [170, 430], 'Bordeaux' => [180, 450]],
                    'Paphos' => ['Paris' => [180, 450], 'Lyon' => [200, 480], 'Bordeaux' => [220, 500]],
                ],
                // Transferts aller-retour aéroport ↔ hébergement, par personne.
                'transferts' => ['Ajaccio' => [20, 50], 'Dubrovnik' => [25, 55], 'La Valette' => [15, 40], 'Dublin' => [20, 45], 'Édimbourg' => [20, 45], 'Bergen' => [25, 60], 'Rovaniemi' => [20, 50], 'Amsterdam' => [15, 40], 'Prague' => [15, 35], 'Budapest' => [15, 35], 'Innsbruck' => [30, 80], 'Ljubljana' => [25, 60], 'Paphos' => [20, 50]],
            ],
            'arctique' => ['nom' => 'Arctique Aventures', 'type' => 'activite', 'contactNom' => 'Juhani Korhonen', 'email' => 'safaris@arctique-aventures.example', 'telephone' => '+358 16 000 021', 'conditionsAnnulation' => 'Gratuite jusqu\'à 72 heures avant ; reportée sans frais si le froid ou la météo l\'imposent.'],
            'ecole-ski' => ['nom' => 'École de ski Alpenblick', 'type' => 'activite', 'contactNom' => 'Sabine Huber', 'email' => 'kurse@alpenblick.example', 'telephone' => '+43 512 000 022', 'conditionsAnnulation' => 'Gratuite jusqu\'à 7 jours avant le cours ; remboursée si les pistes sont fermées.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function logements(): array
    {
        return [
            // Corse
            ['nom' => 'Hôtel des Îles Sanguinaires, chambre vue mer', 'destination' => 'corse', 'prestataire' => 'rivages', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 210, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'vue mer', 'petit-déjeuner'], 'envies' => ['plage', 'romantique', 'détente'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Bergerie de Porto-Vecchio', 'destination' => 'corse', 'prestataire' => 'rivages', 'categorie' => 'Maison de vacances', 'capacite' => 6, 'chambres' => 3, 'prix' => 320, 'sejourMinimum' => 5, 'equipements' => ['wifi', 'piscine privée', 'cuisine équipée', 'terrasse'], 'envies' => ['famille', 'plage', 'nature'], 'photos' => ['villa']],
            ['nom' => 'Gîte d\'étape du GR20, Corte', 'destination' => 'corse', 'prestataire' => 'rivages', 'categorie' => 'Gîte de montagne', 'capacite' => 4, 'chambres' => 2, 'prix' => 90, 'equipements' => ['demi-pension', 'parking'], 'envies' => ['nature', 'sport', 'aventure'], 'photos' => ['guesthouse']],
            ['nom' => 'Village vacances Cap Corse, bloc groupe', 'destination' => 'corse', 'prestataire' => 'groupes', 'categorie' => 'Village vacances', 'capacite' => 40, 'chambres' => 18, 'prix' => 1700, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'pension complète', 'salle commune'], 'envies' => ['plage', 'famille'], 'photos' => ['hotel-piscine']],
            // Dubrovnik
            ['nom' => 'Villa Ploče, suite face aux remparts', 'destination' => 'dubrovnik', 'prestataire' => 'rivages', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 340, 'sejourMinimum' => 2, 'equipements' => ['wifi', 'climatisation', 'spa', 'vue mer', 'petit-déjeuner'], 'envies' => ['romantique', 'détente', 'culture'], 'photos' => ['hotel-ville']],
            ['nom' => 'Appartement Stradun, vieille ville', 'destination' => 'dubrovnik', 'prestataire' => 'rivages', 'categorie' => 'Appartement', 'capacite' => 4, 'chambres' => 2, 'prix' => 160, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'climatisation', 'cuisine équipée'], 'envies' => ['culture', 'famille'], 'photos' => ['appartement']],
            ['nom' => 'Hôtel Lapad Bay, bloc groupe', 'destination' => 'dubrovnik', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 30, 'chambres' => 15, 'prix' => 2100, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'accès plage', 'restauration de groupe'], 'envies' => ['plage', 'culture'], 'photos' => ['hotel-piscine']],
            // Malte
            ['nom' => 'Palazzo Valletta, chambre double', 'destination' => 'malte', 'prestataire' => 'rivages', 'categorie' => 'Hôtel de charme', 'capacite' => 2, 'chambres' => 1, 'prix' => 150, 'equipements' => ['wifi', 'climatisation', 'petit-déjeuner', 'terrasse'], 'envies' => ['culture', 'romantique'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Sliema Seafront, appartement familial', 'destination' => 'malte', 'prestataire' => 'rivages', 'categorie' => 'Appartement', 'capacite' => 5, 'chambres' => 2, 'prix' => 140, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'climatisation', 'cuisine équipée', 'balcon vue mer'], 'envies' => ['famille', 'plage'], 'photos' => ['appartement']],
            ['nom' => 'Malta Congress Resort', 'destination' => 'malte', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 80, 'chambres' => 40, 'prix' => 4200, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'salle de réunion 150 places', 'salles de sous-commission', 'restauration de groupe'], 'envies' => ['détente', 'plage'], 'photos' => ['seminaire', 'hotel-piscine'], 'description' => 'Quarante chambres en bloc, une plénière de 150 places et une terrasse sur la mer pour les incentives.'],
            // Irlande
            ['nom' => 'Hôtel Temple Bar, chambre double', 'destination' => 'irlande', 'prestataire' => 'europe-hotels', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 160, 'accessible' => true, 'equipements' => ['wifi', 'petit-déjeuner irlandais', 'ascenseur'], 'envies' => ['culture', 'gastronomie'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Cottage du Connemara', 'destination' => 'irlande', 'prestataire' => 'europe-hotels', 'categorie' => 'Cottage', 'capacite' => 4, 'chambres' => 2, 'prix' => 150, 'sejourMinimum' => 2, 'equipements' => ['wifi', 'cuisine équipée', 'cheminée', 'parking'], 'envies' => ['nature', 'famille', 'romantique'], 'photos' => ['guesthouse']],
            ['nom' => 'B&B des falaises de Moher', 'destination' => 'irlande', 'prestataire' => 'europe-hotels', 'categorie' => 'Maison d\'hôtes', 'capacite' => 3, 'chambres' => 1, 'prix' => 110, 'equipements' => ['wifi', 'petit-déjeuner', 'parking'], 'envies' => ['nature'], 'photos' => ['guesthouse']],
            ['nom' => 'Dublin Convention Hotel', 'destination' => 'irlande', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 60, 'chambres' => 30, 'prix' => 3600, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 100 places', 'vidéoprojecteur', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['seminaire']],
            // Édimbourg et Highlands
            ['nom' => 'Hôtel Royal Mile, chambre double', 'destination' => 'edimbourg', 'prestataire' => 'europe-hotels', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 190, 'accessible' => true, 'equipements' => ['wifi', 'petit-déjeuner écossais', 'ascenseur'], 'envies' => ['culture', 'gastronomie'], 'photos' => ['hotel-ville']],
            ['nom' => 'Lodge du Loch Ness', 'destination' => 'edimbourg', 'prestataire' => 'europe-hotels', 'categorie' => 'Lodge', 'capacite' => 4, 'chambres' => 2, 'prix' => 210, 'equipements' => ['wifi', 'demi-pension', 'cheminée', 'vue sur le loch'], 'envies' => ['nature', 'famille'], 'photos' => ['ecolodge']],
            ['nom' => 'Auberge de l\'île de Skye', 'destination' => 'edimbourg', 'prestataire' => 'europe-hotels', 'categorie' => 'Auberge', 'capacite' => 2, 'chambres' => 1, 'prix' => 140, 'equipements' => ['wifi', 'petit-déjeuner', 'pub'], 'envies' => ['nature', 'romantique'], 'photos' => ['guesthouse']],
            ['nom' => 'Edinburgh Conference Centre Hotel', 'destination' => 'edimbourg', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 50, 'chambres' => 25, 'prix' => 3200, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 80 places', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['seminaire']],
            // Fjords de Norvège
            ['nom' => 'Hôtel Bryggen, Bergen', 'destination' => 'fjords-norvegiens', 'prestataire' => 'nordic-stays', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 170, 'accessible' => true, 'equipements' => ['wifi', 'petit-déjeuner', 'ascenseur'], 'envies' => ['culture'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Hôtel historique de Balestrand', 'destination' => 'fjords-norvegiens', 'prestataire' => 'nordic-stays', 'categorie' => 'Hôtel de charme', 'capacite' => 2, 'chambres' => 1, 'prix' => 230, 'equipements' => ['wifi', 'demi-pension', 'vue sur le fjord'], 'envies' => ['nature', 'romantique'], 'photos' => ['guesthouse']],
            ['nom' => 'Chalet de Geiranger', 'destination' => 'fjords-norvegiens', 'prestataire' => 'nordic-stays', 'categorie' => 'Chalet', 'capacite' => 5, 'chambres' => 2, 'prix' => 220, 'sejourMinimum' => 2, 'equipements' => ['wifi', 'cuisine équipée', 'sauna', 'parking'], 'envies' => ['nature', 'famille'], 'photos' => ['chalet']],
            // Laponie
            ['nom' => 'Igloo de verre sous les aurores', 'destination' => 'laponie', 'prestataire' => 'nordic-stays', 'categorie' => 'Igloo de verre', 'capacite' => 2, 'chambres' => 1, 'prix' => 480, 'equipements' => ['toit de verre', 'chauffage', 'petit-déjeuner', 'sauna'], 'envies' => ['romantique', 'nature', 'aurores boréales'], 'photos' => ['aurores'], 'description' => 'Un lit face au ciel pour guetter les aurores boréales sans quitter la couette.'],
            ['nom' => 'Chalet familial de Rovaniemi', 'destination' => 'laponie', 'prestataire' => 'nordic-stays', 'categorie' => 'Chalet', 'capacite' => 6, 'chambres' => 3, 'prix' => 290, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'cuisine équipée', 'sauna', 'cheminée'], 'envies' => ['famille', 'nature', 'neige'], 'photos' => ['chalet']],
            ['nom' => 'Hôtel Arctic Circle, chambre double', 'destination' => 'laponie', 'prestataire' => 'nordic-stays', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 190, 'accessible' => true, 'equipements' => ['wifi', 'petit-déjeuner', 'sauna', 'ascenseur'], 'envies' => ['détente', 'famille'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Village de cabanes de Laponie, bloc groupe', 'destination' => 'laponie', 'prestataire' => 'nordic-stays', 'categorie' => 'Village de cabanes', 'capacite' => 24, 'chambres' => 12, 'prix' => 2300, 'equipements' => ['wifi', 'sauna', 'pension complète', 'salle commune'], 'envies' => ['nature', 'neige'], 'photos' => ['chalet']],
            // Amsterdam
            ['nom' => 'Hôtel du Jordaan, chambre double', 'destination' => 'amsterdam', 'prestataire' => 'europe-hotels', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 170, 'accessible' => true, 'equipements' => ['wifi', 'petit-déjeuner', 'ascenseur', 'vélos'], 'envies' => ['culture'], 'photos' => ['hotel-ville']],
            ['nom' => 'Péniche sur le Prinsengracht', 'destination' => 'amsterdam', 'prestataire' => 'europe-hotels', 'categorie' => 'Péniche aménagée', 'capacite' => 4, 'chambres' => 2, 'prix' => 260, 'sejourMinimum' => 2, 'equipements' => ['wifi', 'cuisine équipée', 'terrasse sur l\'eau'], 'envies' => ['romantique', 'famille', 'culture'], 'photos' => ['appartement']],
            ['nom' => 'Amsterdam RAI Business Hotel', 'destination' => 'amsterdam', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 120, 'chambres' => 60, 'prix' => 7800, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 200 places', 'salles de sous-commission', 'traduction simultanée', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['seminaire'], 'description' => 'Soixante chambres en bloc à côté du centre de congrès, pour les conventions et lancements de produit.'],
            // Prague
            ['nom' => 'Hôtel Malá Strana, chambre double', 'destination' => 'prague', 'prestataire' => 'europe-hotels', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 120, 'accessible' => true, 'equipements' => ['wifi', 'petit-déjeuner', 'ascenseur'], 'envies' => ['culture', 'romantique'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Appartement Vieille-Ville', 'destination' => 'prague', 'prestataire' => 'europe-hotels', 'categorie' => 'Appartement', 'capacite' => 5, 'chambres' => 2, 'prix' => 110, 'sejourMinimum' => 2, 'equipements' => ['wifi', 'cuisine équipée', 'lave-linge'], 'envies' => ['culture', 'famille'], 'photos' => ['appartement']],
            ['nom' => 'Auberge Vltava, dortoirs groupes', 'destination' => 'prague', 'prestataire' => 'groupes', 'categorie' => 'Auberge de groupe', 'capacite' => 48, 'chambres' => 8, 'prix' => 900, 'equipements' => ['wifi', 'petit-déjeuner', 'salle commune'], 'envies' => ['culture'], 'photos' => ['auberge'], 'description' => 'Dortoirs de 4 à 8 lits en bord de Vltava, pour les groupes scolaires et associatifs.'],
            ['nom' => 'Prague Congress Hotel', 'destination' => 'prague', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 70, 'chambres' => 35, 'prix' => 3300, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 120 places', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['seminaire']],
            // Budapest
            ['nom' => 'Hôtel thermal Gellért, chambre double', 'destination' => 'budapest', 'prestataire' => 'europe-hotels', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 150, 'accessible' => true, 'equipements' => ['wifi', 'accès aux bains thermaux', 'spa', 'petit-déjeuner'], 'envies' => ['détente', 'culture', 'romantique'], 'photos' => ['spa', 'hotel-chambre']],
            ['nom' => 'Appartement Andrássy', 'destination' => 'budapest', 'prestataire' => 'europe-hotels', 'categorie' => 'Appartement', 'capacite' => 4, 'chambres' => 2, 'prix' => 100, 'sejourMinimum' => 2, 'equipements' => ['wifi', 'cuisine équipée', 'climatisation'], 'envies' => ['culture', 'famille'], 'photos' => ['appartement']],
            ['nom' => 'Danube Business Hotel', 'destination' => 'budapest', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 60, 'chambres' => 30, 'prix' => 2700, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 100 places', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['seminaire']],
            // Tyrol
            ['nom' => 'Chalet Alpenrose au pied des pistes', 'destination' => 'tyrol', 'prestataire' => 'chalets-alpes', 'categorie' => 'Chalet', 'capacite' => 8, 'chambres' => 4, 'prix' => 520, 'sejourMinimum' => 4, 'equipements' => ['wifi', 'cuisine équipée', 'sauna', 'local à skis', 'cheminée'], 'envies' => ['ski', 'neige', 'famille', 'sport'], 'photos' => ['chalet']],
            ['nom' => 'Hôtel Bergblick, chambre double', 'destination' => 'tyrol', 'prestataire' => 'chalets-alpes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 210, 'accessible' => true, 'equipements' => ['wifi', 'spa', 'demi-pension', 'local à skis'], 'envies' => ['ski', 'détente', 'romantique'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Innsbruck Alpine Seminar Hotel', 'destination' => 'tyrol', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 50, 'chambres' => 25, 'prix' => 3400, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 80 places', 'spa', 'restauration de groupe'], 'envies' => ['ski', 'nature'], 'photos' => ['seminaire', 'chalet']],
            // Slovénie
            ['nom' => 'Villa au bord du lac de Bled', 'destination' => 'slovenie', 'prestataire' => 'europe-hotels', 'categorie' => 'Hôtel de charme', 'capacite' => 2, 'chambres' => 1, 'prix' => 190, 'equipements' => ['wifi', 'petit-déjeuner', 'vue sur le lac', 'spa'], 'envies' => ['romantique', 'nature', 'détente'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Hôtel Ljubljana Centre', 'destination' => 'slovenie', 'prestataire' => 'europe-hotels', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 120, 'accessible' => true, 'equipements' => ['wifi', 'petit-déjeuner', 'ascenseur'], 'envies' => ['culture'], 'photos' => ['hotel-ville']],
            ['nom' => 'Ferme touristique des Alpes juliennes', 'destination' => 'slovenie', 'prestataire' => 'europe-hotels', 'categorie' => 'Ferme-auberge', 'capacite' => 5, 'chambres' => 2, 'prix' => 130, 'equipements' => ['wifi', 'demi-pension', 'parking'], 'envies' => ['nature', 'famille', 'gastronomie'], 'photos' => ['guesthouse']],
            // Chypre
            ['nom' => 'Resort Aphrodite, suite avec piscine privée', 'destination' => 'chypre', 'prestataire' => 'rivages', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 360, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'climatisation', 'piscine privée', 'spa', 'vue mer'], 'envies' => ['romantique', 'détente', 'plage'], 'photos' => ['villa']],
            ['nom' => 'Paphos Family Beach, chambre familiale', 'destination' => 'chypre', 'prestataire' => 'rivages', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 200, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'club enfants', 'demi-pension', 'accès plage'], 'envies' => ['plage', 'famille'], 'photos' => ['plage']],
            ['nom' => 'Club Coral Bay, bloc groupe', 'destination' => 'chypre', 'prestataire' => 'groupes', 'categorie' => 'Club de vacances', 'capacite' => 40, 'chambres' => 20, 'prix' => 2600, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'tout compris', 'animation'], 'envies' => ['plage', 'détente'], 'photos' => ['hotel-piscine']],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function activites(): array
    {
        $hiver = [[1, 3, 1.0], [12, 12, 1.2]];

        return [
            // Corse
            ['nom' => 'Sortie en bateau aux calanques de Piana', 'destination' => 'corse', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 25, 'prix' => 65, 'duree' => 240, 'envies' => ['nature', 'plage', 'famille'], 'photos' => ['bateau']],
            ['nom' => 'Randonnée guidée sur le GR20', 'destination' => 'corse', 'prestataire' => 'explora', 'categorie' => 'Randonnée', 'capacite' => 12, 'prix' => 70, 'duree' => 420, 'ageMinimum' => 14, 'envies' => ['nature', 'sport', 'aventure'], 'photos' => ['trek']],
            // Dubrovnik
            ['nom' => 'Visite guidée des remparts de Dubrovnik', 'destination' => 'dubrovnik', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 50, 'duree' => 150, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            ['nom' => 'Kayak de mer autour de l\'île de Lokrum', 'destination' => 'dubrovnik', 'prestataire' => 'oceanes', 'categorie' => 'Sport nautique', 'capacite' => 12, 'prix' => 45, 'duree' => 180, 'ageMinimum' => 8, 'envies' => ['sport', 'plage', 'nature'], 'photos' => ['kayak']],
            // Malte
            ['nom' => 'Plongée sur les épaves de Malte', 'destination' => 'malte', 'prestataire' => 'oceanes', 'categorie' => 'Plongée', 'capacite' => 8, 'prix' => 85, 'duree' => 180, 'ageMinimum' => 12, 'envies' => ['sport', 'aventure'], 'photos' => ['plongee']],
            ['nom' => 'Journée au lagon bleu de Comino', 'destination' => 'malte', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 40, 'prix' => 40, 'duree' => 360, 'accessible' => true, 'envies' => ['plage', 'famille', 'détente'], 'photos' => ['voilier']],
            ['nom' => 'Visite guidée de La Valette et de Mdina', 'destination' => 'malte', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 45, 'duree' => 300, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            // Irlande
            ['nom' => 'Excursion guidée aux falaises de Moher', 'destination' => 'irlande', 'prestataire' => 'guides-monde', 'categorie' => 'Excursion guidée', 'capacite' => 30, 'prix' => 75, 'duree' => 600, 'accessible' => true, 'envies' => ['nature'], 'photos' => ['randonnee']],
            ['nom' => 'Soirée pub et musique traditionnelle', 'destination' => 'irlande', 'prestataire' => 'saveurs', 'categorie' => 'Soirée culturelle', 'capacite' => 40, 'prix' => 45, 'duree' => 180, 'ageMinimum' => 18, 'envies' => ['culture', 'gastronomie'], 'photos' => ['spectacle']],
            // Édimbourg et Highlands
            ['nom' => 'Dégustation dans une distillerie de whisky', 'destination' => 'edimbourg', 'prestataire' => 'saveurs', 'categorie' => 'Dégustation', 'capacite' => 20, 'prix' => 55, 'duree' => 120, 'ageMinimum' => 18, 'envies' => ['gastronomie', 'culture'], 'photos' => ['cuisine']],
            ['nom' => 'Journée guidée au Loch Ness et dans les Highlands', 'destination' => 'edimbourg', 'prestataire' => 'guides-monde', 'categorie' => 'Excursion guidée', 'capacite' => 30, 'prix' => 85, 'duree' => 720, 'accessible' => true, 'envies' => ['nature', 'culture'], 'photos' => ['randonnee']],
            // Fjords de Norvège (croisière de mai à septembre)
            ['nom' => 'Croisière dans le Nærøyfjord et train de Flåm', 'destination' => 'fjords-norvegiens', 'prestataire' => 'oceanes', 'categorie' => 'Croisière', 'capacite' => 120, 'prix' => 160, 'duree' => 600, 'accessible' => true, 'envies' => ['nature', 'famille'], 'photos' => ['croisiere-fluviale'], 'saisons' => [[5, 5, 1.0], [6, 8, 1.2], [9, 9, 1.0]]],
            ['nom' => 'Randonnée guidée au Trolltunga', 'destination' => 'fjords-norvegiens', 'prestataire' => 'explora', 'categorie' => 'Randonnée', 'capacite' => 12, 'prix' => 140, 'duree' => 660, 'ageMinimum' => 14, 'envies' => ['nature', 'sport', 'aventure'], 'photos' => ['trek'], 'saisons' => [[6, 9, 1.0]]],
            // Laponie (chiens de traîneau et aurores de décembre à mars)
            ['nom' => 'Safari en chiens de traîneau', 'destination' => 'laponie', 'prestataire' => 'arctique', 'categorie' => 'Chiens de traîneau', 'capacite' => 10, 'prix' => 180, 'duree' => 180, 'ageMinimum' => 6, 'envies' => ['aventure', 'nature', 'famille', 'neige'], 'photos' => ['traineau'], 'saisons' => $hiver],
            ['nom' => 'Chasse aux aurores boréales en motoneige', 'destination' => 'laponie', 'prestataire' => 'arctique', 'categorie' => 'Excursion nocturne', 'capacite' => 12, 'prix' => 150, 'duree' => 240, 'ageMinimum' => 12, 'envies' => ['nature', 'aventure', 'romantique', 'aurores boréales'], 'photos' => ['aurores'], 'saisons' => [[1, 3, 1.0], [9, 11, 0.9], [12, 12, 1.2]]],
            ['nom' => 'Visite du village du Père Noël', 'destination' => 'laponie', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 40, 'duree' => 180, 'accessible' => true, 'envies' => ['famille'], 'photos' => ['chalet']],
            // Amsterdam
            ['nom' => 'Balade à vélo et croisière sur les canaux', 'destination' => 'amsterdam', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 20, 'prix' => 55, 'duree' => 210, 'ageMinimum' => 10, 'envies' => ['culture', 'sport', 'famille'], 'photos' => ['velo']],
            ['nom' => 'Visite guidée du Rijksmuseum', 'destination' => 'amsterdam', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 50, 'duree' => 150, 'accessible' => true, 'envies' => ['culture'], 'photos' => ['musee']],
            // Prague
            ['nom' => 'Visite guidée du château et du pont Charles', 'destination' => 'prague', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 35, 'duree' => 210, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            ['nom' => 'Concert classique dans une église baroque', 'destination' => 'prague', 'prestataire' => 'saveurs', 'categorie' => 'Concert', 'capacite' => 80, 'prix' => 35, 'duree' => 75, 'accessible' => true, 'envies' => ['culture', 'romantique'], 'photos' => ['spectacle']],
            // Budapest
            ['nom' => 'Bains thermaux Széchenyi', 'destination' => 'budapest', 'prestataire' => 'bien-etre', 'categorie' => 'Bien-être', 'capacite' => 50, 'prix' => 30, 'duree' => 180, 'accessible' => true, 'envies' => ['détente', 'famille'], 'photos' => ['spa']],
            ['nom' => 'Dîner-croisière sur le Danube', 'destination' => 'budapest', 'prestataire' => 'saveurs', 'categorie' => 'Croisière', 'capacite' => 100, 'prix' => 75, 'duree' => 150, 'accessible' => true, 'envies' => ['romantique', 'gastronomie'], 'photos' => ['croisiere-fluviale']],
            ['nom' => 'Visite guidée de Buda et Pest', 'destination' => 'budapest', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 30, 'duree' => 180, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            // Tyrol (ski de décembre à avril)
            ['nom' => 'Forfait et cours de ski, 6 jours', 'destination' => 'tyrol', 'prestataire' => 'ecole-ski', 'categorie' => 'Ski', 'capacite' => 10, 'prix' => 390, 'duree' => 1080, 'ageMinimum' => 5, 'envies' => ['ski', 'sport', 'famille', 'neige'], 'photos' => ['ski'], 'saisons' => [[1, 3, 1.0], [4, 4, 0.85], [12, 12, 1.15]]],
            ['nom' => 'Randonnée guidée dans les alpages', 'destination' => 'tyrol', 'prestataire' => 'explora', 'categorie' => 'Randonnée', 'capacite' => 15, 'prix' => 55, 'duree' => 360, 'ageMinimum' => 8, 'envies' => ['nature', 'sport', 'famille'], 'photos' => ['randonnee'], 'saisons' => [[6, 9, 1.0]]],
            // Slovénie
            ['nom' => 'Visite guidée des grottes de Postojna', 'destination' => 'slovenie', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 40, 'prix' => 45, 'duree' => 150, 'accessible' => true, 'envies' => ['nature', 'famille'], 'photos' => ['visite-guidee']],
            ['nom' => 'Rafting sur la rivière Soča', 'destination' => 'slovenie', 'prestataire' => 'explora', 'categorie' => 'Sport nautique', 'capacite' => 8, 'prix' => 60, 'duree' => 180, 'ageMinimum' => 12, 'envies' => ['sport', 'aventure', 'nature'], 'photos' => ['canoe'], 'saisons' => [[5, 9, 1.0]]],
            // Chypre
            ['nom' => 'Visite guidée des mosaïques de Paphos', 'destination' => 'chypre', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 30, 'prix' => 35, 'duree' => 150, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            ['nom' => 'Croisière au lagon bleu d\'Akamas', 'destination' => 'chypre', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 40, 'prix' => 55, 'duree' => 300, 'envies' => ['plage', 'romantique', 'famille'], 'photos' => ['voilier']],
        ];
    }
}
