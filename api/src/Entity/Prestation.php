<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ComparisonFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\IriFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\ApiPlatform\Filtre\EnvieFilter;
use App\ApiPlatform\Filtre\MoisFilter;
use App\Enum\TypePrestation;
use App\Repository\PrestationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Logement ou activité proposé par un prestataire dans une destination.
 * Exposée sous deux adresses, /api/logements et /api/activites (voir TypePrestationExtension).
 * Groupe `prestation:public` : ce que le site peut montrer ; `prestation:interne` : prestataire et tarifs d'achat.
 */
#[ApiResource(
    shortName: 'Logement',
    operations: [
        new GetCollection(uriTemplate: '/logements', itemUriTemplate: '/logements/{id}', parameters: [
            'destination' => new QueryParameter(filter: new IriFilter(), property: 'destination', castToArray: false),
            'destinationSlug' => new QueryParameter(filter: new ExactFilter(), property: 'destination.slug', description: 'Slug de la destination, ex. lisbonne.', castToArray: false),
            'prestataire' => new QueryParameter(filter: new IriFilter(), property: 'prestataire', castToArray: false),
            'categorie' => new QueryParameter(filter: new PartialSearchFilter(), property: 'categorie', castToArray: false),
            'capacite' => new QueryParameter(
                filter: new ComparisonFilter(new ExactFilter()),
                property: 'capacite',
                description: 'Capacité, ex. capacite[gte]=4 pour au moins 4 personnes.',
                nativeType: new BuiltinType(TypeIdentifier::INT),
                castToNativeType: true,
            ),
            'accessible' => new QueryParameter(filter: new ExactFilter(), property: 'accessible', description: 'Adapté aux personnes à mobilité réduite.', nativeType: new BuiltinType(TypeIdentifier::BOOL), castToNativeType: true, castToArray: false),
            'envie' => new QueryParameter(filter: new EnvieFilter(), description: 'Envie associée (plage, culture, détente…).', castToArray: false),
            'mois' => new QueryParameter(
                filter: new MoisFilter(TarifParPeriode::class, 'prestation'),
                description: 'Disponible ce mois-ci (AAAA-MM) : au moins un tarif saisi sur le mois.',
                schema: ['type' => 'string', 'pattern' => '^\\d{4}-(0[1-9]|1[0-2])$', 'example' => '2027-05'],
                constraints: [new Assert\Regex(MoisFilter::FORMAT, message: 'Le mois doit être au format AAAA-MM, par exemple 2027-05.')],
                castToArray: false,
            ),
            'chambres' => new QueryParameter(
                filter: new ComparisonFilter(new ExactFilter()),
                property: 'chambres',
                description: 'Nombre de chambres, ex. chambres[gte]=2.',
                nativeType: new BuiltinType(TypeIdentifier::INT),
                castToNativeType: true,
            ),
        ]),
        new Get(uriTemplate: '/logements/{id}', name: self::OPERATION_LOGEMENT),
        new Post(
            uriTemplate: '/logements',
            itemUriTemplate: '/logements/{id}',
            denormalizationContext: [
                'groups' => ['prestation:ecriture'],
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [self::class => ['type' => TypePrestation::Logement]],
            ],
        ),
        new Patch(uriTemplate: '/logements/{id}'),
        new Delete(uriTemplate: '/logements/{id}', security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['prestation:public', 'prestation:interne', 'prestation:tarifs']],
    denormalizationContext: ['groups' => ['prestation:ecriture']],
    order: ['nom' => 'ASC'],
    security: "is_granted('ROLE_CONSEILLER')",
    extraProperties: ['typePrestation' => TypePrestation::Logement],
)]
#[ApiResource(
    shortName: 'Activite',
    operations: [
        new GetCollection(uriTemplate: '/activites', itemUriTemplate: '/activites/{id}', parameters: [
            'destination' => new QueryParameter(filter: new IriFilter(), property: 'destination', castToArray: false),
            'destinationSlug' => new QueryParameter(filter: new ExactFilter(), property: 'destination.slug', description: 'Slug de la destination, ex. lisbonne.', castToArray: false),
            'prestataire' => new QueryParameter(filter: new IriFilter(), property: 'prestataire', castToArray: false),
            'categorie' => new QueryParameter(filter: new PartialSearchFilter(), property: 'categorie', castToArray: false),
            'capacite' => new QueryParameter(
                filter: new ComparisonFilter(new ExactFilter()),
                property: 'capacite',
                description: 'Capacité, ex. capacite[gte]=4 pour au moins 4 personnes.',
                nativeType: new BuiltinType(TypeIdentifier::INT),
                castToNativeType: true,
            ),
            'accessible' => new QueryParameter(filter: new ExactFilter(), property: 'accessible', description: 'Adapté aux personnes à mobilité réduite.', nativeType: new BuiltinType(TypeIdentifier::BOOL), castToNativeType: true, castToArray: false),
            'envie' => new QueryParameter(filter: new EnvieFilter(), description: 'Envie associée (plage, culture, détente…).', castToArray: false),
            'mois' => new QueryParameter(
                filter: new MoisFilter(TarifParPeriode::class, 'prestation'),
                description: 'Disponible ce mois-ci (AAAA-MM) : au moins un tarif saisi sur le mois.',
                schema: ['type' => 'string', 'pattern' => '^\\d{4}-(0[1-9]|1[0-2])$', 'example' => '2027-05'],
                constraints: [new Assert\Regex(MoisFilter::FORMAT, message: 'Le mois doit être au format AAAA-MM, par exemple 2027-05.')],
                castToArray: false,
            ),
            'ageMinimum' => new QueryParameter(
                filter: new ComparisonFilter(new ExactFilter()),
                property: 'ageMinimum',
                description: 'Âge minimum, ex. ageMinimum[lte]=8 pour une activité ouverte à un enfant de 8 ans.',
                nativeType: new BuiltinType(TypeIdentifier::INT),
                castToNativeType: true,
            ),
            'dureeMinutes' => new QueryParameter(
                filter: new ComparisonFilter(new ExactFilter()),
                property: 'dureeMinutes',
                description: 'Durée en minutes, ex. dureeMinutes[lte]=180.',
                nativeType: new BuiltinType(TypeIdentifier::INT),
                castToNativeType: true,
            ),
        ]),
        new Get(uriTemplate: '/activites/{id}', name: self::OPERATION_ACTIVITE),
        new Post(
            uriTemplate: '/activites',
            itemUriTemplate: '/activites/{id}',
            denormalizationContext: [
                'groups' => ['prestation:ecriture'],
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [self::class => ['type' => TypePrestation::Activite]],
            ],
        ),
        new Patch(uriTemplate: '/activites/{id}'),
        new Delete(uriTemplate: '/activites/{id}', security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['prestation:public', 'prestation:interne', 'prestation:tarifs']],
    denormalizationContext: ['groups' => ['prestation:ecriture']],
    order: ['nom' => 'ASC'],
    security: "is_granted('ROLE_CONSEILLER')",
    extraProperties: ['typePrestation' => TypePrestation::Activite],
)]
#[ORM\Entity(repositoryClass: PrestationRepository::class)]
#[ORM\Table(name: 'prestation')]
#[ORM\Index(columns: ['destination_id', 'type'])]
#[ORM\Index(columns: ['capacite'])]
class Prestation
{
    /** Noms des opérations qui donnent l'adresse d'une fiche (voir IriPrestationConverter). */
    public const OPERATION_LOGEMENT = 'logement_fiche';
    public const OPERATION_ACTIVITE = 'activite_fiche';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['prestation:public'])]
    private ?int $id = null;

    /** Fixé par l'adresse utilisée (/api/logements ou /api/activites), jamais par le contenu envoyé. */
    #[ORM\Column(enumType: TypePrestation::class)]
    #[Assert\NotNull]
    #[Groups(['prestation:public'])]
    private ?TypePrestation $type = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\NotBlank(message: 'Indiquez le nom.')]
    #[Assert\Length(max: 150)]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private ?string $nom = null;

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: 'prestations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Choisissez la destination.')]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private ?Destination $destination = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'prestations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Choisissez le prestataire.')]
    #[Groups(['prestation:interne', 'prestation:ecriture'])]
    private ?Prestataire $prestataire = null;

    /** Ex. « 3 étoiles », « villa », « excursion ». */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private ?string $description = null;

    /** Nombre maximum de personnes. */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive(message: 'La capacité doit être d\'au moins une personne.')]
    #[Assert\LessThanOrEqual(1000)]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private int $capacite = 1;

    /** Nombre de chambres d'un logement (un riad entier : 6 ; une chambre double : 1). */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'Le nombre de chambres doit être d\'au moins 1.')]
    #[Assert\LessThanOrEqual(500)]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private ?int $chambres = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 100)])]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private array $equipements = [];

    /** Adapté aux personnes à mobilité réduite. */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private bool $accessible = false;

    /** Durée d'une activité. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'La durée doit être d\'au moins une minute.')]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private ?int $dureeMinutes = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(min: 0, max: 99)]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private ?int $ageMinimum = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 50)])]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private array $envies = [];

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(max: 10)]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 255)])]
    #[Groups(['prestation:public', 'prestation:ecriture'])]
    private array $photos = [];

    /**
     * Tarifs en lecture seule ici : ils se créent et se modifient sur /api/tarifs.
     *
     * @var Collection<int, TarifParPeriode>
     */
    #[ORM\OneToMany(targetEntity: TarifParPeriode::class, mappedBy: 'prestation', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['debut' => 'ASC'])]
    #[Groups(['prestation:interne'])]
    private Collection $tarifs;

    public function __construct(?TypePrestation $type = null)
    {
        $this->type = $type;
        $this->tarifs = new ArrayCollection();
    }

    #[Assert\Callback]
    public function verifierSelonLeType(ExecutionContextInterface $context): void
    {
        if (TypePrestation::Activite === $this->type && null === $this->dureeMinutes) {
            $context->buildViolation('Indiquez la durée de l\'activité, en minutes.')->atPath('dureeMinutes')->addViolation();
        }
        if (TypePrestation::Logement === $this->type && null !== $this->dureeMinutes) {
            $context->buildViolation('Un logement n\'a pas de durée : laissez ce champ vide.')->atPath('dureeMinutes')->addViolation();
        }
        if (TypePrestation::Activite === $this->type && null !== $this->chambres) {
            $context->buildViolation('Une activité n\'a pas de chambres : laissez ce champ vide.')->atPath('chambres')->addViolation();
        }
    }

    public function __toString(): string
    {
        return (string) $this->nom;
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

    public function getChambres(): ?int
    {
        return $this->chambres;
    }

    public function setChambres(?int $chambres): static
    {
        $this->chambres = $chambres;

        return $this;
    }

    /**
     * Première photo, pour les vignettes.
     */
    public function getPhotoPrincipale(): ?string
    {
        return $this->photos[0] ?? null;
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
        $this->equipements = array_values(array_unique(array_filter(
            array_map(static fn (string $equipement): string => trim($equipement), $equipements),
            static fn (string $equipement): bool => '' !== $equipement,
        )));

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
        // Vocabulaire commun au chatbot et aux filtres : « Plage », « plage » et « plage » saisis deux fois ne font qu'un.
        $this->envies = array_values(array_unique(array_filter(
            array_map(static fn (string $envie): string => mb_strtolower(trim($envie)), $envies),
            static fn (string $envie): bool => '' !== $envie,
        )));

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

    /**
     * Ce à quoi une envie du client peut correspondre : envies, équipements, catégorie et nom (« piscine privée »,
     * « dauphins », « plage »).
     *
     * @return list<string>
     */
    public function motsCles(): array
    {
        return array_values(array_filter([...$this->envies, ...$this->equipements, (string) $this->categorie, (string) $this->nom], static fn (string $m): bool => '' !== $m));
    }

    /** Le tarif qui s'applique ce jour-là, ou null si aucune période ne le couvre. */
    public function tarifLe(\DateTimeImmutable $date): ?TarifParPeriode
    {
        $jour = $date->format('Y-m-d');
        foreach ($this->tarifs as $tarif) {
            if ($tarif->getDebut()?->format('Y-m-d') <= $jour && $jour <= $tarif->getFin()?->format('Y-m-d')) {
                return $tarif;
            }
        }

        return null;
    }

    /**
     * Le tarif de chacune des $nuits à partir de $premiereNuit, null pour une nuit qu'aucune période ne couvre.
     *
     * @return list<TarifParPeriode|null>
     */
    public function tarifsDesNuits(\DateTimeImmutable $premiereNuit, int $nuits): array
    {
        $tarifs = [];
        for ($i = 0; $i < $nuits; ++$i) {
            $tarifs[] = $this->tarifLe($premiereNuit->modify(\sprintf('+%d days', $i)));
        }

        return $tarifs;
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
