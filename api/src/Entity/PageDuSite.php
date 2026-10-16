<?php

namespace App\Entity;

use App\Repository\PageDuSiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** Contenu modifiable d'une page du site (textes, photos, référencement). */
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
    private ?string $titre = null;

    #[ORM\Column(type: Types::STRING, length: 150, unique: true)]
    #[Assert\NotBlank]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contenu = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $photos = [];

    #[ORM\Column(type: Types::STRING, length: 70, nullable: true)]
    private ?string $seoTitre = null;

    #[ORM\Column(type: Types::STRING, length: 160, nullable: true)]
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
