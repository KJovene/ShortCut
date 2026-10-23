<?php

namespace App\Enum;

/** Pourquoi une demande a été transmise à un conseiller (US06). */
enum MotifTransmission: string
{
    case DemandeDuClient = 'demande_du_client';
    case DonneesManquantes = 'donnees_manquantes';
    case HorsPerimetre = 'hors_perimetre';
    case HorsCatalogue = 'hors_catalogue';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::DemandeDuClient => 'Le client a demandé un conseiller',
            self::DonneesManquantes => 'Période sans données dans le catalogue',
            self::HorsPerimetre => 'Hors de ce que sait faire le chatbot',
            self::HorsCatalogue => 'Destination absente du catalogue',
            self::Autre => 'Autre',
        };
    }
}
