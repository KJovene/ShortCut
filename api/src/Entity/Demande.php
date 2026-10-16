<?php

namespace App\Entity;

use App\Enum\StatutDemande;
use App\Enum\TypeDeVoyage;
use App\Repository\DemandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Fiche structurée remplie par l'agent Conversation au fil du dialogue. */
#[ORM\Entity(repositoryClass: DemandeRepository::class)]
#[ORM\Table(name: 'demande')]
#[ORM\Index(columns: ['statut'])]
#[ORM\Index(columns: ['conseiller_id', 'statut'])]
class Demande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Inconnu tant que le client ne s'est pas présenté. */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $client = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $conseiller = null;

    #[ORM\OneToOne(targetEntity: Conversation::class, mappedBy: 'demande')]
    private ?Conversation $conversation = null;

    #[ORM\Column(nullable: true, enumType: TypeDeVoyage::class)]
    private ?TypeDeVoyage $typeDeVoyage = null;

    #[ORM\Column(enumType: StatutDemande::class)]
    private StatutDemande $statut = StatutDemande::Nouvelle;

    /** Lieu précis ou zone : « Lisbonne », « la Méditerranée ». */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $lieuOuZone = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $villeDepart = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'dateDebut')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $datesFlexibles = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    private ?int $nombreDeJours = null;

    /** Température moyenne souhaitée, en °C. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $temperatureMin = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $temperatureMax = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $adultes = 1;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $enfants = 0;

    /**
     * @var list<int>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $agesEnfants = [];

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    private ?int $budgetCentimes = null;

    /** Faux : le budget est un total. */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $budgetParPersonne = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $transportInclus = true;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $envies = [];

    /**
     * Mobilité réduite, régime alimentaire, allergies….
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $contraintes = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $creeLe;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $modifieLe;

    /**
     * @var Collection<int, Proposition>
     */
    #[ORM\OneToMany(targetEntity: Proposition::class, mappedBy: 'demande')]
    #[ORM\OrderBy(['version' => 'ASC'])]
    private Collection $propositions;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->modifieLe = new \DateTimeImmutable();
        $this->propositions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Utilisateur
    {
        return $this->client;
    }

    public function setClient(?Utilisateur $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getConseiller(): ?Utilisateur
    {
        return $this->conseiller;
    }

    public function setConseiller(?Utilisateur $conseiller): static
    {
        $this->conseiller = $conseiller;

        return $this;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(Conversation $conversation): static
    {
        if ($conversation->getDemande() !== $this) {
            $conversation->setDemande($this);
        }

        $this->conversation = $conversation;

        return $this;
    }

    public function getTypeDeVoyage(): ?TypeDeVoyage
    {
        return $this->typeDeVoyage;
    }

    public function setTypeDeVoyage(?TypeDeVoyage $typeDeVoyage): static
    {
        $this->typeDeVoyage = $typeDeVoyage;

        return $this;
    }

    public function getStatut(): StatutDemande
    {
        return $this->statut;
    }

    public function setStatut(StatutDemande $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getLieuOuZone(): ?string
    {
        return $this->lieuOuZone;
    }

    public function setLieuOuZone(?string $lieuOuZone): static
    {
        $this->lieuOuZone = $lieuOuZone;

        return $this;
    }

    public function getVilleDepart(): ?string
    {
        return $this->villeDepart;
    }

    public function setVilleDepart(?string $villeDepart): static
    {
        $this->villeDepart = $villeDepart;

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

    public function isDatesFlexibles(): bool
    {
        return $this->datesFlexibles;
    }

    public function setDatesFlexibles(bool $datesFlexibles): static
    {
        $this->datesFlexibles = $datesFlexibles;

        return $this;
    }

    public function getNombreDeJours(): ?int
    {
        return $this->nombreDeJours;
    }

    public function setNombreDeJours(?int $nombreDeJours): static
    {
        $this->nombreDeJours = $nombreDeJours;

        return $this;
    }

    public function getTemperatureMin(): ?int
    {
        return $this->temperatureMin;
    }

    public function setTemperatureMin(?int $temperatureMin): static
    {
        $this->temperatureMin = $temperatureMin;

        return $this;
    }

    public function getTemperatureMax(): ?int
    {
        return $this->temperatureMax;
    }

    public function setTemperatureMax(?int $temperatureMax): static
    {
        $this->temperatureMax = $temperatureMax;

        return $this;
    }

    public function getAdultes(): int
    {
        return $this->adultes;
    }

    public function setAdultes(int $adultes): static
    {
        $this->adultes = $adultes;

        return $this;
    }

    public function getEnfants(): int
    {
        return $this->enfants;
    }

    public function setEnfants(int $enfants): static
    {
        $this->enfants = $enfants;

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getAgesEnfants(): array
    {
        return $this->agesEnfants;
    }

    /**
     * @param list<int> $agesEnfants
     */
    public function setAgesEnfants(array $agesEnfants): static
    {
        $this->agesEnfants = $agesEnfants;

        return $this;
    }

    public function getBudgetCentimes(): ?int
    {
        return $this->budgetCentimes;
    }

    public function setBudgetCentimes(?int $budgetCentimes): static
    {
        $this->budgetCentimes = $budgetCentimes;

        return $this;
    }

    public function isBudgetParPersonne(): bool
    {
        return $this->budgetParPersonne;
    }

    public function setBudgetParPersonne(bool $budgetParPersonne): static
    {
        $this->budgetParPersonne = $budgetParPersonne;

        return $this;
    }

    public function isTransportInclus(): bool
    {
        return $this->transportInclus;
    }

    public function setTransportInclus(bool $transportInclus): static
    {
        $this->transportInclus = $transportInclus;

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
    public function getContraintes(): array
    {
        return $this->contraintes;
    }

    /**
     * @param list<string> $contraintes
     */
    public function setContraintes(array $contraintes): static
    {
        $this->contraintes = $contraintes;

        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function setCreeLe(\DateTimeImmutable $creeLe): static
    {
        $this->creeLe = $creeLe;

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

    /**
     * @return Collection<int, Proposition>
     */
    public function getPropositions(): Collection
    {
        return $this->propositions;
    }

    public function addProposition(Proposition $proposition): static
    {
        if (!$this->propositions->contains($proposition)) {
            $this->propositions->add($proposition);
            $proposition->setDemande($this);
        }

        return $this;
    }

    public function removeProposition(Proposition $proposition): static
    {
        $this->propositions->removeElement($proposition);

        return $this;
    }
}
