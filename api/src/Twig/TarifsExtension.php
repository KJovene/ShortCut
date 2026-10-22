<?php

namespace App\Twig;

use App\Admin\GrilleTarifs;
use App\Admin\GrilleTransport;
use App\Entity\TarifParPeriode;
use Psr\Clock\ClockInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Tarifs du backoffice, lus par un conseiller : grille d'une fiche, résumé dans une liste, fourchettes d'un
 * transporteur (GrilleTarifs, GrilleTransport). En fonctions Twig, car EasyAdmin remplace la valeur formatée d'une
 * collection par son nombre d'éléments.
 */
final class TarifsExtension
{
    public function __construct(private readonly ClockInterface $horloge)
    {
    }

    /**
     * @param iterable<TarifParPeriode> $tarifs
     *
     * @return list<array<string, mixed>>
     */
    #[AsTwigFunction('grille_tarifs')]
    public function grilleTarifs(iterable $tarifs): array
    {
        return GrilleTarifs::lignes($tarifs, $this->horloge->now());
    }

    /**
     * @param iterable<TarifParPeriode> $tarifs
     *
     * @return array<string, mixed>|null
     */
    #[AsTwigFunction('resume_tarifs')]
    public function resumeTarifs(iterable $tarifs): ?array
    {
        return GrilleTarifs::resume($tarifs, $this->horloge->now());
    }

    /**
     * @param list<array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}> $fourchettes
     *
     * @return array<string, mixed>
     */
    #[AsTwigFunction('grille_transport')]
    public static function grilleTransport(array $fourchettes): array
    {
        return GrilleTransport::depuis($fourchettes);
    }
}
