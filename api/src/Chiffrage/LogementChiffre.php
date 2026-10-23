<?php

namespace App\Chiffrage;

use App\Entity\Prestation;
use App\Entity\TarifParPeriode;

/** Un logement candidat chiffré pour son étape : coût, poste du détail, tarifs appliqués. Interne au chiffrage. */
final readonly class LogementChiffre
{
    /**
     * @param list<TarifParPeriode> $tarifs tarifs appliqués, dans l'ordre des nuits, sans doublon
     */
    public function __construct(
        public Prestation $logement,
        public Etape $etape,
        public int $unites,
        public Poste $poste,
        public array $tarifs,
    ) {
    }

    public function centimes(): int
    {
        return $this->poste->centimes;
    }
}
