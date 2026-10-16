<?php

namespace App\Entity;

use App\Repository\RegleDePrixRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Règles de prix de l'agence, appliquées à chaque chiffrage. */
#[ORM\Entity(repositoryClass: RegleDePrixRepository::class)]
#[ORM\Table(name: 'regle_de_prix')]
class RegleDePrix
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 0, max: 100)]
    private int $margePourcentage = 20;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $fraisDossierMinCentimes = 10000;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'fraisDossierMinCentimes')]
    private int $fraisDossierMaxCentimes = 20000;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $modifieLe;

    public function __construct()
    {
        $this->modifieLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMargePourcentage(): int
    {
        return $this->margePourcentage;
    }

    public function setMargePourcentage(int $margePourcentage): static
    {
        $this->margePourcentage = $margePourcentage;

        return $this;
    }

    public function getFraisDossierMinCentimes(): int
    {
        return $this->fraisDossierMinCentimes;
    }

    public function setFraisDossierMinCentimes(int $fraisDossierMinCentimes): static
    {
        $this->fraisDossierMinCentimes = $fraisDossierMinCentimes;

        return $this;
    }

    public function getFraisDossierMaxCentimes(): int
    {
        return $this->fraisDossierMaxCentimes;
    }

    public function setFraisDossierMaxCentimes(int $fraisDossierMaxCentimes): static
    {
        $this->fraisDossierMaxCentimes = $fraisDossierMaxCentimes;

        return $this;
    }

    public function getModifieLe(): \DateTimeImmutable
    {
        return $this->modifieLe;
    }

    public function setModifieLe(\DateTimeImmutable $modifieLe): static
    {
        $this->modifieLe = $modifieLe;

        return $this;
    }
}
