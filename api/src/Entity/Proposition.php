<?php

namespace App\Entity;

use App\Enum\NiveauBudget;
use App\Enum\StatutProposition;
use App\Enum\TypeProposition;
use App\Repository\PropositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** Une version de proposition : la première vient du chatbot, les suivantes des modifications ou du conseiller. */
#[ORM\Entity(repositoryClass: PropositionRepository::class)]
#[ORM\Table(name: 'proposition')]
#[ORM\UniqueConstraint(columns: ['demande_id', 'version'])]
#[UniqueEntity('jetonPublic')]
class Proposition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Demande::class, inversedBy: 'propositions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Demande $demande = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive]
    private int $version = 1;

    #[ORM\Column(enumType: TypeProposition::class)]
    private TypeProposition $type = TypeProposition::Premiere;

    #[ORM\Column(enumType: StatutProposition::class)]
    private StatutProposition $statut = StatutProposition::Brouillon;

    /** Lien unique de la page « Ma proposition ». */
    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $jetonPublic;

    /**
     * @var list<array{jour: int, titre: string, description: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $programme = [];

    /** Niveau détaillé dans les lignes. */
    #[ORM\Column(enumType: NiveauBudget::class)]
    private NiveauBudget $niveauBudget = NiveauBudget::Equilibre;

    /**
     * Totaux des trois niveaux, indexés par valeur de NiveauBudget.
     *
     * @var array<string, array{totalCentimes: int, parPersonneCentimes: int}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $budgetsParNiveau = [];

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $totalCentimes = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $totalParPersonneCentimes = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $fraisDeDossierCentimes = 0;

    /**
     * Hypothèses de budget affichées au client.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $hypotheses = [];

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $confirmeePar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmeeLe = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $creeLe;

    /**
     * @var Collection<int, LigneDePrestation>
     */
    #[ORM\OneToMany(targetEntity: LigneDePrestation::class, mappedBy: 'proposition', orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->jetonPublic = bin2hex(random_bytes(32));
        $this->creeLe = new \DateTimeImmutable();
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemande(): ?Demande
    {
        return $this->demande;
    }

    public function setDemande(Demande $demande): static
    {
        $this->demande = $demande;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getType(): TypeProposition
    {
        return $this->type;
    }

    public function setType(TypeProposition $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatut(): StatutProposition
    {
        return $this->statut;
    }

    public function setStatut(StatutProposition $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getJetonPublic(): string
    {
        return $this->jetonPublic;
    }

    public function setJetonPublic(string $jetonPublic): static
    {
        $this->jetonPublic = $jetonPublic;

        return $this;
    }

    /**
     * @return list<array{jour: int, titre: string, description: string}>
     */
    public function getProgramme(): array
    {
        return $this->programme;
    }

    /**
     * @param list<array{jour: int, titre: string, description: string}> $programme
     */
    public function setProgramme(array $programme): static
    {
        $this->programme = $programme;

        return $this;
    }

    public function getNiveauBudget(): NiveauBudget
    {
        return $this->niveauBudget;
    }

    public function setNiveauBudget(NiveauBudget $niveauBudget): static
    {
        $this->niveauBudget = $niveauBudget;

        return $this;
    }

    /**
     * @return array<string, array{totalCentimes: int, parPersonneCentimes: int}>
     */
    public function getBudgetsParNiveau(): array
    {
        return $this->budgetsParNiveau;
    }

    /**
     * @param array<string, array{totalCentimes: int, parPersonneCentimes: int}> $budgetsParNiveau
     */
    public function setBudgetsParNiveau(array $budgetsParNiveau): static
    {
        $this->budgetsParNiveau = $budgetsParNiveau;

        return $this;
    }

    public function getTotalCentimes(): int
    {
        return $this->totalCentimes;
    }

    public function setTotalCentimes(int $totalCentimes): static
    {
        $this->totalCentimes = $totalCentimes;

        return $this;
    }

    public function getTotalParPersonneCentimes(): int
    {
        return $this->totalParPersonneCentimes;
    }

    public function setTotalParPersonneCentimes(int $totalParPersonneCentimes): static
    {
        $this->totalParPersonneCentimes = $totalParPersonneCentimes;

        return $this;
    }

    public function getFraisDeDossierCentimes(): int
    {
        return $this->fraisDeDossierCentimes;
    }

    public function setFraisDeDossierCentimes(int $fraisDeDossierCentimes): static
    {
        $this->fraisDeDossierCentimes = $fraisDeDossierCentimes;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getHypotheses(): array
    {
        return $this->hypotheses;
    }

    /**
     * @param list<string> $hypotheses
     */
    public function setHypotheses(array $hypotheses): static
    {
        $this->hypotheses = $hypotheses;

        return $this;
    }

    public function getConfirmeePar(): ?Utilisateur
    {
        return $this->confirmeePar;
    }

    public function setConfirmeePar(?Utilisateur $confirmeePar): static
    {
        $this->confirmeePar = $confirmeePar;

        return $this;
    }

    public function getConfirmeeLe(): ?\DateTimeImmutable
    {
        return $this->confirmeeLe;
    }

    public function setConfirmeeLe(?\DateTimeImmutable $confirmeeLe): static
    {
        $this->confirmeeLe = $confirmeeLe;

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

    /**
     * @return Collection<int, LigneDePrestation>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneDePrestation $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setProposition($this);
        }

        return $this;
    }

    public function removeLigne(LigneDePrestation $ligne): static
    {
        $this->lignes->removeElement($ligne);

        return $this;
    }
}
