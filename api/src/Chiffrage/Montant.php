<?php

namespace App\Chiffrage;

/** Écriture des montants pour le client : « 1 300 € », « 32,50 € ». Les calculs restent en centimes. */
final class Montant
{
    public static function euros(int $centimes): string
    {
        $decimales = 0 === $centimes % 100 ? 0 : 2;

        return number_format($centimes / 100, $decimales, ',', ' ').' €';
    }

    /** Montant en euros pour l'API Claude : entier si rond, sinon deux décimales au plus. */
    public static function nombre(int $centimes): int|float
    {
        return 0 === $centimes % 100 ? intdiv($centimes, 100) : round($centimes / 100, 2);
    }

    /** « 1 nuit », « 3 nuits ». */
    public static function quantite(int $nombre, string $singulier, ?string $pluriel = null): string
    {
        return \sprintf('%s %s', number_format($nombre, 0, ',', ' '), 1 === $nombre ? $singulier : ($pluriel ?? $singulier.'s'));
    }
}
