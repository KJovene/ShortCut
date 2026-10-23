<?php

namespace App\Enum;

/** Une prestation est un logement ou une activité. */
enum TypePrestation: string
{
    case Logement = 'logement';
    case Activite = 'activite';

    public function label(): string
    {
        return match ($this) {
            self::Logement => 'Logement',
            self::Activite => 'Activité',
        };
    }
}
