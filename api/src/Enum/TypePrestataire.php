<?php

namespace App\Enum;

/** Métier du prestataire. */
enum TypePrestataire: string
{
    case Hebergeur = 'hebergeur';
    case Transporteur = 'transporteur';
    case Guide = 'guide';
    case Activite = 'activite';

    public function label(): string
    {
        return match ($this) {
            self::Hebergeur => 'Hébergeur',
            self::Transporteur => 'Transporteur',
            self::Guide => 'Guide',
            self::Activite => 'Prestataire d\'activités',
        };
    }
}
