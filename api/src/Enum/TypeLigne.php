<?php

namespace App\Enum;

/** Nature d'une ligne de prestation dans une proposition. */
enum TypeLigne: string
{
    case Logement = 'logement';
    case Transport = 'transport';
    case Activite = 'activite';
    case Guide = 'guide';

    public function label(): string
    {
        return match ($this) {
            self::Logement => 'Logement',
            self::Transport => 'Transport',
            self::Activite => 'Activité',
            self::Guide => 'Guide',
        };
    }
}
