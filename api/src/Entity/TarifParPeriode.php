<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\IriFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\ApiPlatform\Filtre\MoisFilter;
use App\Enum\TypePrestation;
use App\Enum\UniteTarif;
use App\Repository\TarifParPeriodeRepository;
use App\Validator\NoPeriodOverlap;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Prix d'un logement ou d'une activité sur une période.
 * Prix d'achat au prestataire, avant marge : donnée interne, jamais exposée au site.
 * Groupe `prestation:tarifs` : vue d'un tarif dans la fiche de sa prestation.
 */
#[ApiResource(
    shortName: 'Tarif',
    operations: [
        new GetCollection(uriTemplate: '/tarifs', parameters: [
            'prestation' => new QueryParameter(filter: new IriFilter(), property: 'prestation', castToArray: false),
            'unite' => new QueryParameter(
                filter: new ExactFilter(),
                property: 'unite',
                schema: ['type' => 'string', 'enum' => ['par_nuit', 'par_personne']],
                nativeType: new BackedEnumType(UniteTarif::class, new BuiltinType(TypeIdentifier::STRING)),
                castToNativeType: true,
                castToArray: false,
            ),
            'mois' => new QueryParameter(
                filter: new MoisFilter(),
                description: 'Tarifs qui couvrent au moins un jour de ce mois (AAAA-MM).',
                schema: ['type' => 'string', 'pattern' => '^\\d{4}-(0[1-9]|1[0-2])$', 'example' => '2027-05'],
                constraints: [new Assert\Regex(MoisFilter::FORMAT, message: 'Le mois doit être au format AAAA-MM, par exemple 2027-05.')],
                castToArray: false,
            ),
        ]),
        new Get(uriTemplate: '/tarifs/{id}'),
        new Post(uriTemplate: '/tarifs'),
        new Patch(uriTemplate: '/tarifs/{id}'),
        new Delete(uriTemplate: '/tarifs/{id}'),
    ],
    normalizationContext: ['groups' => ['tarif:lecture']],
    denormalizationContext: ['groups' => ['tarif:ecriture']],
    order: ['debut' => 'ASC'],
    security: "is_granted('ROLE_CONSEILLER')",
)]
#[ORM\Entity(repositoryClass: TarifParPeriodeRepository::class)]
#[ORM\Table(name: 'tarif_par_periode')]
#[ORM\Index(columns: ['prestation_id', 'debut', 'fin'])]
#[NoPeriodOverlap]
class TarifParPeriode implements SaisieTracee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['tarif:lecture', 'prestation:tarifs'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestation::class, inversedBy: 'tarifs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Choisissez le logement ou l\'activité.')]
    #[Groups(['tarif:lecture', 'tarif:ecriture'])]
    private ?Prestation $prestation = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Indiquez la date de début.')]
    #[Groups(['tarif:lecture', 'tarif:ecriture', 'prestation:tarifs'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $debut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Indiquez la date de fin.')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'debut', message: 'La fin doit être le même jour que le début ou après.')]
    #[Groups(['tarif:lecture', 'tarif:ecriture', 'prestation:tarifs'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $fin = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull(message: 'Indiquez le prix, en centimes.')]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    #[Groups(['tarif:lecture', 'tarif:ecriture', 'prestation:tarifs'])]
    private ?int $prixCentimes = null;

    #[ORM\Column(enumType: UniteTarif::class)]
    #[Groups(['tarif:lecture', 'tarif:ecriture', 'prestation:tarifs'])]
    private UniteTarif $unite = UniteTarif::ParNuit;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'Le séjour minimum doit être d\'au moins une nuit.')]
    #[Groups(['tarif:lecture', 'tarif:ecriture', 'prestation:tarifs'])]
    private ?int $sejourMinimumNuits = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $saisiPar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['tarif:lecture'])]
    private \DateTimeImmutable $modifieLe;

    public function __construct()
    {
        $this->modifieLe = new \DateTimeImmutable();
    }

    /** Un logement se paie à la nuit, une activité par personne (docs/chiffrage.md). */
    #[Assert\Callback]
    public function verifierUnite(ExecutionContextInterface $context): void
    {
        $type = $this->prestation?->getType();
        if (null === $type) {
            return;
        }

        $attendue = TypePrestation::Logement === $type ? UniteTarif::ParNuit : UniteTarif::ParPersonne;
        if ($attendue !== $this->unite) {
            $context->buildViolation(TypePrestation::Logement === $type
                ? 'Le tarif d\'un logement est par nuit (par_nuit).'
                : 'Le tarif d\'une activité est par personne (par_personne).')
                ->atPath('unite')
                ->addViolation();
        }
        if (TypePrestation::Activite === $type && null !== $this->sejourMinimumNuits) {
            $context->buildViolation('Le séjour minimum ne concerne que les logements.')->atPath('sejourMinimumNuits')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrestation(): ?Prestation
    {
        return $this->prestation;
    }

    public function setPrestation(Prestation $prestation): static
    {
        $this->prestation = $prestation;

        return $this;
    }

    public function getDebut(): ?\DateTimeImmutable
    {
        return $this->debut;
    }

    public function setDebut(\DateTimeImmutable $debut): static
    {
        $this->debut = $debut;

        return $this;
    }

    public function getFin(): ?\DateTimeImmutable
    {
        return $this->fin;
    }

    public function setFin(\DateTimeImmutable $fin): static
    {
        $this->fin = $fin;

        return $this;
    }

    public function getPrixCentimes(): ?int
    {
        return $this->prixCentimes;
    }

    public function setPrixCentimes(int $prixCentimes): static
    {
        $this->prixCentimes = $prixCentimes;

        return $this;
    }

    public function getUnite(): UniteTarif
    {
        return $this->unite;
    }

    public function setUnite(UniteTarif $unite): static
    {
        $this->unite = $unite;

        return $this;
    }

    public function getSejourMinimumNuits(): ?int
    {
        return $this->sejourMinimumNuits;
    }

    public function setSejourMinimumNuits(?int $sejourMinimumNuits): static
    {
        $this->sejourMinimumNuits = $sejourMinimumNuits;

        return $this;
    }

    public function getSaisiPar(): ?Utilisateur
    {
        return $this->saisiPar;
    }

    public function setSaisiPar(?Utilisateur $saisiPar): static
    {
        $this->saisiPar = $saisiPar;

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
}
