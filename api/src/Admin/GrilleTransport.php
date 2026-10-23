<?php

namespace App\Admin;

/**
 * Les fourchettes de prix d'un transporteur en grille : une ligne par ville d'arrivée, une colonne par ville de
 * départ des vols, et le transfert sur place (départ « Aéroport de Louxor » pour l'arrivée « Louxor »).
 */
final class GrilleTransport
{
    /**
     * @param list<array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}> $fourchettes
     *
     * @return array{departs: list<string>, lignes: list<array{arrivee: string, vols: array<string, array{min: int, max: int}>, transfert: ?array{min: int, max: int}}>}
     */
    public static function depuis(array $fourchettes): array
    {
        $departs = [];
        $lignes = [];
        foreach ($fourchettes as $f) {
            $arrivee = $f['arrivee'];
            $lignes[$arrivee] ??= ['arrivee' => $arrivee, 'vols' => [], 'transfert' => null];
            $prix = ['min' => $f['minCentimes'], 'max' => $f['maxCentimes']];
            if (self::estUnTransfert($f['depart'], $arrivee)) {
                $lignes[$arrivee]['transfert'] = $prix;
                continue;
            }
            $departs[$f['depart']] = true;
            $lignes[$arrivee]['vols'][$f['depart']] = $prix;
        }

        return ['departs' => array_keys($departs), 'lignes' => array_values($lignes)];
    }

    private static function estUnTransfert(string $depart, string $arrivee): bool
    {
        return mb_strtolower($depart) !== mb_strtolower($arrivee)
            && str_contains(mb_strtolower($depart), mb_strtolower($arrivee));
    }
}
