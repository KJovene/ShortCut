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

    /** Pour un client qui écrit en anglais (US15). */
    public function labelEnAnglais(): string
    {
        return match ($this) {
            self::Essentiel => 'Essential',
            self::Equilibre => 'Balanced',
            self::Confort => 'Comfort',
        };
    }
}
