<?php

namespace App\Entity;

use App\Enum\TypePrestation;
use App\Repository\PrestationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Logement ou activité proposé par un prestataire dans une destination. */
#[ORM\Entity(repositoryClass: PrestationRepository::class)]
#[ORM\Table(name: 'prestation')]
#[ORM\Index(columns: ['destination_id', 'type'])]
#[ORM\Index(columns: ['capacite'])]
class Prestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: TypePrestation::class)]
    private ?TypePrestation $type = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: 'prestations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Destination $destination = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'prestations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Prestataire $prestataire = null;

    /** Ex. « 3 étoiles », « villa », « excursion ». */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Nombre maximum de personnes. */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive]
    private int $capacite = 1;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $equipements = [];

    /** Adapté aux personnes à mobilité réduite. */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $accessible = false;

    /** Durée d'une activité. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    private ?int $dureeMinutes = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $ageMinimum = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $envies = [];

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $photos = [];

    /**
     * @var Collection<int, TarifParPeriode>
     */
    #[ORM\OneToMany(targetEntity: TarifParPeriode::class, mappedBy: 'prestation', orphanRemoval: true)]
    #[ORM\OrderBy(['debut' => 'ASC'])]
    private Collection $tarifs;

    public function __construct()
    {
        $this->tarifs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?TypePrestation
    {
        return $this->type;
    }

    public function setType(TypePrestation $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
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

    public function getPrestataire(): ?Prestataire
    {
        return $this->prestataire;
    }

    public function setPrestataire(Prestataire $prestataire): static
    {
        $this->prestataire = $prestataire;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCapacite(): int
    {
        return $this->capacite;
    }

    public function setCapacite(int $capacite): static
    {
        $this->capacite = $capacite;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEquipements(): array
    {
        return $this->equipements;
    }

    /**
     * @param list<string> $equipements
     */
    public function setEquipements(array $equipements): static
    {
        $this->equipements = $equipements;

        return $this;
    }

    public function isAccessible(): bool
    {
        return $this->accessible;
    }

    public function setAccessible(bool $accessible): static
    {
        $this->accessible = $accessible;

        return $this;
    }

    public function getDureeMinutes(): ?int
    {
        return $this->dureeMinutes;
    }

    public function setDureeMinutes(?int $dureeMinutes): static
    {
        $this->dureeMinutes = $dureeMinutes;

        return $this;
    }

    public function getAgeMinimum(): ?int
    {
        return $this->ageMinimum;
    }

    public function setAgeMinimum(?int $ageMinimum): static
    {
        $this->ageMinimum = $ageMinimum;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEnvies(): array
    {
        return $this->envies;
    }

    /**
     * @param list<string> $envies
     */
    public function setEnvies(array $envies): static
    {
        $this->envies = $envies;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPhotos(): array
    {
        return $this->photos;
    }

    /**
     * @param list<string> $photos
     */
    public function setPhotos(array $photos): static
    {
        $this->photos = $photos;

        return $this;
    }

    /**
     * @return Collection<int, TarifParPeriode>
     */
    public function getTarifs(): Collection
    {
        return $this->tarifs;
    }

    public function addTarif(TarifParPeriode $tarif): static
    {
        if (!$this->tarifs->contains($tarif)) {
            $this->tarifs->add($tarif);
            $tarif->setPrestation($this);
        }

        return $this;
    }

    public function removeTarif(TarifParPeriode $tarif): static
    {
        $this->tarifs->removeElement($tarif);

        return $this;
    }
}
