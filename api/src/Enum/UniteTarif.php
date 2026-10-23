<?php

namespace App\Enum;

/** Unité d'un tarif par période. */
enum UniteTarif: string
{
    case ParNuit = 'par_nuit';
    case ParPersonne = 'par_personne';

    public function label(): string
    {
        return match ($this) {
            self::ParNuit => 'Par nuit',
            self::ParPersonne => 'Par personne',
        };
    }
}
