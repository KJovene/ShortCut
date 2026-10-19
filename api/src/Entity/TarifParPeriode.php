<?php

namespace App\Entity;

use App\Enum\UniteTarif;
use App\Repository\TarifParPeriodeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\NoPeriodOverlap;

/** Prix d'un logement ou d'une activité sur une période. */
#[ORM\Entity(repositoryClass: TarifParPeriodeRepository::class)]
#[ORM\Table(name: 'tarif_par_periode')]
#[ORM\Index(columns: ['prestation_id', 'debut', 'fin'])]
#[NoPeriodOverlap]
class TarifParPeriode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestation::class, inversedBy: 'tarifs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Prestation $prestation = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $debut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'debut')]
    private ?\DateTimeImmutable $fin = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private ?int $prixCentimes = null;

    #[ORM\Column(enumType: UniteTarif::class)]
    private UniteTarif $unite = UniteTarif::ParNuit;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    private ?int $sejourMinimumNuits = null;

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

    public function getPrestation(): ?Prestation
    {
        return $this->prestation;
    }

    public function setPrestation(Prestation $prestation): static
    {
        $this->prestation = $prestation;

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

    public function getPrixCentimes(): ?int
    {
        return $this->prixCentimes;
    }

    public function setPrixCentimes(int $prixCentimes): static
    {
        $this->prixCentimes = $prixCentimes;

        return $this;
    }

    public function getUnite(): UniteTarif
    {
        return $this->unite;
    }

    public function setUnite(UniteTarif $unite): static
    {
        $this->unite = $unite;

        return $this;
    }

    public function getSejourMinimumNuits(): ?int
    {
        return $this->sejourMinimumNuits;
    }

    public function setSejourMinimumNuits(?int $sejourMinimumNuits): static
    {
        $this->sejourMinimumNuits = $sejourMinimumNuits;

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
