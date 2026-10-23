<?php

namespace App\Entity;

use App\Enum\Role;
use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/** Conseiller ou administrateur du backoffice, ou client qui écrit au chatbot. */
#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[UniqueEntity('email', message: 'Un compte existe déjà avec cet e-mail.')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Saisissez votre e-mail.')]
    #[Assert\Email(message: 'Saisissez une adresse e-mail valide.')]
    private string $email = '';

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Saisissez votre nom.')]
    #[Assert\Length(max: 100)]
    private string $nom = '';

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    private ?string $telephone = null;

    /**
     * Étiquettes du client : « plage », « famille », « gastronomie »….
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $preferences = [];

    /** Un compte créé par l'inscription du site est client ; l'administrateur change le rôle dans le backoffice. */
    #[ORM\Column(enumType: Role::class)]
    private Role $role = Role::Client;

    /** Hash du mot de passe. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $motDePasse = null;

    /** Affichage dans les listes et filtres du backoffice. */
    public function __toString(): string
    {
        return trim(($this->prenom ?? '').' '.$this->nom);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

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

    /**
     * @return list<string>
     */
    public function getPreferences(): array
    {
        return $this->preferences;
    }

    /**
     * @param list<string> $preferences
     */
    public function setPreferences(array $preferences): static
    {
        $this->preferences = $preferences;

        return $this;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function setMotDePasse(?string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        // L'administrateur a aussi tous les droits du conseiller (role_hierarchy, ticket #173).
        return [$this->role->value, 'ROLE_USER'];
    }

    public function getPassword(): ?string
    {
        return $this->motDePasse;
    }

    public function eraseCredentials(): void
    {
    }
}
