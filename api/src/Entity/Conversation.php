<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/** Échanges entre le client et le chatbot pour une demande. */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
#[UniqueEntity('jeton')]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Demande::class, inversedBy: 'conversation')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Demande $demande = null;

    /** Jeton anonyme qui permet au navigateur du client de reprendre sa conversation. */
    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $jeton;

    /**
     * @var list<array{role: string, contenu: string, le: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $messages = [];

    /**
     * Échanges au format de l'API Claude (réflexion, appels d'outils et résultats compris), renvoyés tels quels
     * à chaque appel. L'historique ne fait que s'allonger : modifier un ancien message invaliderait la réflexion.
     * Stocké en texte et non en JSON Doctrine, qui décode en tableaux et changerait « {} » en « [] ».
     */
    #[ORM\Column(type: Types::TEXT, options: ['default' => '[]'])]
    private string $historique = '[]';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $debutLe;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finLe = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $repriseParConseiller = false;

    public function __construct()
    {
        $this->jeton = bin2hex(random_bytes(32));
        $this->debutLe = new \DateTimeImmutable();
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

    public function getJeton(): string
    {
        return $this->jeton;
    }

    public function setJeton(string $jeton): static
    {
        $this->jeton = $jeton;

        return $this;
    }

    /**
     * @return list<array{role: string, contenu: string, le: string}>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param list<array{role: string, contenu: string, le: string}> $messages
     */
    public function setMessages(array $messages): static
    {
        $this->messages = $messages;

        return $this;
    }

    /** Ajoute un message à la conversation lisible (backoffice, reprise par un conseiller). */
    public function ajouterMessage(string $role, string $contenu): static
    {
        $this->messages[] = ['role' => $role, 'contenu' => $contenu, 'le' => (new \DateTimeImmutable())->format(\DATE_ATOM)];

        return $this;
    }

    /**
     * Messages décodés en objets (\stdClass), pour être réencodés à l'identique.
     *
     * @return list<mixed>
     */
    public function getHistorique(): array
    {
        $historique = json_decode($this->historique, false, flags: \JSON_THROW_ON_ERROR);

        return \is_array($historique) ? array_values($historique) : [];
    }

    /**
     * @param list<mixed> $historique
     */
    public function setHistorique(array $historique): static
    {
        $this->historique = json_encode($historique, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);

        return $this;
    }

    public function getDebutLe(): \DateTimeImmutable
    {
        return $this->debutLe;
    }

    public function setDebutLe(\DateTimeImmutable $debutLe): static
    {
        $this->debutLe = $debutLe;

        return $this;
    }

    public function getFinLe(): ?\DateTimeImmutable
    {
        return $this->finLe;
    }

    public function setFinLe(?\DateTimeImmutable $finLe): static
    {
        $this->finLe = $finLe;

        return $this;
    }

    public function isRepriseParConseiller(): bool
    {
        return $this->repriseParConseiller;
    }

    public function setRepriseParConseiller(bool $repriseParConseiller): static
    {
        $this->repriseParConseiller = $repriseParConseiller;

        return $this;
    }
}
