<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoPeriodOverlap extends Constraint
{
    public string $message = 'Cette période chevauche déjà une période existante pour {{ subject }}.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
