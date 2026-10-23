<?php

namespace App\Confirmation;

/**
 * Prix confirmés face à l'estimation, ligne par ligne et au total. Le total se compare à la proposition envoyée au
 * client ; tant qu'un prix manque, les montants confirmés du total restent null.
 */
final readonly class Comparaison
{
    /**
     * @param array<int, Ecart> $lignes     par id de ligne
     * @param list<string>      $manquantes libellés des lignes sans prix confirmé
     */
    public function __construct(
        public array $lignes,
        public Ecart $cout,
        public Ecart $marge,
        public Ecart $fraisDeDossier,
        public Ecart $total,
        public Ecart $parPersonne,
        public array $manquantes,
    ) {
    }

    public function complete(): bool
    {
        return [] === $this->manquantes;
    }
}
