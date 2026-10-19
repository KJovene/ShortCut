<?php

namespace App\Chiffrage;

use App\Entity\Prestation;

/** Une étape du voyage : ses nuits et les logements compatibles parmi lesquels choisir. */
final readonly class Etape
{
    /**
     * @param list<Prestation> $logements
     */
    public function __construct(
        public \DateTimeImmutable $dateDebut,
        public int $nombreDeNuits,
        public array $logements,
    ) {
        if ($nombreDeNuits < 1) {
            throw new \InvalidArgumentException('Une étape compte au moins une nuit.');
        }
        if ([] === $logements) {
            throw new \InvalidArgumentException('Chaque étape a besoin d\'au moins un logement compatible.');
        }
    }

    /** Date de la nuit n° $index (0 pour la première). */
    public function nuit(int $index): \DateTimeImmutable
    {
        return $this->dateDebut->modify(\sprintf('+%d days', $index));
    }
}
