<?php

namespace App\Proposition;

use Symfony\Component\Validator\Constraints as Assert;

/** Les points forts d'un logement ou d'une activité retenus, présentés au client. */
final class PointsForts
{
    /**
     * @param list<string> $pointsForts
     */
    public function __construct(
        #[Assert\Positive]
        public readonly int $id,
        #[Assert\Count(min: 1, max: 4)]
        #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 200)])]
        public readonly array $pointsForts,
    ) {
    }
}
