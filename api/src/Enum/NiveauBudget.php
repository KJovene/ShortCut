<?php

namespace App\Enum;

/** Les trois niveaux de budget de chaque proposition. */
enum NiveauBudget: string
{
    case Essentiel = 'essentiel';
    case Equilibre = 'equilibre';
    case Confort = 'confort';

    public function label(): string
    {
        return match ($this) {
            self::Essentiel => 'Essentiel',
            self::Equilibre => 'Équilibré',
            self::Confort => 'Confort',
        };
    }
}
