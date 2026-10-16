<?php

namespace App\Entity;

use App\Enum\TypeDeVoyage;
use App\Repository\RegleDePrixRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

    /**
     * Frais de dossier par type de voyage (décision #165), indexés par valeur de TypeDeVoyage.
     *
     * @var array<string, int>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $fraisDossierParTypeCentimes = [
        'sejour' => 10000,
        'noces' => 15000,
        'circuit' => 15000,
        'groupe' => 20000,
        'entreprise' => 20000,
    ];

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

    /**
     * @return array<string, int>
     */
    public function getFraisDossierParTypeCentimes(): array
    {
        return $this->fraisDossierParTypeCentimes;
    }

    /**
     * @param array<string, int> $fraisDossierParTypeCentimes
     */
    public function setFraisDossierParTypeCentimes(array $fraisDossierParTypeCentimes): static
    {
        $this->fraisDossierParTypeCentimes = $fraisDossierParTypeCentimes;

        return $this;
    }

    /** Frais de dossier ajoutés une fois par dossier, selon le type de voyage (docs/chiffrage.md). */
    public function getFraisDeDossierCentimes(TypeDeVoyage $type): int
    {
        return $this->fraisDossierParTypeCentimes[$type->value]
            ?? throw new \LogicException(\sprintf('Aucun frais de dossier défini pour « %s ».', $type->label()));
    }

    #[Assert\Callback]
    public function validerBaremeFraisDossier(ExecutionContextInterface $context): void
    {
        foreach (TypeDeVoyage::cases() as $type) {
            $frais = $this->fraisDossierParTypeCentimes[$type->value] ?? null;
            if (null === $frais || $frais < $this->fraisDossierMinCentimes || $frais > $this->fraisDossierMaxCentimes) {
                $context->buildViolation('Les frais de dossier « {{ type }} » doivent être compris entre le minimum et le maximum.')
                    ->setParameter('{{ type }}', $type->label())
                    ->atPath('fraisDossierParTypeCentimes')
                    ->addViolation();
            }
        }
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
