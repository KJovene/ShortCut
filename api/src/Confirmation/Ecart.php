<?php

namespace App\Confirmation;

/** Écart entre un montant estimé et le montant confirmé par le conseiller. Montants en centimes. */
final readonly class Ecart
{
    /** Au-delà, l'écart est signalé : l'agence vise moins de 10 % entre estimé et confirmé (cahier des charges). */
    public const SEUIL_POURCENTAGE = 10;

    public function __construct(
        public int $estimeCentimes,
        public ?int $confirmeCentimes,
    ) {
    }

    /** Confirmé moins estimé : positif si le prix monte ; null tant que le prix confirmé n'est pas saisi. */
    public function centimes(): ?int
    {
        return null === $this->confirmeCentimes ? null : $this->confirmeCentimes - $this->estimeCentimes;
    }

    /** En pourcentage de l'estimé, à une décimale ; null sans prix confirmé ou si l'estimé vaut 0. */
    public function pourcentage(): ?float
    {
        $centimes = $this->centimes();
        if (null === $centimes || 0 === $this->estimeCentimes) {
            return null;
        }

        return round($centimes * 100 / $this->estimeCentimes, 1);
    }

    public function important(): bool
    {
        $pourcentage = $this->pourcentage();

        return null !== $pourcentage && abs($pourcentage) > self::SEUIL_POURCENTAGE;
    }
}
