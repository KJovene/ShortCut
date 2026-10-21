<?php

namespace App\DataFixtures\Donnees;

use App\DataFixtures\UtilisateurFixtures;

/**
 * Catalogue de démo, Asie : 11 destinations, de Samarcande à Palawan.
 * Mêmes conventions que Catalogue (prix en euros, saisons, climat mois par mois).
 * Volontairement non couverts : au Népal, le trek des Annapurnas pendant la mousson (de juin à août).
 */
final class Asie
{
    /** @return array<string, array<string, mixed>> */
    public static function destinations(): array
    {
        return [
            'kyoto' => [
                'pays' => 'Japon', 'region' => 'Kansai', 'ville' => 'Kyoto', 'photo' => 'kyoto',
                'nom' => 'Kyoto, Japon', 'typesDeVoyage' => ['circuit', 'noces', 'groupe'], 'moisIdeal' => 4, 'latitude' => 35.0, 'longitude' => 135.8,
                'zones' => ['Asie', 'Asie de l\'Est', 'Extrême-Orient'],
                'photoAlt' => 'Ruelle pavée de Kyoto au crépuscule, bordée de maisons en bois, avec la pagode Yasaka en arrière-plan',
                'accroche' => 'Temples, jardins zen et cerisiers en fleurs, en ryokan.',
                'description' => 'L\'ancienne capitale impériale : plus de mille temples, les torii vermillon de Fushimi Inari, le quartier des geishas de Gion et la bambouseraie d\'Arashiyama. Nuit en ryokan, cérémonie du thé, puis Nara ou Osaka en train.',
                'pointsForts' => ['Cerisiers en avril, érables en novembre', 'Nuit en ryokan', 'Circuit en train avec le Japan Rail Pass', 'Cuisine japonaise'],
                'tempsDeTrajet' => ['Paris' => 810, 'Lyon' => 930, 'Bordeaux' => 960],
                'temperatures' => [5, 6, 9, 15, 20, 23, 27, 28, 24, 18, 12, 7],
                'pluie' => [50, 65, 105, 115, 150, 215, 220, 135, 175, 120, 70, 45],
                'saisons' => [[1, 2, 0.85], [3, 5, 1.3], [6, 6, 0.9], [7, 8, 1.0], [9, 9, 0.9], [10, 11, 1.25], [12, 12, 0.9]],
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'krabi' => [
                'pays' => 'Thaïlande', 'region' => 'Mer d\'Andaman', 'ville' => 'Krabi', 'photo' => 'krabi',
                'nom' => 'Krabi, Thaïlande', 'typesDeVoyage' => ['sejour', 'noces', 'groupe'], 'moisIdeal' => 1, 'latitude' => 8.0, 'longitude' => 98.8,
                'zones' => ['Asie', 'Asie du Sud-Est', 'Îles'],
                'photoAlt' => 'Bateaux longue-queue sur la plage de Phi Phi Don au coucher du soleil, face aux îles calcaires',
                'accroche' => 'Falaises calcaires, îles en bateau longue-queue et cuisine thaïe.',
                'description' => 'La mer d\'Andaman dans toute sa splendeur : plages de Railay au pied des falaises, îles Phi Phi et Hong en bateau longue-queue, kayak dans les mangroves et cuisine de rue. Douce et sèche de novembre à avril.',
                'pointsForts' => ['Îles en bateau longue-queue', 'Plages au pied des falaises', 'Cuisine thaïe', 'Massages thaïs'],
                'tempsDeTrajet' => ['Paris' => 780, 'Lyon' => 870, 'Bordeaux' => 900],
                'temperatures' => [27, 28, 29, 29, 29, 28, 28, 28, 27, 27, 27, 27],
                'pluie' => [30, 20, 50, 130, 300, 280, 300, 300, 350, 320, 200, 70],
                'saisons' => Catalogue::SAISONS_TROPIQUES,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'vietnam' => [
                'pays' => 'Vietnam', 'region' => 'Nord du Vietnam', 'ville' => 'Hanoï', 'photo' => 'vietnam',
                'nom' => 'Vietnam, baie d\'Halong', 'typesDeVoyage' => ['circuit', 'groupe', 'noces'], 'moisIdeal' => 11, 'latitude' => 21.0, 'longitude' => 105.8,
                'zones' => ['Asie', 'Asie du Sud-Est', 'Indochine'],
                'photoAlt' => 'Barques à rames et pêcheurs en chapeau conique dans la baie d\'Halong, devant des pitons calcaires',
                'accroche' => 'Baie d’Halong en jonque, rizières en terrasses et street food de Hanoï.',
                'description' => 'Du nord au centre : le vieux quartier de Hanoï et sa cuisine de rue, une nuit en jonque dans la baie d\'Halong, les rizières de Ninh Binh, puis Hué et les lanternes de Hoi An. Un circuit riche, francophone par endroits.',
                'pointsForts' => ['Nuit en jonque à Halong', 'Circuit de 10 à 15 jours', 'Street food', 'Hoi An et ses lanternes'],
                'tempsDeTrajet' => ['Paris' => 750, 'Lyon' => 870, 'Bordeaux' => 900],
                'temperatures' => [17, 18, 20, 24, 28, 30, 30, 29, 28, 26, 22, 19],
                'pluie' => [20, 25, 45, 90, 190, 240, 290, 320, 250, 130, 45, 20],
                'saisons' => [[1, 3, 1.0], [4, 4, 1.1], [5, 9, 0.85], [10, 11, 1.15], [12, 12, 1.05]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'angkor' => [
                'pays' => 'Cambodge', 'region' => 'Siem Reap', 'ville' => 'Siem Reap', 'photo' => 'angkor',
                'nom' => 'Cambodge, temples d\'Angkor', 'typesDeVoyage' => ['circuit', 'groupe'], 'moisIdeal' => 12, 'latitude' => 13.4, 'longitude' => 103.9,
                'zones' => ['Asie', 'Asie du Sud-Est', 'Indochine'],
                'photoAlt' => 'Tours sculptées du temple d\'Angkor Wat sous un ciel bleu parsemé de nuages blancs, au Cambodge',
                'accroche' => 'Lever du soleil sur Angkor Vat et villages sur pilotis du Tonlé Sap.',
                'description' => 'Les temples d\'Angkor, plus grand site religieux du monde : Angkor Vat au lever du soleil, les visages du Bayon, Ta Prohm envahi par la jungle, puis les villages flottants du lac Tonlé Sap. Se combine avec le Vietnam.',
                'pointsForts' => ['Angkor Vat au lever du soleil', 'Temples dans la jungle', 'Villages flottants', 'Extension du Vietnam'],
                'tempsDeTrajet' => ['Paris' => 900, 'Lyon' => 960, 'Bordeaux' => 990],
                'temperatures' => [25, 27, 29, 30, 30, 29, 28, 28, 28, 27, 26, 25],
                'pluie' => [5, 10, 35, 80, 175, 215, 215, 230, 250, 230, 90, 15],
                'saisons' => Catalogue::SAISONS_TROPIQUES,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'sri-lanka' => [
                'pays' => 'Sri Lanka', 'region' => 'Triangle culturel et côte sud', 'ville' => 'Colombo', 'photo' => 'sri-lanka',
                'nom' => 'Sri Lanka', 'typesDeVoyage' => ['circuit', 'noces'], 'moisIdeal' => 2, 'latitude' => 7.3, 'longitude' => 80.6,
                'zones' => ['Asie', 'Asie du Sud', 'Océan Indien', 'Îles'],
                'photoAlt' => 'Plantation de thé en terrasses sur des collines verdoyantes près de Nuwara Eliya, au Sri Lanka',
                'accroche' => 'Rocher de Sigiriya, plantations de thé et plages du sud.',
                'description' => 'L\'île aux mille visages : le rocher de Sigiriya, le temple de la Dent à Kandy, le train panoramique dans les plantations de thé, les éléphants de Minneriya puis les plages et le fort de Galle. Un circuit compact qui finit à la plage.',
                'pointsForts' => ['Circuit de 10 à 14 jours', 'Train dans les plantations de thé', 'Safari aux éléphants', 'Fin de séjour balnéaire'],
                'tempsDeTrajet' => ['Paris' => 690, 'Lyon' => 840, 'Bordeaux' => 870],
                'temperatures' => [27, 27, 28, 28, 28, 28, 27, 27, 27, 27, 27, 27],
                'pluie' => [60, 70, 120, 250, 330, 190, 120, 110, 160, 350, 320, 140],
                'saisons' => [[1, 3, 1.2], [4, 4, 1.0], [5, 6, 0.8], [7, 8, 1.1], [9, 11, 0.85], [12, 12, 1.3]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'rajasthan' => [
                'pays' => 'Inde', 'region' => 'Rajasthan', 'ville' => 'Jaipur', 'photo' => 'rajasthan',
                'nom' => 'Inde, Rajasthan', 'typesDeVoyage' => ['circuit', 'noces', 'groupe'], 'moisIdeal' => 2, 'latitude' => 26.9, 'longitude' => 75.8,
                'zones' => ['Asie', 'Asie du Sud', 'Sous-continent indien'],
                'photoAlt' => 'Façade rose aux innombrables fenêtres du Hawa Mahal, le palais des Vents de Jaipur, sous un ciel bleu',
                'accroche' => 'Palais de maharajas, forts du désert et Taj Mahal au lever du soleil.',
                'description' => 'Le Rajasthan des maharajas : Jaipur la rose, le fort d\'Amber, Udaipur et ses palais sur le lac, Jodhpur la bleue et le désert de Jaisalmer, avec le Taj Mahal d\'Agra en point d\'orgue. Nuits dans des palais transformés en hôtels.',
                'pointsForts' => ['Nuits dans des palais', 'Taj Mahal', 'Circuit avec chauffeur', 'Couleurs et épices'],
                'tempsDeTrajet' => ['Paris' => 600, 'Lyon' => 720, 'Bordeaux' => 750],
                'temperatures' => [15, 18, 23, 29, 33, 33, 30, 28, 28, 26, 21, 16],
                'pluie' => [10, 10, 10, 5, 15, 60, 200, 190, 70, 15, 5, 5],
                'saisons' => [[1, 3, 1.15], [4, 6, 0.8], [7, 9, 0.75], [10, 11, 1.1], [12, 12, 1.3]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'maldives' => [
                'pays' => 'Maldives', 'region' => 'Atoll de Malé Nord', 'ville' => 'Malé', 'photo' => 'maldives',
                'nom' => 'Maldives', 'typesDeVoyage' => ['noces', 'sejour'], 'moisIdeal' => 3, 'latitude' => 4.2, 'longitude' => 73.5,
                'zones' => ['Asie', 'Asie du Sud', 'Océan Indien', 'Îles'],
                'photoAlt' => 'Plage de sable blanc et bungalows au toit de chaume au bord d\'un lagon turquoise aux Maldives',
                'accroche' => 'Bungalows sur pilotis et lagons cristallins, l’île pour deux.',
                'description' => 'Une île, un hôtel : bungalows sur pilotis au-dessus du lagon, raies manta et requins-baleines en snorkeling, dîners sur un banc de sable. Le voyage de noces de rêve, de décembre à avril.',
                'pointsForts' => ['Bungalows sur pilotis', 'Snorkeling exceptionnel', 'Hydravion jusqu\'à l\'île', 'Formule tout compris'],
                'tempsDeTrajet' => ['Paris' => 660, 'Lyon' => 780, 'Bordeaux' => 810],
                'temperatures' => [28, 28, 29, 29, 29, 28, 28, 28, 28, 28, 28, 28],
                'pluie' => [80, 40, 60, 120, 220, 170, 150, 190, 230, 200, 220, 200],
                'saisons' => [[1, 4, 1.25], [5, 6, 0.85], [7, 8, 1.0], [9, 11, 0.85], [12, 12, 1.4]],
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'nepal' => [
                'pays' => 'Népal', 'region' => 'Vallée de Katmandou et Annapurnas', 'ville' => 'Katmandou', 'photo' => 'nepal',
                'nom' => 'Népal', 'typesDeVoyage' => ['circuit', 'groupe'], 'moisIdeal' => 10, 'latitude' => 27.7, 'longitude' => 85.3,
                'zones' => ['Asie', 'Asie du Sud', 'Himalaya', 'Montagne'],
                'photoAlt' => 'Drapeaux de prière tibétains flottant au vent devant les sommets enneigés de l\'Himalaya, au Népal',
                'accroche' => 'Trek face aux Annapurnas, temples de Katmandou et jungle de Chitwan.',
                'description' => 'Le toit du monde : temples et stupas de la vallée de Katmandou, Pokhara au bord du lac, trek face aux Annapurnas de lodge en lodge, puis le parc de Chitwan et ses rhinocéros. Meilleures saisons au printemps et à l\'automne.',
                'pointsForts' => ['Trek de lodge en lodge', 'Vallée de Katmandou', 'Rhinocéros de Chitwan', 'Groupes et associations'],
                'tempsDeTrajet' => ['Paris' => 720, 'Lyon' => 810, 'Bordeaux' => 840],
                'temperatures' => [10, 12, 16, 19, 22, 24, 24, 24, 23, 20, 15, 11],
                'pluie' => [15, 20, 35, 60, 120, 250, 370, 345, 190, 60, 8, 10],
                'saisons' => [[1, 2, 0.9], [3, 5, 1.15], [6, 8, 0.7], [9, 9, 1.0], [10, 11, 1.25], [12, 12, 0.9]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'palawan' => [
                'pays' => 'Philippines', 'region' => 'Palawan', 'ville' => 'El Nido', 'photo' => 'palawan',
                'nom' => 'Palawan, Philippines', 'typesDeVoyage' => ['sejour', 'noces'], 'moisIdeal' => 4, 'latitude' => 11.2, 'longitude' => 119.4,
                'zones' => ['Asie', 'Asie du Sud-Est', 'Îles', 'Pacifique'],
                'photoAlt' => 'Falaises calcaires couvertes de végétation plongeant dans l\'eau turquoise d\'El Nido, à Palawan',
                'accroche' => 'Lagons secrets, plages désertes et island hopping à El Nido.',
                'description' => 'L\'une des plus belles îles du monde : lagons cachés entre les falaises d\'El Nido, island hopping en bangka, plages désertes de Port Barton et rivière souterraine de Puerto Princesa. Une aventure balnéaire pour deux.',
                'pointsForts' => ['Island hopping', 'Lagons et falaises', 'Plages désertes', 'Snorkeling'],
                'tempsDeTrajet' => ['Paris' => 900, 'Lyon' => 990, 'Bordeaux' => 1020],
                'temperatures' => [27, 27, 28, 29, 29, 28, 28, 28, 28, 28, 28, 27],
                'pluie' => [20, 10, 20, 40, 170, 280, 330, 330, 300, 240, 170, 70],
                'saisons' => Catalogue::SAISONS_TROPIQUES,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'seoul' => [
                'pays' => 'Corée du Sud', 'region' => 'Capitale', 'ville' => 'Séoul', 'photo' => 'seoul',
                'nom' => 'Séoul, Corée du Sud', 'typesDeVoyage' => ['entreprise', 'groupe', 'circuit'], 'moisIdeal' => 10, 'latitude' => 37.6, 'longitude' => 127.0,
                'zones' => ['Asie', 'Asie de l\'Est', 'Extrême-Orient'],
                'photoAlt' => 'Pavillon Geunjeongjeon du palais Gyeongbokgung à Séoul, avec une montagne boisée en arrière-plan',
                'accroche' => 'Palais royaux, cuisine coréenne et culture K-pop, entre tradition et innovation.',
                'description' => 'Une mégapole fascinante : palais Gyeongbokgung, village de Bukchon, marchés de nuit, temples de montagne et quartiers de la K-pop. Séoul accueille salons et séminaires high-tech ; Busan et Gyeongju complètent un circuit.',
                'pointsForts' => ['Palais et hanoks', 'Cuisine coréenne', 'Salons et séminaires', 'Feuillages d\'automne'],
                'tempsDeTrajet' => ['Paris' => 780, 'Lyon' => 900, 'Bordeaux' => 930],
                'temperatures' => [-2, 1, 6, 13, 18, 23, 25, 26, 22, 15, 7, 0],
                'pluie' => [20, 25, 45, 75, 105, 135, 395, 365, 170, 50, 50, 20],
                'saisons' => [[1, 2, 0.85], [3, 3, 1.0], [4, 5, 1.2], [6, 8, 0.95], [9, 11, 1.2], [12, 12, 0.9]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'samarcande' => [
                'pays' => 'Ouzbékistan', 'region' => 'Route de la soie', 'ville' => 'Samarcande', 'photo' => 'samarcande',
                'nom' => 'Ouzbékistan, route de la soie', 'typesDeVoyage' => ['circuit', 'groupe'], 'moisIdeal' => 5, 'latitude' => 39.7, 'longitude' => 67.0,
                'zones' => ['Asie', 'Asie centrale', 'Route de la soie'],
                'photoAlt' => 'Place du Registan à Samarcande : trois madrasas ornées de mosaïques et coupoles bleues sous un ciel clair',
                'accroche' => 'Coupoles bleues de Samarcande, Boukhara et Khiva sur la route de la soie.',
                'description' => 'Les cités mythiques de la route de la soie : la place du Registan à Samarcande, la vieille ville de Boukhara, les remparts de Khiva, les bazars et l\'hospitalité ouzbèke. Un circuit culturel rare, au printemps ou en automne.',
                'pointsForts' => ['Place du Registan', 'Boukhara et Khiva', 'Train rapide entre les villes', 'Destination peu fréquentée'],
                'tempsDeTrajet' => ['Paris' => 420, 'Lyon' => 540, 'Bordeaux' => 560],
                'temperatures' => [2, 4, 10, 15, 20, 25, 27, 25, 20, 13, 8, 4],
                'pluie' => [45, 45, 70, 60, 35, 5, 3, 1, 3, 20, 35, 45],
                'saisons' => [[1, 2, 0.8], [3, 3, 0.9], [4, 6, 1.15], [7, 8, 0.95], [9, 10, 1.15], [11, 12, 0.8]],
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function prestataires(): array
    {
        return [
            'asia-boutique' => ['nom' => 'Asia Boutique Hotels', 'type' => 'hebergeur', 'contactNom' => 'Mei Tanaka', 'email' => 'contracts@asia-boutique.example', 'telephone' => '+81 75 000 0032', 'conditionsAnnulation' => 'Gratuite jusqu\'à 14 jours avant l\'arrivée ; 50 % entre 14 et 3 jours, puis 100 %.'],
            'tropiques-resorts' => ['nom' => 'Tropiques Resorts', 'type' => 'hebergeur', 'contactNom' => 'Somchai Prasert', 'email' => 'sales@tropiques-resorts.example', 'telephone' => '+66 75 000 033', 'conditionsAnnulation' => 'Gratuite jusqu\'à 30 jours avant l\'arrivée ; 50 % ensuite. Fêtes de fin d\'année non remboursables.'],
            'asie-express' => [
                'nom' => 'Asie Express Aviation', 'type' => 'transporteur', 'contactNom' => 'Pôle Asie', 'email' => 'tarifs@asie-express.example', 'telephone' => '+33 1 00 00 00 34',
                'conditionsAnnulation' => 'Frais de 150 € par personne jusqu\'à 30 jours avant le départ, puis non remboursable.',
                'vols' => [
                    'Kyoto' => ['Paris' => [750, 1500], 'Lyon' => [820, 1600], 'Bordeaux' => [850, 1650]],
                    'Krabi' => ['Paris' => [600, 1150], 'Lyon' => [680, 1250], 'Bordeaux' => [700, 1300]],
                    'Hanoï' => ['Paris' => [600, 1200], 'Lyon' => [680, 1300], 'Bordeaux' => [700, 1330]],
                    'Siem Reap' => ['Paris' => [650, 1250], 'Lyon' => [700, 1350], 'Bordeaux' => [720, 1380]],
                    'Colombo' => ['Paris' => [600, 1200], 'Lyon' => [660, 1280], 'Bordeaux' => [680, 1320]],
                    'Jaipur' => ['Paris' => [500, 1050], 'Lyon' => [560, 1150], 'Bordeaux' => [580, 1180]],
                    'Malé' => ['Paris' => [700, 1400], 'Lyon' => [760, 1500], 'Bordeaux' => [780, 1550]],
                    'Katmandou' => ['Paris' => [650, 1200], 'Lyon' => [720, 1300], 'Bordeaux' => [740, 1330]],
                    'El Nido' => ['Paris' => [750, 1400], 'Lyon' => [820, 1500], 'Bordeaux' => [840, 1530]],
                    'Séoul' => ['Paris' => [650, 1300], 'Lyon' => [720, 1400], 'Bordeaux' => [740, 1430]],
                    'Samarcande' => ['Paris' => [450, 900], 'Lyon' => [500, 980], 'Bordeaux' => [520, 1000]],
                ],
                // Circuits (Vietnam, Sri Lanka, Rajasthan, Népal, Ouzbékistan) : chauffeur ou trains compris ; Maldives : hydravion.
                'transferts' => ['Kyoto' => [40, 90], 'Krabi' => [15, 40], 'Hanoï' => [150, 350], 'Siem Reap' => [15, 40], 'Colombo' => [200, 450], 'Jaipur' => [220, 480], 'Malé' => [350, 700], 'Katmandou' => [120, 300], 'El Nido' => [60, 140], 'Séoul' => [25, 60], 'Samarcande' => [150, 320]],
            ],
            'guides-asie' => ['nom' => 'Guides d\'Asie francophones', 'type' => 'guide', 'contactNom' => 'Linh Nguyen', 'email' => 'guides@guides-asie.example', 'telephone' => '+84 24 000 0035', 'conditionsAnnulation' => 'Gratuite jusqu\'à 72 heures avant.'],
            'himalaya-treks' => ['nom' => 'Himalaya Treks', 'type' => 'guide', 'contactNom' => 'Pemba Sherpa', 'email' => 'treks@himalaya-treks.example', 'telephone' => '+977 1 000 0036', 'conditionsAnnulation' => 'Gratuite jusqu\'à 30 jours avant ; 30 % ensuite. Itinéraire adapté si la météo l\'impose.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function logements(): array
    {
        return [
            // Kyoto
            ['nom' => 'Ryokan de Gion, chambre traditionnelle', 'destination' => 'kyoto', 'prestataire' => 'asia-boutique', 'categorie' => 'Ryokan', 'capacite' => 2, 'chambres' => 1, 'prix' => 380, 'equipements' => ['wifi', 'onsen', 'dîner kaiseki', 'petit-déjeuner'], 'envies' => ['culture', 'romantique', 'gastronomie', 'détente'], 'photos' => ['ryokan'], 'description' => 'Chambre sur tatamis, bain chaud et dîner kaiseki de plusieurs services.'],
            ['nom' => 'Hôtel de la gare de Kyoto, chambre triple', 'destination' => 'kyoto', 'prestataire' => 'asia-boutique', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 160, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'ascenseur'], 'envies' => ['culture', 'famille'], 'photos' => ['hotel-ville']],
            ['nom' => 'Machiya d\'Higashiyama', 'destination' => 'kyoto', 'prestataire' => 'asia-boutique', 'categorie' => 'Maison traditionnelle', 'capacite' => 5, 'chambres' => 2, 'prix' => 290, 'sejourMinimum' => 2, 'equipements' => ['wifi', 'cuisine équipée', 'jardin intérieur'], 'envies' => ['culture', 'famille'], 'photos' => ['ryokan']],
            ['nom' => 'Hôtel de Kyoto, bloc groupe', 'destination' => 'kyoto', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 40, 'chambres' => 20, 'prix' => 4200, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['hotel-ville']],
            // Krabi
            ['nom' => 'Villa de Railay, piscine privée', 'destination' => 'krabi', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Villa de luxe', 'capacite' => 2, 'chambres' => 1, 'prix' => 280, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'piscine privée', 'climatisation', 'petit-déjeuner'], 'envies' => ['romantique', 'plage', 'détente'], 'photos' => ['villa-bali']],
            ['nom' => 'Resort d\'Ao Nang, chambre familiale', 'destination' => 'krabi', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 120, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'club enfants', 'petit-déjeuner'], 'envies' => ['plage', 'famille'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Bungalow de Klong Muang', 'destination' => 'krabi', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Bungalow', 'capacite' => 2, 'chambres' => 1, 'prix' => 60, 'equipements' => ['wifi', 'ventilateur', 'accès plage'], 'envies' => ['plage', 'nature'], 'photos' => ['ecolodge']],
            ['nom' => 'Krabi Beach Resort, bloc groupe', 'destination' => 'krabi', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 40, 'chambres' => 20, 'prix' => 2200, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'accès plage', 'restauration de groupe'], 'envies' => ['plage', 'détente'], 'photos' => ['plage']],
            // Vietnam
            ['nom' => 'Hôtel du vieux quartier de Hanoï', 'destination' => 'vietnam', 'prestataire' => 'asia-boutique', 'categorie' => 'Hôtel de charme', 'capacite' => 2, 'chambres' => 1, 'prix' => 90, 'equipements' => ['wifi', 'climatisation', 'petit-déjeuner'], 'envies' => ['culture', 'gastronomie'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Jonque de la baie d\'Halong, cabine', 'destination' => 'vietnam', 'prestataire' => 'asia-boutique', 'categorie' => 'Jonque', 'capacite' => 2, 'chambres' => 1, 'prix' => 260, 'equipements' => ['climatisation', 'pension complète', 'kayaks'], 'envies' => ['nature', 'romantique'], 'photos' => ['bateau'], 'description' => 'Une nuit à bord parmi les pitons calcaires, kayak et cours de cuisine compris.'],
            ['nom' => 'Resort de Hoi An, chambre familiale', 'destination' => 'vietnam', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 130, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'vélos', 'petit-déjeuner'], 'envies' => ['famille', 'culture', 'plage'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Écolodge des rizières de Ninh Binh', 'destination' => 'vietnam', 'prestataire' => 'asia-boutique', 'categorie' => 'Écolodge', 'capacite' => 3, 'chambres' => 1, 'prix' => 80, 'equipements' => ['wifi', 'petit-déjeuner', 'vélos'], 'envies' => ['nature'], 'photos' => ['ecolodge']],
            ['nom' => 'Hôtel de Hanoï, bloc groupe', 'destination' => 'vietnam', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 40, 'chambres' => 20, 'prix' => 1800, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['hotel-ville']],
            // Cambodge
            ['nom' => 'Hôtel colonial de Siem Reap', 'destination' => 'angkor', 'prestataire' => 'asia-boutique', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 140, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'spa', 'petit-déjeuner'], 'envies' => ['culture', 'détente', 'romantique'], 'photos' => ['hotel-piscine']],
            ['nom' => 'Villa khmère avec piscine', 'destination' => 'angkor', 'prestataire' => 'asia-boutique', 'categorie' => 'Villa', 'capacite' => 6, 'chambres' => 3, 'prix' => 220, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'piscine privée', 'climatisation', 'cuisinière sur place'], 'envies' => ['famille', 'détente'], 'photos' => ['villa']],
            ['nom' => 'Hôtel d\'Angkor, bloc groupe', 'destination' => 'angkor', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 40, 'chambres' => 20, 'prix' => 1900, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['hotel-piscine']],
            // Sri Lanka
            ['nom' => 'Lodge de Sigiriya', 'destination' => 'sri-lanka', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Lodge', 'capacite' => 3, 'chambres' => 1, 'prix' => 150, 'equipements' => ['wifi', 'piscine', 'demi-pension', 'vue sur le rocher'], 'envies' => ['nature', 'culture'], 'photos' => ['ecolodge']],
            ['nom' => 'Bungalow des plantations de thé', 'destination' => 'sri-lanka', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Maison de planteur', 'capacite' => 2, 'chambres' => 1, 'prix' => 190, 'equipements' => ['wifi', 'cheminée', 'pension complète'], 'envies' => ['nature', 'romantique', 'détente'], 'photos' => ['guesthouse']],
            ['nom' => 'Resort de la plage de Galle, chambre familiale', 'destination' => 'sri-lanka', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 170, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'accès plage', 'demi-pension'], 'envies' => ['plage', 'famille', 'détente'], 'photos' => ['plage']],
            // Rajasthan
            ['nom' => 'Palais de maharaja à Jaipur, suite', 'destination' => 'rajasthan', 'prestataire' => 'asia-boutique', 'categorie' => 'Palais-hôtel', 'capacite' => 2, 'chambres' => 1, 'prix' => 320, 'equipements' => ['wifi', 'climatisation', 'piscine', 'spa', 'petit-déjeuner'], 'envies' => ['culture', 'romantique', 'détente'], 'photos' => ['riad', 'hotel-piscine'], 'description' => 'Une suite dans l\'ancienne résidence d\'un maharaja, jardins moghols et dîner sur la terrasse.'],
            ['nom' => 'Haveli d\'Udaipur, chambre familiale', 'destination' => 'rajasthan', 'prestataire' => 'asia-boutique', 'categorie' => 'Haveli', 'capacite' => 4, 'chambres' => 1, 'prix' => 120, 'equipements' => ['wifi', 'climatisation', 'terrasse sur le lac', 'petit-déjeuner'], 'envies' => ['culture', 'famille'], 'photos' => ['riad']],
            ['nom' => 'Camp de luxe du désert de Jaisalmer', 'destination' => 'rajasthan', 'prestataire' => 'asia-boutique', 'categorie' => 'Camp dans le désert', 'capacite' => 2, 'chambres' => 1, 'prix' => 160, 'equipements' => ['pension complète', 'salle de bains privée'], 'envies' => ['aventure', 'romantique', 'désert'], 'photos' => ['desert']],
            ['nom' => 'Hôtel de Jaipur, bloc groupe', 'destination' => 'rajasthan', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 40, 'chambres' => 20, 'prix' => 2000, 'accessible' => true, 'equipements' => ['wifi', 'piscine', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['hotel-piscine']],
            // Maldives
            ['nom' => 'Bungalow sur pilotis, tout compris', 'destination' => 'maldives', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Bungalow sur pilotis', 'capacite' => 2, 'chambres' => 1, 'prix' => 780, 'sejourMinimum' => 4, 'equipements' => ['wifi', 'climatisation', 'tout compris', 'accès direct au lagon', 'spa'], 'envies' => ['romantique', 'plage', 'détente'], 'photos' => ['pilotis'], 'description' => 'Accès direct au lagon depuis la terrasse, dîner sur un banc de sable offert aux jeunes mariés.'],
            ['nom' => 'Villa de plage familiale', 'destination' => 'maldives', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Villa de plage', 'capacite' => 4, 'chambres' => 2, 'prix' => 650, 'sejourMinimum' => 4, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'pension complète', 'club enfants', 'accès plage'], 'envies' => ['famille', 'plage'], 'photos' => ['plage']],
            ['nom' => 'Guesthouse de Maafushi', 'destination' => 'maldives', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Maison d\'hôtes', 'capacite' => 2, 'chambres' => 1, 'prix' => 110, 'equipements' => ['wifi', 'climatisation', 'petit-déjeuner'], 'envies' => ['plage', 'sport'], 'photos' => ['guesthouse']],
            // Népal (trek de septembre à mai)
            ['nom' => 'Hôtel de Thamel, Katmandou', 'destination' => 'nepal', 'prestataire' => 'asia-boutique', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 70, 'equipements' => ['wifi', 'petit-déjeuner', 'jardin'], 'envies' => ['culture'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Lodge du lac de Pokhara', 'destination' => 'nepal', 'prestataire' => 'asia-boutique', 'categorie' => 'Lodge', 'capacite' => 4, 'chambres' => 2, 'prix' => 110, 'equipements' => ['wifi', 'demi-pension', 'vue sur les Annapurnas'], 'envies' => ['nature', 'détente', 'famille'], 'photos' => ['ecolodge']],
            ['nom' => 'Lodges de trek des Annapurnas, groupe', 'destination' => 'nepal', 'prestataire' => 'himalaya-treks', 'categorie' => 'Lodges de montagne', 'capacite' => 20, 'chambres' => 10, 'prix' => 600, 'equipements' => ['pension complète', 'porteurs'], 'envies' => ['nature', 'sport', 'aventure'], 'photos' => ['trek'], 'saisons' => [[1, 2, 0.9], [3, 5, 1.15], [9, 9, 1.0], [10, 11, 1.25], [12, 12, 0.9]]],
            // Palawan
            ['nom' => 'Resort d\'El Nido, cottage sur la plage', 'destination' => 'palawan', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 420, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'climatisation', 'pension complète', 'accès plage', 'kayaks'], 'envies' => ['romantique', 'plage', 'nature'], 'photos' => ['plage']],
            ['nom' => 'Bungalows de Port Barton', 'destination' => 'palawan', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Bungalow', 'capacite' => 4, 'chambres' => 2, 'prix' => 90, 'equipements' => ['ventilateur', 'accès plage', 'petit-déjeuner'], 'envies' => ['plage', 'famille', 'aventure'], 'photos' => ['ecolodge']],
            ['nom' => 'Hôtel de Puerto Princesa', 'destination' => 'palawan', 'prestataire' => 'tropiques-resorts', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 75, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine'], 'envies' => ['nature'], 'photos' => ['hotel-piscine']],
            // Séoul
            ['nom' => 'Hanok de Bukchon, chambre traditionnelle', 'destination' => 'seoul', 'prestataire' => 'asia-boutique', 'categorie' => 'Maison traditionnelle', 'capacite' => 2, 'chambres' => 1, 'prix' => 150, 'equipements' => ['wifi', 'chauffage au sol', 'petit-déjeuner coréen'], 'envies' => ['culture', 'romantique'], 'photos' => ['ryokan']],
            ['nom' => 'Hôtel de Myeongdong, chambre familiale', 'destination' => 'seoul', 'prestataire' => 'asia-boutique', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 180, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'ascenseur'], 'envies' => ['shopping', 'famille', 'gastronomie'], 'photos' => ['hotel-ville']],
            ['nom' => 'Seoul COEX Convention Hotel', 'destination' => 'seoul', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 100, 'chambres' => 50, 'prix' => 8500, 'accessible' => true, 'equipements' => ['wifi', 'salle de réunion 200 places', 'salles de sous-commission', 'traduction simultanée', 'restauration de groupe'], 'envies' => ['culture', 'shopping'], 'photos' => ['seminaire']],
            // Ouzbékistan
            ['nom' => 'Hôtel du Registan, Samarcande', 'destination' => 'samarcande', 'prestataire' => 'asia-boutique', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 3, 'chambres' => 1, 'prix' => 110, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'petit-déjeuner'], 'envies' => ['culture'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Maison d\'hôtes de Boukhara', 'destination' => 'samarcande', 'prestataire' => 'asia-boutique', 'categorie' => 'Maison d\'hôtes', 'capacite' => 2, 'chambres' => 1, 'prix' => 75, 'equipements' => ['wifi', 'climatisation', 'patio', 'petit-déjeuner'], 'envies' => ['culture', 'romantique'], 'photos' => ['riad']],
            ['nom' => 'Hôtel de Khiva, chambre familiale', 'destination' => 'samarcande', 'prestataire' => 'asia-boutique', 'categorie' => 'Hôtel 3 étoiles', 'capacite' => 4, 'chambres' => 1, 'prix' => 85, 'equipements' => ['wifi', 'climatisation', 'demi-pension'], 'envies' => ['culture', 'famille'], 'photos' => ['hotel-chambre']],
            ['nom' => 'Hôtel de Tachkent, bloc groupe', 'destination' => 'samarcande', 'prestataire' => 'groupes', 'categorie' => 'Hôtel 4 étoiles', 'capacite' => 40, 'chambres' => 20, 'prix' => 1700, 'accessible' => true, 'equipements' => ['wifi', 'restauration de groupe'], 'envies' => ['culture'], 'photos' => ['hotel-ville']],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function activites(): array
    {
        return [
            // Kyoto
            ['nom' => 'Visite guidée de Fushimi Inari et Gion', 'destination' => 'kyoto', 'prestataire' => 'guides-asie', 'categorie' => 'Visite guidée', 'capacite' => 20, 'prix' => 70, 'duree' => 300, 'envies' => ['culture'], 'photos' => ['temple']],
            ['nom' => 'Cérémonie du thé en kimono', 'destination' => 'kyoto', 'prestataire' => 'saveurs', 'categorie' => 'Atelier culturel', 'capacite' => 8, 'prix' => 60, 'duree' => 90, 'accessible' => true, 'envies' => ['culture', 'romantique', 'famille'], 'photos' => ['ryokan']],
            // Krabi
            ['nom' => 'Island hopping aux quatre îles en bateau longue-queue', 'destination' => 'krabi', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 20, 'prix' => 45, 'duree' => 420, 'envies' => ['plage', 'nature', 'famille'], 'photos' => ['bateau']],
            ['nom' => 'Kayak dans les mangroves d\'Ao Thalane', 'destination' => 'krabi', 'prestataire' => 'oceanes', 'categorie' => 'Sport nautique', 'capacite' => 12, 'prix' => 40, 'duree' => 180, 'ageMinimum' => 6, 'envies' => ['nature', 'sport'], 'photos' => ['kayak']],
            ['nom' => 'Massage thaï traditionnel', 'destination' => 'krabi', 'prestataire' => 'bien-etre', 'categorie' => 'Bien-être', 'capacite' => 2, 'prix' => 25, 'duree' => 90, 'ageMinimum' => 16, 'envies' => ['détente', 'romantique'], 'photos' => ['spa']],
            // Vietnam
            ['nom' => 'Street food dans le vieux quartier de Hanoï', 'destination' => 'vietnam', 'prestataire' => 'guides-asie', 'categorie' => 'Visite gourmande', 'capacite' => 12, 'prix' => 40, 'duree' => 180, 'envies' => ['gastronomie', 'culture'], 'photos' => ['marche']],
            ['nom' => 'Barque dans les rizières de Tam Coc', 'destination' => 'vietnam', 'prestataire' => 'guides-asie', 'categorie' => 'Excursion guidée', 'capacite' => 20, 'prix' => 45, 'duree' => 360, 'envies' => ['nature', 'famille'], 'photos' => ['canoe']],
            // Cambodge
            ['nom' => 'Lever du soleil à Angkor Vat avec un guide', 'destination' => 'angkor', 'prestataire' => 'guides-asie', 'categorie' => 'Visite guidée', 'capacite' => 20, 'prix' => 60, 'duree' => 480, 'envies' => ['culture', 'romantique'], 'photos' => ['temple']],
            ['nom' => 'Villages flottants du lac Tonlé Sap', 'destination' => 'angkor', 'prestataire' => 'guides-asie', 'categorie' => 'Excursion guidée', 'capacite' => 20, 'prix' => 35, 'duree' => 240, 'envies' => ['culture', 'nature', 'famille'], 'photos' => ['bateau']],
            // Sri Lanka
            ['nom' => 'Safari aux éléphants à Minneriya', 'destination' => 'sri-lanka', 'prestataire' => 'guides-asie', 'categorie' => 'Safari', 'capacite' => 6, 'prix' => 65, 'duree' => 240, 'ageMinimum' => 5, 'envies' => ['nature', 'famille', 'safari'], 'photos' => ['safari']],
            ['nom' => 'Ascension guidée du rocher de Sigiriya', 'destination' => 'sri-lanka', 'prestataire' => 'guides-asie', 'categorie' => 'Visite guidée', 'capacite' => 20, 'prix' => 45, 'duree' => 180, 'ageMinimum' => 8, 'envies' => ['culture', 'aventure'], 'photos' => ['temple']],
            // Rajasthan
            ['nom' => 'Visite guidée du fort d\'Amber et de Jaipur', 'destination' => 'rajasthan', 'prestataire' => 'guides-asie', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 40, 'duree' => 420, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            ['nom' => 'Cours de cuisine indienne dans une famille', 'destination' => 'rajasthan', 'prestataire' => 'saveurs', 'categorie' => 'Atelier culinaire', 'capacite' => 10, 'prix' => 35, 'duree' => 180, 'accessible' => true, 'envies' => ['gastronomie', 'famille'], 'photos' => ['cuisine']],
            // Maldives
            ['nom' => 'Snorkeling avec les raies manta', 'destination' => 'maldives', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 10, 'prix' => 120, 'duree' => 180, 'ageMinimum' => 8, 'envies' => ['nature', 'sport'], 'photos' => ['plongee']],
            ['nom' => 'Dîner privé sur un banc de sable', 'destination' => 'maldives', 'prestataire' => 'saveurs', 'categorie' => 'Expérience gastronomique', 'capacite' => 2, 'prix' => 250, 'duree' => 180, 'ageMinimum' => 18, 'envies' => ['romantique', 'gastronomie'], 'photos' => ['plage']],
            // Népal
            ['nom' => 'Trek de Poon Hill face aux Annapurnas, 4 jours', 'destination' => 'nepal', 'prestataire' => 'himalaya-treks', 'categorie' => 'Trek', 'capacite' => 12, 'prix' => 380, 'duree' => 1920, 'ageMinimum' => 12, 'envies' => ['nature', 'sport', 'aventure'], 'photos' => ['trek'], 'saisons' => [[1, 2, 0.9], [3, 5, 1.1], [9, 9, 1.0], [10, 11, 1.2], [12, 12, 0.9]]],
            ['nom' => 'Visite guidée des temples de la vallée de Katmandou', 'destination' => 'nepal', 'prestataire' => 'guides-asie', 'categorie' => 'Visite guidée', 'capacite' => 20, 'prix' => 45, 'duree' => 420, 'envies' => ['culture'], 'photos' => ['temple']],
            ['nom' => 'Safari en jeep dans le parc de Chitwan', 'destination' => 'nepal', 'prestataire' => 'himalaya-treks', 'categorie' => 'Safari', 'capacite' => 8, 'prix' => 70, 'duree' => 240, 'ageMinimum' => 6, 'envies' => ['nature', 'famille', 'safari'], 'photos' => ['safari']],
            // Palawan
            ['nom' => 'Island hopping dans l\'archipel de Bacuit', 'destination' => 'palawan', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 20, 'prix' => 50, 'duree' => 420, 'envies' => ['plage', 'nature', 'aventure'], 'photos' => ['bateau']],
            ['nom' => 'Rivière souterraine de Puerto Princesa', 'destination' => 'palawan', 'prestataire' => 'guides-monde', 'categorie' => 'Excursion guidée', 'capacite' => 20, 'prix' => 60, 'duree' => 480, 'envies' => ['nature', 'famille'], 'photos' => ['canoe']],
            // Séoul
            ['nom' => 'Visite guidée des palais et du village de Bukchon', 'destination' => 'seoul', 'prestataire' => 'guides-asie', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 50, 'duree' => 240, 'envies' => ['culture'], 'photos' => ['temple']],
            ['nom' => 'Marché de nuit et barbecue coréen', 'destination' => 'seoul', 'prestataire' => 'saveurs', 'categorie' => 'Visite gourmande', 'capacite' => 12, 'prix' => 55, 'duree' => 180, 'envies' => ['gastronomie'], 'photos' => ['marche']],
            // Ouzbékistan
            ['nom' => 'Visite guidée du Registan et de Shah-i-Zinda', 'destination' => 'samarcande', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 35, 'duree' => 300, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            ['nom' => 'Atelier de plov et dîner chez l\'habitant', 'destination' => 'samarcande', 'prestataire' => 'saveurs', 'categorie' => 'Atelier culinaire', 'capacite' => 12, 'prix' => 30, 'duree' => 180, 'envies' => ['gastronomie', 'culture', 'famille'], 'photos' => ['cuisine']],
        ];
    }
}
