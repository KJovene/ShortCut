<?php

namespace App\Enum;

/** Type de voyage demandé par le client. */
enum TypeDeVoyage: string
{
    case Sejour = 'sejour';
    case Circuit = 'circuit';
    case Groupe = 'groupe';
    case Noces = 'noces';
    case Entreprise = 'entreprise';

    public function label(): string
    {
        return match ($this) {
            self::Sejour => 'Séjour',
            self::Circuit => 'Circuit',
            self::Groupe => 'Groupe',
            self::Noces => 'Voyage de noces',
            self::Entreprise => 'Entreprise',
        };
    }
}
