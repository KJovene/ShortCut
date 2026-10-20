<?php

namespace App\Entity;

use App\Enum\TypeLigne;
use App\Repository\LigneDePrestationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Poste chiffré d'une proposition : logement, transport, activité ou guide. */
#[ORM\Entity(repositoryClass: LigneDePrestationRepository::class)]
#[ORM\Table(name: 'ligne_de_prestation')]
class LigneDePrestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Proposition::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Proposition $proposition = null;

    #[ORM\Column(enumType: TypeLigne::class)]
    private ?TypeLigne $type = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    private ?string $libelle = null;

    /** Logement ou activité source. */
    #[ORM\ManyToOne(targetEntity: Prestation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Prestation $prestation = null;

    /** Transporteur ou guide source. */
    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'dateDebut')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive]
    private int $quantite = 1;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $prixEstimeCentimes = 0;

    /** Détail du prix : « 7 nuits × 150 € », « 2 participants × 120 € ». */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $calcul = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $prixConfirmeCentimes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProposition(): ?Proposition
    {
        return $this->proposition;
    }

    public function setProposition(Proposition $proposition): static
    {
        $this->proposition = $proposition;

        return $this;
    }

    public function getType(): ?TypeLigne
    {
        return $this->type;
    }

    public function setType(TypeLigne $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getPrestation(): ?Prestation
    {
        return $this->prestation;
    }

    public function setPrestation(?Prestation $prestation): static
    {
        $this->prestation = $prestation;

        return $this;
    }

    public function getPrestataire(): ?Prestataire
    {
        return $this->prestataire;
    }

    public function setPrestataire(?Prestataire $prestataire): static
    {
        $this->prestataire = $prestataire;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getPrixEstimeCentimes(): int
    {
        return $this->prixEstimeCentimes;
    }

    public function setPrixEstimeCentimes(int $prixEstimeCentimes): static
    {
        $this->prixEstimeCentimes = $prixEstimeCentimes;

        return $this;
    }

    public function getCalcul(): ?string
    {
        return $this->calcul;
    }

    public function setCalcul(?string $calcul): static
    {
        $this->calcul = $calcul;

        return $this;
    }

    public function getPrixConfirmeCentimes(): ?int
    {
        return $this->prixConfirmeCentimes;
    }

    public function setPrixConfirmeCentimes(?int $prixConfirmeCentimes): static
    {
        $this->prixConfirmeCentimes = $prixConfirmeCentimes;

        return $this;
    }
}
