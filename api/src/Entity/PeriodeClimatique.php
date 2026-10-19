<?php

namespace App\Entity;

use App\Repository\PeriodeClimatiqueRepository;
use App\Validator\NoPeriodOverlap;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

/** Climat moyen d'une destination sur une période. */
#[ORM\Entity(repositoryClass: PeriodeClimatiqueRepository::class)]
#[ORM\Table(name: 'periode_climatique')]
#[ORM\Index(columns: ['destination_id', 'debut', 'fin'])]
#[NoPeriodOverlap]
class PeriodeClimatique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: 'periodesClimatiques')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Destination $destination = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Indiquez la date de début.')]
    #[Groups(['destination:public'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $debut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Indiquez la date de fin.')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'debut', message: 'La fin doit être le même jour que le début ou après.')]
    #[Groups(['destination:public'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $fin = null;

    /** En °C. */
    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotNull(message: 'Indiquez la température moyenne.')]
    #[Assert\Range(min: -50, max: 60)]
    #[Groups(['destination:public'])]
    private ?float $temperatureMoyenne = null;

    /** Précipitations sur la période, en mm. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['destination:public'])]
    private ?int $pluieMm = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $saisiPar = null;

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

    public function getDestination(): ?Destination
    {
        return $this->destination;
    }

    public function setDestination(Destination $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getDebut(): ?\DateTimeImmutable
    {
        return $this->debut;
    }

    public function setDebut(\DateTimeImmutable $debut): static
    {
        $this->debut = $debut;

        return $this;
    }

    public function getFin(): ?\DateTimeImmutable
    {
        return $this->fin;
    }

    public function setFin(\DateTimeImmutable $fin): static
    {
        $this->fin = $fin;

        return $this;
    }

    public function getTemperatureMoyenne(): ?float
    {
        return $this->temperatureMoyenne;
    }

    public function setTemperatureMoyenne(float $temperatureMoyenne): static
    {
        $this->temperatureMoyenne = $temperatureMoyenne;

        return $this;
    }

    public function getPluieMm(): ?int
    {
        return $this->pluieMm;
    }

    public function setPluieMm(?int $pluieMm): static
    {
        $this->pluieMm = $pluieMm;

        return $this;
    }

    public function getSaisiPar(): ?Utilisateur
    {
        return $this->saisiPar;
    }

    public function setSaisiPar(?Utilisateur $saisiPar): static
    {
        $this->saisiPar = $saisiPar;

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
