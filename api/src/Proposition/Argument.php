<?php

namespace App\Proposition;

use Symfony\Component\Validator\Constraints as Assert;

/** Une exigence du client et ce qui, dans la proposition, y répond. */
final class Argument
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public readonly string $exigence,
        #[Assert\NotBlank]
        #[Assert\Length(max: 600)]
        public readonly string $reponse,
    ) {
    }
}
