<?php

namespace App\Entity;

use App\Enum\AgentIA;
use App\Repository\JournalIARepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Trace de chaque appel à un agent IA : entrée, sortie, coût. */
#[ORM\Entity(repositoryClass: JournalIARepository::class)]
#[ORM\Table(name: 'journal_ia')]
#[ORM\Index(columns: ['demande_id'])]
#[ORM\Index(columns: ['cree_le'])]
class JournalIA
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: AgentIA::class)]
    private ?AgentIA $agent = null;

    #[ORM\ManyToOne(targetEntity: Demande::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Demande $demande = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $modele = null;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $entree = [];

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $sortie = [];

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $tokensEntree = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $tokensSortie = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $creeLe;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgent(): ?AgentIA
    {
        return $this->agent;
    }

    public function setAgent(AgentIA $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getDemande(): ?Demande
    {
        return $this->demande;
    }

    public function setDemande(?Demande $demande): static
    {
        $this->demande = $demande;

        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(string $modele): static
    {
        $this->modele = $modele;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getEntree(): array
    {
        return $this->entree;
    }

    /**
     * @param array<mixed> $entree
     */
    public function setEntree(array $entree): static
    {
        $this->entree = $entree;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getSortie(): array
    {
        return $this->sortie;
    }

    /**
     * @param array<mixed> $sortie
     */
    public function setSortie(array $sortie): static
    {
        $this->sortie = $sortie;

        return $this;
    }

    public function getTokensEntree(): ?int
    {
        return $this->tokensEntree;
    }

    public function setTokensEntree(?int $tokensEntree): static
    {
        $this->tokensEntree = $tokensEntree;

        return $this;
    }

    public function getTokensSortie(): ?int
    {
        return $this->tokensSortie;
    }

    public function setTokensSortie(?int $tokensSortie): static
    {
        $this->tokensSortie = $tokensSortie;

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
}
