<?php

namespace App\Entity;

use App\Repository\TraductionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Traduction anglaise d'un texte du site public (pages, destinations), faite une fois par Claude (Traducteur) puis
 * relue ici. Retrouvée par l'empreinte du texte français : quand l'agence modifie ce texte dans le backoffice, il est
 * traduit à nouveau ; une traduction corrigée dans « Traductions » reste servie tant que le français ne change pas.
 */
#[ORM\Entity(repositoryClass: TraductionRepository::class)]
#[ORM\Table(name: 'traduction')]
class Traduction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** sha1 du texte français. */
    #[ORM\Column(type: Types::STRING, length: 40, unique: true)]
    private string $empreinte;

    #[ORM\Column(type: Types::TEXT)]
    private string $francais;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Saisissez la traduction.')]
    private string $anglais;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $creeLe;

    public function __construct(string $francais, string $anglais)
    {
        $this->empreinte = self::empreinte($francais);
        $this->francais = $francais;
        $this->anglais = $anglais;
        $this->creeLe = new \DateTimeImmutable();
    }

    public static function empreinte(string $francais): string
    {
        return sha1($francais);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmpreinte(): string
    {
        return $this->empreinte;
    }

    public function getFrancais(): string
    {
        return $this->francais;
    }

    public function getAnglais(): string
    {
        return $this->anglais;
    }

    public function setAnglais(string $anglais): static
    {
        $this->anglais = $anglais;

        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }
}
