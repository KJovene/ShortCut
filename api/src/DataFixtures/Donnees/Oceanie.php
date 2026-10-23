<?php

namespace App\DataFixtures\Donnees;

use App\DataFixtures\UtilisateurFixtures;

/**
 * Catalogue de démo, Océanie : Sydney et la Nouvelle-Zélande, les antipodes.
 * Mêmes conventions que Catalogue (prix en euros, saisons, climat mois par mois).
 */
final class Oceanie
{
    /** @return array<string, array<string, mixed>> */
    public static function destinations(): array
    {
        return [
            'sydney' => [
                'pays' => 'Australie', 'region' => 'Nouvelle-Galles du Sud', 'ville' => 'Sydney', 'photo' => 'sydney',
                'nom' => 'Sydney, Australie', 'typesDeVoyage' => ['circuit', 'sejour', 'noces'], 'moisIdeal' => 11, 'latitude' => -33.9, 'longitude' => 151.2,
                'zones' => ['Océanie', 'Pacifique', 'Hémisphère sud'],
                'photoAlt' => 'Harbour Bridge et Opéra de Sydney se reflétant dans la baie sous un ciel embrasé au coucher du soleil',
                'accroche' => 'Opéra, plages de surf et Montagnes bleues, l’été austral à Noël.',
                'description' => 'Sydney, sa baie, son Opéra et la plage de Bondi, puis les Montagnes bleues, la Grande Barrière de corail et le rocher d\'Uluru pour un grand circuit. L\'été austral, de novembre à mars, rime avec Noël à la plage.',
                'pointsForts' => ['Grand circuit de 3 semaines', 'Grande Barrière de corail', 'Plages de surf', 'Faune unique'],
                'tempsDeTrajet' => ['Paris' => 1320, 'Lyon' => 1380, 'Bordeaux' => 1410],
                'temperatures' => [23, 23, 22, 19, 16, 14, 13, 14, 16, 18, 20, 22],
                'pluie' => [100, 115, 130, 125, 120, 130, 100, 80, 70, 75, 85, 80],
                'saisons' => Catalogue::SAISONS_HEMISPHERE_SUD,
                'miseEnAvant' => false, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
            'nouvelle-zelande' => [
                'pays' => 'Nouvelle-Zélande', 'region' => 'Otago, île du Sud', 'ville' => 'Queenstown', 'photo' => 'nouvelle-zelande',
                'nom' => 'Nouvelle-Zélande', 'typesDeVoyage' => ['circuit', 'noces'], 'moisIdeal' => 2, 'latitude' => -45.0, 'longitude' => 168.7,
                'zones' => ['Océanie', 'Pacifique', 'Montagne', 'Îles', 'Hémisphère sud'],
                'photoAlt' => 'Vue aérienne du fjord de Milford Sound entre des montagnes boisées, avec un bateau sur l\'eau',
                'accroche' => 'Fjords, glaciers et lacs turquoise, au bout du monde en van ou en voiture.',
                'description' => 'Le bout du monde grandeur nature : Milford Sound, les glaciers de la côte ouest, les lacs turquoise de Tekapo, Queenstown capitale de l\'aventure, puis la culture maorie et les geysers de Rotorua sur l\'île du Nord. Un autotour inoubliable.',
                'pointsForts' => ['Autotour de 3 semaines', 'Milford Sound', 'Sports d\'aventure', 'Culture maorie'],
                'tempsDeTrajet' => ['Paris' => 1560, 'Lyon' => 1620, 'Bordeaux' => 1650],
                'temperatures' => [16, 16, 14, 11, 7, 4, 3, 5, 8, 10, 12, 15],
                'pluie' => [80, 70, 75, 75, 80, 70, 65, 70, 70, 85, 80, 90],
                'saisons' => [[1, 3, 1.2], [4, 5, 0.9], [6, 9, 1.0], [10, 11, 0.95], [12, 12, 1.25]],
                'miseEnAvant' => true, 'saisiPar' => UtilisateurFixtures::HUGO,
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function prestataires(): array
    {
        return [
            'pacific-stays' => ['nom' => 'Pacific Stays', 'type' => 'hebergeur', 'contactNom' => 'Olivia Brown', 'email' => 'bookings@pacific-stays.example', 'telephone' => '+61 2 0000 0042', 'conditionsAnnulation' => 'Gratuite jusqu\'à 30 jours avant l\'arrivée ; première nuit due ensuite.'],
            'antipodes' => [
                'nom' => 'Antipodes Airlines', 'type' => 'transporteur', 'contactNom' => 'Pôle Pacifique', 'email' => 'tarifs@antipodes.example', 'telephone' => '+33 1 00 00 00 43',
                'conditionsAnnulation' => 'Frais de 250 € par personne jusqu\'à 60 jours avant le départ, puis non remboursable.',
                'vols' => [
                    'Sydney' => ['Paris' => [1100, 2100], 'Lyon' => [1180, 2200], 'Bordeaux' => [1200, 2250]],
                    'Queenstown' => ['Paris' => [1300, 2400], 'Lyon' => [1380, 2500], 'Bordeaux' => [1400, 2550]],
                ],
                // Autotours : voiture ou van de location et vols intérieurs compris.
                'transferts' => ['Sydney' => [300, 700], 'Queenstown' => [350, 800]],
            ],
            'kiwi-aventures' => ['nom' => 'Kiwi Aventures', 'type' => 'activite', 'contactNom' => 'Aroha Ngata', 'email' => 'kiaora@kiwi-aventures.example', 'telephone' => '+64 3 000 0044', 'conditionsAnnulation' => 'Gratuite jusqu\'à 48 heures avant ; reportée ou remboursée si la météo l\'impose.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function logements(): array
    {
        return [
            // Sydney
            ['nom' => 'Hôtel de The Rocks, vue sur l\'Opéra', 'destination' => 'sydney', 'prestataire' => 'pacific-stays', 'categorie' => 'Hôtel 5 étoiles', 'capacite' => 2, 'chambres' => 1, 'prix' => 360, 'accessible' => true, 'equipements' => ['wifi', 'climatisation', 'piscine', 'vue sur la baie'], 'envies' => ['romantique', 'culture'], 'photos' => ['hotel-ville']],
            ['nom' => 'Appartement de Bondi Beach', 'destination' => 'sydney', 'prestataire' => 'pacific-stays', 'categorie' => 'Appartement', 'capacite' => 4, 'chambres' => 2, 'prix' => 240, 'sejourMinimum' => 3, 'equipements' => ['wifi', 'cuisine équipée', 'balcon vue mer'], 'envies' => ['plage', 'famille', 'sport'], 'photos' => ['appartement']],
            ['nom' => 'Lodge des Montagnes bleues', 'destination' => 'sydney', 'prestataire' => 'pacific-stays', 'categorie' => 'Lodge', 'capacite' => 4, 'chambres' => 2, 'prix' => 220, 'equipements' => ['wifi', 'cheminée', 'demi-pension'], 'envies' => ['nature', 'famille'], 'photos' => ['ecolodge']],
            // Nouvelle-Zélande
            ['nom' => 'Lodge du lac Wakatipu, Queenstown', 'destination' => 'nouvelle-zelande', 'prestataire' => 'pacific-stays', 'categorie' => 'Lodge de luxe', 'capacite' => 2, 'chambres' => 1, 'prix' => 380, 'equipements' => ['wifi', 'spa', 'vue sur le lac', 'petit-déjeuner'], 'envies' => ['romantique', 'nature', 'détente'], 'photos' => ['chalet']],
            ['nom' => 'Van aménagé pour l\'île du Sud', 'destination' => 'nouvelle-zelande', 'prestataire' => 'pacific-stays', 'categorie' => 'Van aménagé', 'capacite' => 4, 'chambres' => 1, 'prix' => 180, 'sejourMinimum' => 7, 'equipements' => ['cuisine équipée', 'couchage 4 places', 'kilométrage illimité'], 'envies' => ['aventure', 'nature', 'famille'], 'photos' => ['randonnee']],
            ['nom' => 'Motel de Franz Josef, au pied du glacier', 'destination' => 'nouvelle-zelande', 'prestataire' => 'pacific-stays', 'categorie' => 'Motel', 'capacite' => 3, 'chambres' => 1, 'prix' => 140, 'accessible' => true, 'equipements' => ['wifi', 'kitchenette', 'parking'], 'envies' => ['nature'], 'photos' => ['guesthouse']],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function activites(): array
    {
        return [
            // Sydney
            ['nom' => 'Snorkeling sur la Grande Barrière de corail', 'destination' => 'sydney', 'prestataire' => 'oceanes', 'categorie' => 'Sortie en mer', 'capacite' => 30, 'prix' => 190, 'duree' => 480, 'ageMinimum' => 6, 'envies' => ['nature', 'sport', 'famille'], 'photos' => ['plongee']],
            ['nom' => 'Visite guidée de l\'Opéra et de la baie de Sydney', 'destination' => 'sydney', 'prestataire' => 'guides-monde', 'categorie' => 'Visite guidée', 'capacite' => 25, 'prix' => 55, 'duree' => 180, 'accessible' => true, 'envies' => ['culture'], 'photos' => ['visite-guidee']],
            // Nouvelle-Zélande
            ['nom' => 'Croisière dans le fjord de Milford Sound', 'destination' => 'nouvelle-zelande', 'prestataire' => 'kiwi-aventures', 'categorie' => 'Croisière', 'capacite' => 80, 'prix' => 120, 'duree' => 600, 'accessible' => true, 'envies' => ['nature', 'famille', 'romantique'], 'photos' => ['croisiere-fluviale']],
            ['nom' => 'Saut à l\'élastique et jet-boat à Queenstown', 'destination' => 'nouvelle-zelande', 'prestataire' => 'kiwi-aventures', 'categorie' => 'Sport d\'aventure', 'capacite' => 10, 'prix' => 210, 'duree' => 240, 'ageMinimum' => 15, 'envies' => ['aventure', 'sport'], 'photos' => ['canoe']],
            ['nom' => 'Randonnée guidée sur le glacier Franz Josef', 'destination' => 'nouvelle-zelande', 'prestataire' => 'explora', 'categorie' => 'Randonnée', 'capacite' => 12, 'prix' => 280, 'duree' => 240, 'ageMinimum' => 10, 'envies' => ['nature', 'aventure', 'sport'], 'photos' => ['trek']],
        ];
    }
}
