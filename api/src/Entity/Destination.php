<?php

namespace App\Entity;

use App\Repository\DestinationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** Destination du catalogue, avec son climat par période. */
#[ORM\Entity(repositoryClass: DestinationRepository::class)]
#[ORM\Table(name: 'destination')]
#[ORM\Index(columns: ['pays'])]
#[ORM\Index(columns: ['publiee'])]
#[UniqueEntity('slug')]
class Destination
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    private ?string $pays = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    private ?string $ville = null;

    /** Adresse de la fiche sur le site. */
    #[ORM\Column(type: Types::STRING, length: 150, unique: true)]
    #[Assert\NotBlank]
    private ?string $slug = null;

    /**
     * Minutes de trajet, indexées par ville de départ.
     *
     * @var array<string, int>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $tempsDeTrajet = [];

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $pointsForts = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $publiee = false;

    /** Affichée sur la page d'accueil. */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $miseEnAvant = false;

    /**
     * @var Collection<int, PeriodeClimatique>
     */
    #[ORM\OneToMany(targetEntity: PeriodeClimatique::class, mappedBy: 'destination', orphanRemoval: true)]
    #[ORM\OrderBy(['debut' => 'ASC'])]
    private Collection $periodesClimatiques;

    /**
     * @var Collection<int, Prestation>
     */
    #[ORM\OneToMany(targetEntity: Prestation::class, mappedBy: 'destination')]
    private Collection $prestations;

    public function __construct()
    {
        $this->periodesClimatiques = new ArrayCollection();
        $this->prestations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(string $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return array<string, int>
     */
    public function getTempsDeTrajet(): array
    {
        return $this->tempsDeTrajet;
    }

    /**
     * @param array<string, int> $tempsDeTrajet
     */
    public function setTempsDeTrajet(array $tempsDeTrajet): static
    {
        $this->tempsDeTrajet = $tempsDeTrajet;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPointsForts(): array
    {
        return $this->pointsForts;
    }

    /**
     * @param list<string> $pointsForts
     */
    public function setPointsForts(array $pointsForts): static
    {
        $this->pointsForts = $pointsForts;

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

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function isPubliee(): bool
    {
        return $this->publiee;
    }

    public function setPubliee(bool $publiee): static
    {
        $this->publiee = $publiee;

        return $this;
    }

    public function isMiseEnAvant(): bool
    {
        return $this->miseEnAvant;
    }

    public function setMiseEnAvant(bool $miseEnAvant): static
    {
        $this->miseEnAvant = $miseEnAvant;

        return $this;
    }

    /**
     * @return Collection<int, PeriodeClimatique>
     */
    public function getPeriodesClimatiques(): Collection
    {
        return $this->periodesClimatiques;
    }

    public function addPeriodeClimatique(PeriodeClimatique $periodeClimatique): static
    {
        if (!$this->periodesClimatiques->contains($periodeClimatique)) {
            $this->periodesClimatiques->add($periodeClimatique);
            $periodeClimatique->setDestination($this);
        }

        return $this;
    }

    public function removePeriodeClimatique(PeriodeClimatique $periodeClimatique): static
    {
        $this->periodesClimatiques->removeElement($periodeClimatique);

        return $this;
    }

    /**
     * @return Collection<int, Prestation>
     */
    public function getPrestations(): Collection
    {
        return $this->prestations;
    }

    public function addPrestation(Prestation $prestation): static
    {
        if (!$this->prestations->contains($prestation)) {
            $this->prestations->add($prestation);
            $prestation->setDestination($this);
        }

        return $this;
    }

    public function removePrestation(Prestation $prestation): static
    {
        $this->prestations->removeElement($prestation);

        return $this;
    }
}
