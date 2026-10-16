<?php

namespace App\Entity;

use App\Enum\TypePrestataire;
use App\Repository\PrestataireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Hébergeur, transporteur, guide ou prestataire d'activités. */
#[ORM\Entity(repositoryClass: PrestataireRepository::class)]
#[ORM\Table(name: 'prestataire')]
#[ORM\Index(columns: ['type'])]
class Prestataire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(enumType: TypePrestataire::class)]
    private ?TypePrestataire $type = null;

    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    private ?string $contactNom = null;

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditionsAnnulation = null;

    /**
     * Fourchettes de prix des transports, utilisées quand il n'y a pas de tarif précis.
     *
     * @var list<array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $fourchettesTransport = [];

    /**
     * @var Collection<int, Prestation>
     */
    #[ORM\OneToMany(targetEntity: Prestation::class, mappedBy: 'prestataire')]
    private Collection $prestations;

    public function __construct()
    {
        $this->prestations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): ?TypePrestataire
    {
        return $this->type;
    }

    public function setType(TypePrestataire $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getContactNom(): ?string
    {
        return $this->contactNom;
    }

    public function setContactNom(?string $contactNom): static
    {
        $this->contactNom = $contactNom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getConditionsAnnulation(): ?string
    {
        return $this->conditionsAnnulation;
    }

    public function setConditionsAnnulation(?string $conditionsAnnulation): static
    {
        $this->conditionsAnnulation = $conditionsAnnulation;

        return $this;
    }

    /**
     * @return list<array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}>
     */
    public function getFourchettesTransport(): array
    {
        return $this->fourchettesTransport;
    }

    /**
     * @param list<array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}> $fourchettesTransport
     */
    public function setFourchettesTransport(array $fourchettesTransport): static
    {
        $this->fourchettesTransport = $fourchettesTransport;

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
            $prestation->setPrestataire($this);
        }

        return $this;
    }

    public function removePrestation(Prestation $prestation): static
    {
        $this->prestations->removeElement($prestation);

        return $this;
    }
}
