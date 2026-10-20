<?php

namespace App\Proposition;

use Symfony\Component\Validator\Constraints as Assert;

/** Un jour du programme : « Jour 3, Croisière dans la caldeira ». */
final class JourDuProgramme
{
    /**
     * @param list<int> $activiteIds activités du niveau détaillé placées ce jour-là
     */
    public function __construct(
        public readonly int $jour,
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public readonly string $titre,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1500)]
        public readonly string $description,
        #[Assert\All([new Assert\Positive()])]
        public readonly array $activiteIds = [],
    ) {
    }
}
