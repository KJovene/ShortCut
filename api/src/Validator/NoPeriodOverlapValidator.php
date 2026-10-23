<?php

namespace App\Validator;

use App\Entity\PeriodeClimatique;
use App\Entity\TarifParPeriode;
use App\Repository\PeriodeClimatiqueRepository;
use App\Repository\TarifParPeriodeRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NoPeriodOverlapValidator extends ConstraintValidator
{
    public function __construct(
        private readonly PeriodeClimatiqueRepository $periodesClimatiques,
        private readonly TarifParPeriodeRepository $tarifs,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoPeriodOverlap) {
            throw new UnexpectedTypeException($constraint, NoPeriodOverlap::class);
        }

        if (!$value instanceof PeriodeClimatique && !$value instanceof TarifParPeriode) {
            return;
        }

        $debut = $value->getDebut();
        $fin = $value->getFin();
        if (null === $debut || null === $fin || $debut > $fin) {
            return;
        }

        $overlap = $value instanceof PeriodeClimatique
            ? $this->periodesClimatiques->findOverlap($value)
            : $this->tarifs->findOverlap($value);

        if (null !== $overlap) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ subject }}', $value instanceof PeriodeClimatique ? 'cette destination' : 'cette prestation')
                ->atPath('debut')
                ->addViolation();
        }
    }
}
