<?php

namespace App\Chiffrage;

/**
 * Périodes écrites pour le client : « d'avril à juin 2027 » pour une saison en mois entiers,
 * « du 14/05/2027 au 17/05/2027 » sinon.
 */
final class Periode
{
    private const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /** Période d'un tarif ou d'une saison. */
    public static function ecrire(\DateTimeImmutable $debut, \DateTimeImmutable $fin): string
    {
        $moisEntiers = '01' === $debut->format('d') && $fin->format('Y-m-d') === $fin->modify('last day of this month')->format('Y-m-d');
        if (!$moisEntiers) {
            return \sprintf('du %s au %s', $debut->format('d/m/Y'), $fin->format('d/m/Y'));
        }

        $premier = self::mois($debut);
        $dernier = self::mois($fin);
        if ($debut->format('Y-m') === $fin->format('Y-m')) {
            return \sprintf('%s %s', self::de($premier), $debut->format('Y'));
        }
        if ($debut->format('Y') === $fin->format('Y')) {
            return \sprintf('%s à %s %s', self::de($premier), $dernier, $fin->format('Y'));
        }

        return \sprintf('%s %s à %s %s', self::de($premier), $debut->format('Y'), $dernier, $fin->format('Y'));
    }

    /** Dates d'un séjour : « du 21/10/2027 au 25/10/2027 » (jour d'arrivée et jour de départ). */
    public static function sejour(\DateTimeImmutable $arrivee, int $nuits): string
    {
        return \sprintf('du %s au %s', $arrivee->format('d/m/Y'), $arrivee->modify(\sprintf('+%d days', $nuits))->format('d/m/Y'));
    }

    /**
     * « vendredi 14 mai 2027 », « samedi 1er mai 2027 ». Sans IntlDateFormatter : l'image PHP n'embarque que les
     * données ICU anglaises.
     */
    public static function jour(\DateTimeImmutable $date): string
    {
        $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
        $numero = (int) $date->format('j');

        return \sprintf('%s %s %s %s', $jours[(int) $date->format('N') - 1], 1 === $numero ? '1er' : $numero, self::mois($date), $date->format('Y'));
    }

    private static function mois(\DateTimeImmutable $date): string
    {
        return self::MOIS[(int) $date->format('n') - 1];
    }

    /** « de mai », « d'avril », « d'août », « d'octobre ». */
    private static function de(string $mois): string
    {
        return \in_array($mois[0], ['a', 'o'], true) ? 'd\''.$mois : 'de '.$mois;
    }
}
