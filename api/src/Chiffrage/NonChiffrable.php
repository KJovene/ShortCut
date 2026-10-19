<?php

namespace App\Chiffrage;

/**
 * Une prestation indispensable n'a pas de prix (tarif manquant, fourchette de transport absente) : le chiffrage
 * s'arrête au lieu d'inventer un montant (docs/chiffrage.md, « Données manquantes »).
 */
final class NonChiffrable extends \RuntimeException
{
    public function __construct(
        public readonly string $prestation,
        public readonly ?\DateTimeImmutable $date,
        public readonly string $raison,
    ) {
        parent::__construct(\sprintf('%s : %s', $prestation, $raison));
    }
}
