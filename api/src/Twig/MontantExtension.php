<?php

namespace App\Twig;

use App\Chiffrage\Montant;
use Twig\Attribute\AsTwigFilter;

/** Montants du backoffice, écrits comme pour le client : « 1 300 € », « +45,50 € », « −3,8 % ». */
final class MontantExtension
{
    /** Montant en centimes : « 1 300 € ». */
    #[AsTwigFilter('euros')]
    public static function euros(int $centimes): string
    {
        return Montant::euros($centimes);
    }

    /** Écart signé en centimes : « +120 € », « −45,50 € », « 0 € ». */
    #[AsTwigFilter('ecart_euros')]
    public static function ecartEuros(int $centimes): string
    {
        return self::signe($centimes).Montant::euros(abs($centimes));
    }

    /** Écart signé en pourcentage : « +3,8 % », « −12 % ». */
    #[AsTwigFilter('ecart_pourcentage')]
    public static function ecartPourcentage(float $pourcentage): string
    {
        $decimales = floor(abs($pourcentage)) === abs($pourcentage) ? 0 : 1;

        return self::signe($pourcentage).number_format(abs($pourcentage), $decimales, ',', ' ').' %';
    }

    private static function signe(int|float $valeur): string
    {
        return match (true) {
            $valeur > 0 => '+',
            $valeur < 0 => '−',
            default => '',
        };
    }
}
