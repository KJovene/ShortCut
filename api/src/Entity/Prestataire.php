<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Enum\TypePrestataire;
use App\Repository\PrestataireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Hébergeur, transporteur, guide ou prestataire d'activités.
 * Données internes de l'agence (contacts, fourchettes d'achat) : jamais exposées au site.
 */
#[ApiResource(
    operations: [
        new GetCollection(parameters: [
            'nom' => new QueryParameter(filter: new PartialSearchFilter(), property: 'nom', castToArray: false),
            'type' => new QueryParameter(
                filter: new ExactFilter(),
                property: 'type',
                schema: ['type' => 'string', 'enum' => ['hebergeur', 'transporteur', 'guide', 'activite']],
                nativeType: new BackedEnumType(TypePrestataire::class, new BuiltinType(TypeIdentifier::STRING)),
                castToNativeType: true,
                castToArray: false,
            ),
        ]),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['prestataire:lecture']],
    denormalizationContext: ['groups' => ['prestataire:ecriture']],
    order: ['nom' => 'ASC'],
    security: "is_granted('ROLE_CONSEILLER')",
)]
#[ORM\Entity(repositoryClass: PrestataireRepository::class)]
#[ORM\Table(name: 'prestataire')]
#[ORM\Index(columns: ['type'])]
class Prestataire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['prestataire:lecture'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\NotBlank(message: 'Indiquez le nom du prestataire.')]
    #[Assert\Length(max: 150)]
    #[Groups(['prestataire:lecture', 'prestataire:ecriture'])]
    private ?string $nom = null;

    #[ORM\Column(enumType: TypePrestataire::class)]
    #[Assert\NotNull(message: 'Choisissez le type : hebergeur, transporteur, guide ou activite.')]
    #[Groups(['prestataire:lecture', 'prestataire:ecriture'])]
    private ?TypePrestataire $type = null;

    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    #[Groups(['prestataire:lecture', 'prestataire:ecriture'])]
    private ?string $contactNom = null;

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Groups(['prestataire:lecture', 'prestataire:ecriture'])]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    #[Assert\Regex('/^\+?[0-9 .()-]{6,}$/', message: 'Ce numéro de téléphone n\'est pas valide.')]
    #[Groups(['prestataire:lecture', 'prestataire:ecriture'])]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    #[Groups(['prestataire:lecture', 'prestataire:ecriture'])]
    private ?string $conditionsAnnulation = null;

    /**
     * Fourchettes de prix des transports, utilisées quand il n'y a pas de tarif précis.
     *
     * @var list<array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([
        new Assert\Collection([
            'depart' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            'arrivee' => [new Assert\NotBlank(), new Assert\Length(max: 150)],
            'minCentimes' => [new Assert\NotNull(), new Assert\Type('int'), new Assert\PositiveOrZero()],
            'maxCentimes' => [new Assert\NotNull(), new Assert\Type('int'), new Assert\PositiveOrZero()],
        ]),
    ])]
    #[Groups(['prestataire:lecture', 'prestataire:ecriture'])]
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

    #[Assert\Callback]
    public function verifierFourchettes(ExecutionContextInterface $context): void
    {
        /** @var array<array<string, mixed>> $fourchettes contenu envoyé à l'API, pas encore validé */
        $fourchettes = $this->fourchettesTransport;
        foreach ($fourchettes as $index => $fourchette) {
            if (\is_int($fourchette['minCentimes'] ?? null) && \is_int($fourchette['maxCentimes'] ?? null)
                && $fourchette['maxCentimes'] < $fourchette['minCentimes']) {
                $context->buildViolation('Le maximum de la fourchette doit être supérieur ou égal au minimum.')
                    ->atPath(\sprintf('fourchettesTransport[%d][maxCentimes]', $index))
                    ->addViolation();
            }
        }
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
