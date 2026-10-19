<?php

namespace App\Chiffrage;

/** Point retenu dans la fourchette de prix d'un transport, selon le niveau (docs/chiffrage.md). */
enum PositionTransport: string
{
    case Minimum = 'minimum';
    case Milieu = 'milieu';
    case Maximum = 'maximum';

    public function label(): string
    {
        return match ($this) {
            self::Minimum => 'minimum de la fourchette',
            self::Milieu => 'milieu de la fourchette',
            self::Maximum => 'maximum de la fourchette',
        };
    }

    /** Prix retenu dans [min, max], au centime ; le milieu est arrondi au centime supérieur. */
    public function prix(int $minCentimes, int $maxCentimes): int
    {
        return match ($this) {
            self::Minimum => $minCentimes,
            self::Milieu => intdiv($minCentimes + $maxCentimes + 1, 2),
            self::Maximum => $maxCentimes,
        };
    }
}
