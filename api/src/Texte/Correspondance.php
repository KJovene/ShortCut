<?php

namespace App\Texte;

use function Symfony\Component\String\u;

/**
 * Rapproche deux textes saisis à la main (villes, envies) sans exiger la même écriture :
 * « lisbonne », « LISBONNE », « Lisbone » et « Lisbonne (Portugal) » désignent la même ville.
 *
 * Règles, sur les textes normalisés (minuscules, sans accents ni ponctuation) :
 * - identiques ;
 * - l'un contient l'autre en mots entiers (« aeroport de lisbonne » contient « lisbonne ») ;
 * - une faute de frappe : 1 lettre d'écart de 5 à 8 lettres, 2 au-delà. En dessous de 5 lettres,
 *   aucune tolérance : « Rome » et « Roma », « Lyon » et « Lion » restent différents.
 */
final class Correspondance
{
    public static function normaliser(string $texte): string
    {
        $ascii = u($texte)->ascii()->lower()->toString();

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }

    public static function correspondent(string $a, string $b): bool
    {
        $a = self::normaliser($a);
        $b = self::normaliser($b);
        if ('' === $a || '' === $b) {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        [$court, $long] = \strlen($a) <= \strlen($b) ? [$a, $b] : [$b, $a];
        if (\strlen($court) >= 4 && str_contains(' '.$long.' ', ' '.$court.' ')) {
            return true;
        }

        $tolerance = match (true) {
            \strlen($court) < 5 => 0,
            \strlen($court) <= 8 => 1,
            default => 2,
        };

        return $tolerance > 0 && levenshtein($a, $b) <= $tolerance;
    }

    /**
     * Les éléments de $candidats qui correspondent à $recherche.
     *
     * @param iterable<string> $candidats
     *
     * @return list<string>
     */
    public static function filtrer(string $recherche, iterable $candidats): array
    {
        $retenus = [];
        foreach ($candidats as $candidat) {
            if (self::correspondent($recherche, $candidat)) {
                $retenus[] = $candidat;
            }
        }

        return $retenus;
    }

    /**
     * Les éléments de $cherches qui correspondent à au moins un de $proposes : les envies du client que l'offre
     * satisfait, selon les mêmes règles (« plages » correspond à « plage », « gastronomique » à « gastronomie »).
     *
     * @param list<string> $cherches
     * @param list<string> $proposes
     *
     * @return list<string>
     */
    public static function communs(array $cherches, array $proposes): array
    {
        return array_values(array_filter($cherches, static fn (string $cherche): bool => [] !== self::filtrer($cherche, $proposes)));
    }
}
