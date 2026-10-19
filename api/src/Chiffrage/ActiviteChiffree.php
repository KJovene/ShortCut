<?php

namespace App\Chiffrage;

use App\Entity\Prestation;

/** Une activité candidate chiffrée : participants (selon l'âge minimum), sessions, poste du détail. Interne. */
final readonly class ActiviteChiffree
{
    public function __construct(
        public Prestation $activite,
        public int $participants,
        public int $sessions,
        public Poste $poste,
    ) {
    }

    public function centimes(): int
    {
        return $this->poste->centimes;
    }
}
