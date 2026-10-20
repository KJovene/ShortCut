<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\ApiPlatform\PublieeExtension;
use App\Repository\PageDuSiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contenu modifiable d'une page du site (textes, photos, référencement), saisi dans le backoffice.
 * Lecture publique par slug : seules les pages publiées sont servies (PublieeExtension), une page masquée
 * répond 404 comme une page inconnue.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/pages/{slug}',
            uriVariables: ['slug' => new Link(fromClass: self::class, identifiers: ['slug'])],
            requirements: ['slug' => '[a-z0-9-]+'],
        ),
    ],
    normalizationContext: ['groups' => ['public:read']],
    security: "is_granted('PUBLIC_ACCESS')",
    extraProperties: [PublieeExtension::PUBLIEES => true],
)]
#[ORM\Entity(repositoryClass: PageDuSiteRepository::class)]
#[ORM\Table(name: 'page_du_site')]
#[UniqueEntity('slug')]
class PageDuSite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\NotBlank]
    #[Groups(['public:read'])]
    private ?string $titre = null;

    #[ORM\Column(type: Types::STRING, length: 150, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['public:read'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contenu = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $photos = [];

    #[ORM\Column(type: Types::STRING, length: 70, nullable: true)]
    #[Groups(['public:read'])]
    private ?string $seoTitre = null;

    #[ORM\Column(type: Types::STRING, length: 160, nullable: true)]
    #[Groups(['public:read'])]
    private ?string $seoDescription = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $publiee = false;

    /** Vide : la page n'apparaît pas dans le menu. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $ordreMenu = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

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

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    /** Contenu lu par le site : une page encore vide donne un texte vide. */
    #[Groups(['public:read'])]
    #[SerializedName('contenu')]
    public function getContenuPublic(): string
    {
        return $this->contenu ?? '';
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
     * Adresses des photos, servies par l'API sous /uploads/pages.
     *
     * @return list<string>
     */
    #[Groups(['public:read'])]
    #[SerializedName('photos')]
    public function getUrlsDesPhotos(): array
    {
        return array_map(static fn (string $photo): string => '/uploads/pages/'.$photo, $this->photos);
    }

    public function getSeoTitre(): ?string
    {
        return $this->seoTitre;
    }

    public function setSeoTitre(?string $seoTitre): static
    {
        $this->seoTitre = $seoTitre;

        return $this;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function setSeoDescription(?string $seoDescription): static
    {
        $this->seoDescription = $seoDescription;

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

    public function getOrdreMenu(): ?int
    {
        return $this->ordreMenu;
    }

    public function setOrdreMenu(?int $ordreMenu): static
    {
        $this->ordreMenu = $ordreMenu;

        return $this;
    }
}
