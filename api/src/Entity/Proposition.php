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
    /** Mention affichée avec toute première proposition (décision D11). */
    public const MENTION = 'Première proposition, prix estimés, à confirmer par votre conseiller.';

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

    #[ORM\ManyToOne(targetEntity: Destination::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Destination $destination = null;

    /** Pourquoi cette proposition répond au client, en quelques phrases. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pourquoi = null;

    /**
     * Chaque exigence du client et ce qui y répond.
     *
     * @var list<array{exigence: string, reponse: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $argumentaire = [];

    /**
     * Logements et activités retenus par au moins un niveau, avec leurs points forts.
     *
     * @var list<array{id: int, type: string, nom: string, pointsForts: list<string>, niveaux: list<string>}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $prestations = [];

    /**
     * La combinaison choisie, telle que retenir_combinaison l'a acceptée (destination, étapes et logements candidats,
     * activités demandées et suggérées) : une modification du client la recalcule sans tout rechercher (#211).
     *
     * @var array{destination?: string, etapes?: list<array{dateDebut: string, nombreDeNuits: int, logementIds: list<int>}>, activitesDemandees?: list<int>, activitesSuggerees?: list<int>, pourquoi?: string}
     */
    #[ORM\Column(type: Types::JSON)]
    private array $choix = [];

    /**
     * La fiche demande au moment du chiffrage (EnregistrerExigences::fiche) : ce que le client a changé depuis.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $exigences = [];

    /**
     * Programme jour par jour du niveau détaillé, du jour d'arrivée au jour du départ.
     *
     * @var list<array{jour: int, date: string, titre: string, description: string, activiteIds: list<int>}>
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

    /** Envoi au client (prix estimés) ; null pour un brouillon jamais envoyé. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $envoyeeLe = null;

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

    public function getDestination(): ?Destination
    {
        return $this->destination;
    }

    public function setDestination(?Destination $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * @return array{destination?: string, etapes?: list<array{dateDebut: string, nombreDeNuits: int, logementIds: list<int>}>, activitesDemandees?: list<int>, activitesSuggerees?: list<int>, pourquoi?: string}
     */
    public function getChoix(): array
    {
        return $this->choix;
    }

    /**
     * @param array{destination?: string, etapes?: list<array{dateDebut: string, nombreDeNuits: int, logementIds: list<int>}>, activitesDemandees?: list<int>, activitesSuggerees?: list<int>, pourquoi?: string} $choix
     */
    public function setChoix(array $choix): static
    {
        $this->choix = $choix;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExigences(): array
    {
        return $this->exigences;
    }

    /**
     * @param array<string, mixed> $exigences
     */
    public function setExigences(array $exigences): static
    {
        $this->exigences = $exigences;

        return $this;
    }

    public function getPourquoi(): ?string
    {
        return $this->pourquoi;
    }

    public function setPourquoi(?string $pourquoi): static
    {
        $this->pourquoi = $pourquoi;

        return $this;
    }

    /**
     * @return list<array{exigence: string, reponse: string}>
     */
    public function getArgumentaire(): array
    {
        return $this->argumentaire;
    }

    /**
     * @param list<array{exigence: string, reponse: string}> $argumentaire
     */
    public function setArgumentaire(array $argumentaire): static
    {
        $this->argumentaire = $argumentaire;

        return $this;
    }

    /**
     * @return list<array{id: int, type: string, nom: string, pointsForts: list<string>, niveaux: list<string>}>
     */
    public function getPrestations(): array
    {
        return $this->prestations;
    }

    /**
     * @param list<array{id: int, type: string, nom: string, pointsForts: list<string>, niveaux: list<string>}> $prestations
     */
    public function setPrestations(array $prestations): static
    {
        $this->prestations = $prestations;

        return $this;
    }

    /**
     * @return list<array{jour: int, date: string, titre: string, description: string, activiteIds: list<int>}>
     */
    public function getProgramme(): array
    {
        return $this->programme;
    }

    /**
     * @param list<array{jour: int, date: string, titre: string, description: string, activiteIds: list<int>}> $programme
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

    public function getEnvoyeeLe(): ?\DateTimeImmutable
    {
        return $this->envoyeeLe;
    }

    public function setEnvoyeeLe(?\DateTimeImmutable $envoyeeLe): static
    {
        $this->envoyeeLe = $envoyeeLe;

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
