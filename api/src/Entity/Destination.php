<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\ApiPlatform\Filtre\ClimatFilter;
use App\ApiPlatform\Filtre\EnvieFilter;
use App\ApiPlatform\Filtre\MoisFilter;
use App\ApiPlatform\PublieeExtension;
use App\Enum\TypeDeVoyage;
use App\Repository\DestinationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Destination du catalogue, avec son climat par période.
 * Groupe `destination:public` : ce que le site peut montrer ; `destination:interne` : réservé au backoffice.
 * Groupe `public:read` : réponses des opérations publiques /api/public/… (voir aussi DestinationPubliqueNormalizer).
 */
#[ApiResource(
    operations: [
        new GetCollection(parameters: [
            'pays' => new QueryParameter(filter: new PartialSearchFilter(), property: 'pays', castToArray: false),
            'ville' => new QueryParameter(filter: new PartialSearchFilter(), property: 'ville', castToArray: false),
            'slug' => new QueryParameter(filter: new ExactFilter(), property: 'slug', castToArray: false),
            'publiee' => new QueryParameter(filter: new ExactFilter(), property: 'publiee', nativeType: new BuiltinType(TypeIdentifier::BOOL), castToNativeType: true, castToArray: false),
            'miseEnAvant' => new QueryParameter(filter: new ExactFilter(), property: 'miseEnAvant', nativeType: new BuiltinType(TypeIdentifier::BOOL), castToNativeType: true, castToArray: false),
            'mois' => new QueryParameter(
                filter: new ClimatFilter(),
                description: 'Mois du voyage, au format AAAA-MM. Combiné à temperatureMin ou temperatureMax, la température porte sur ce mois.',
                schema: ['type' => 'string', 'pattern' => '^\\d{4}-(0[1-9]|1[0-2])$', 'example' => '2027-05'],
                constraints: [new Assert\Regex(MoisFilter::FORMAT, message: 'Le mois doit être au format AAAA-MM, par exemple 2027-05.')],
                castToArray: false,
            ),
            'temperatureMin' => new QueryParameter(
                filter: new ClimatFilter(),
                description: 'Température moyenne minimale, en °C.',
                schema: ['type' => 'number'],
                constraints: [new Assert\Range(min: -50, max: 60)],
                castToArray: false,
            ),
            'temperatureMax' => new QueryParameter(
                filter: new ClimatFilter(),
                description: 'Température moyenne maximale, en °C.',
                schema: ['type' => 'number'],
                constraints: [new Assert\Range(min: -50, max: 60)],
                castToArray: false,
            ),
            'envie' => new QueryParameter(
                filter: new EnvieFilter(parDestination: true),
                description: 'Au moins un logement ou une activité de la destination correspond à cette envie (plage, culture…).',
                castToArray: false,
            ),
        ]),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
        // Site public : destinations publiées seulement (PublieeExtension), sans les données internes.
        new GetCollection(
            uriTemplate: '/public/destinations',
            itemUriTemplate: '/public/destinations/{slug}',
            normalizationContext: ['groups' => ['public:read']],
            security: "is_granted('PUBLIC_ACCESS')",
            extraProperties: [PublieeExtension::PUBLIEES => true],
            parameters: [
                'miseEnAvant' => new QueryParameter(filter: new ExactFilter(), property: 'miseEnAvant', nativeType: new BuiltinType(TypeIdentifier::BOOL), castToNativeType: true, castToArray: false),
                'mois' => new QueryParameter(
                    filter: new ClimatFilter(),
                    description: 'Mois du voyage, au format AAAA-MM. Combiné à temperatureMin ou temperatureMax, la température porte sur ce mois.',
                    schema: ['type' => 'string', 'pattern' => '^\\d{4}-(0[1-9]|1[0-2])$', 'example' => '2027-05'],
                    constraints: [new Assert\Regex(MoisFilter::FORMAT, message: 'Le mois doit être au format AAAA-MM, par exemple 2027-05.')],
                    castToArray: false,
                ),
                'temperatureMin' => new QueryParameter(
                    filter: new ClimatFilter(),
                    description: 'Température moyenne minimale, en °C.',
                    schema: ['type' => 'number'],
                    constraints: [new Assert\Range(min: -50, max: 60)],
                    castToArray: false,
                ),
                'temperatureMax' => new QueryParameter(
                    filter: new ClimatFilter(),
                    description: 'Température moyenne maximale, en °C.',
                    schema: ['type' => 'number'],
                    constraints: [new Assert\Range(min: -50, max: 60)],
                    castToArray: false,
                ),
            ],
        ),
        new Get(
            uriTemplate: '/public/destinations/{slug}',
            uriVariables: ['slug' => new Link(fromClass: self::class, identifiers: ['slug'])],
            normalizationContext: ['groups' => ['public:read']],
            security: "is_granted('PUBLIC_ACCESS')",
            extraProperties: [PublieeExtension::PUBLIEES => true],
        ),
    ],
    normalizationContext: ['groups' => ['destination:public', 'destination:interne']],
    denormalizationContext: ['groups' => ['destination:ecriture']],
    order: ['ville' => 'ASC'],
    security: "is_granted('ROLE_CONSEILLER')",
)]
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
    #[Groups(['destination:public'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Indiquez le pays.')]
    #[Assert\Length(max: 100)]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private ?string $pays = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private ?string $region = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Indiquez la ville.')]
    #[Assert\Length(max: 100)]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private ?string $ville = null;

    /** Adresse de la fiche sur le site. */
    #[ORM\Column(type: Types::STRING, length: 150, unique: true)]
    #[Assert\NotBlank(message: 'Indiquez l\'adresse de la fiche (slug).')]
    #[Assert\Length(max: 150)]
    #[Assert\Regex('/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Le slug ne contient que des minuscules sans accent, des chiffres et des tirets (ex. « costa-rica »).')]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private ?string $slug = null;

    /**
     * Minutes de trajet, indexées par ville de départ.
     *
     * @var array<string, int>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([
        new Assert\Type('int', message: 'Un temps de trajet est un nombre entier de minutes.'),
        new Assert\Range(min: 1, max: 2880),
    ])]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private array $tempsDeTrajet = [];

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(max: 10)]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 150)])]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private array $pointsForts = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['destination:public', 'destination:ecriture'])]
    private ?string $photo = null;

    /** Description de la photo pour les lecteurs d'écran. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private ?string $photoAlt = null;

    /** Nom affiché sur le site (« Lisbonne, Portugal », « Islande ») ; vide : ville et pays. */
    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    #[Groups(['destination:public', 'destination:ecriture'])]
    private ?string $nom = null;

    /** Une phrase d'accroche, affichée sur la carte du monde de l'accueil. */
    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private ?string $accroche = null;

    /**
     * Types de voyage proposés pour cette destination, le principal en premier (valeurs de TypeDeVoyage).
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Choice(callback: [TypeDeVoyage::class, 'valeurs'], multiple: true, multipleMessage: 'Type de voyage inconnu.')]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private array $typesDeVoyage = [];

    /** Meilleur mois pour partir, de 1 (janvier) à 12 (décembre). */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 1, max: 12)]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private ?int $moisIdeal = null;

    /** Position sur la carte du monde de l'accueil. */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private ?float $longitude = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['destination:interne', 'destination:ecriture'])]
    private bool $publiee = false;

    /** Affichée sur la page d'accueil. */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['destination:public', 'public:read', 'destination:ecriture'])]
    private bool $miseEnAvant = false;

    /**
     * Climat en lecture seule dans l'API : il se saisit dans le backoffice.
     *
     * @var Collection<int, PeriodeClimatique>
     */
    #[ORM\OneToMany(targetEntity: PeriodeClimatique::class, mappedBy: 'destination', orphanRemoval: true)]
    #[ORM\OrderBy(['debut' => 'ASC'])]
    #[Groups(['destination:public', 'public:read'])]
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

    public function __toString(): string
    {
        return \sprintf('%s (%s)', $this->ville, $this->pays);
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
     * Temps de trajet en lignes (ville, minutes), pour le formulaire du backoffice.
     *
     * @return list<array{ville: string, minutes: int}>
     */
    public function getTrajets(): array
    {
        $trajets = [];
        foreach ($this->tempsDeTrajet as $ville => $minutes) {
            $trajets[] = ['ville' => (string) $ville, 'minutes' => $minutes];
        }

        return $trajets;
    }

    /**
     * @param iterable<array{ville?: ?string, minutes?: ?int}> $trajets
     */
    public function setTrajets(iterable $trajets): static
    {
        $this->tempsDeTrajet = [];
        foreach ($trajets as $trajet) {
            $ville = trim((string) ($trajet['ville'] ?? ''));
            if ('' !== $ville && null !== ($trajet['minutes'] ?? null)) {
                $this->tempsDeTrajet[$ville] = (int) $trajet['minutes'];
            }
        }

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

    /** Adresse de la photo sur le site public. */
    #[Groups(['public:read'])]
    #[SerializedName('photo')]
    public function getUrlPhoto(): ?string
    {
        return null === $this->photo ? null : '/uploads/destinations/'.$this->photo;
    }

    public function getPhotoAlt(): ?string
    {
        return $this->photoAlt;
    }

    public function setPhotoAlt(?string $photoAlt): static
    {
        $this->photoAlt = $photoAlt;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    #[Groups(['public:read'])]
    #[SerializedName('nom')]
    public function getNomAffiche(): string
    {
        return $this->nom ?? \sprintf('%s, %s', $this->ville, $this->pays);
    }

    public function getAccroche(): ?string
    {
        return $this->accroche;
    }

    public function setAccroche(?string $accroche): static
    {
        $this->accroche = $accroche;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTypesDeVoyage(): array
    {
        return $this->typesDeVoyage;
    }

    /**
     * @param list<string> $typesDeVoyage
     */
    public function setTypesDeVoyage(array $typesDeVoyage): static
    {
        $this->typesDeVoyage = $typesDeVoyage;

        return $this;
    }

    public function getMoisIdeal(): ?int
    {
        return $this->moisIdeal;
    }

    public function setMoisIdeal(?int $moisIdeal): static
    {
        $this->moisIdeal = $moisIdeal;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

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
