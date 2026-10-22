<?php

namespace App\Lieu;

use App\Repository\DestinationRepository;
use App\Repository\PrestataireRepository;
use Symfony\Component\Intl\Countries;

use function Symfony\Component\String\u;

/**
 * Les lieux déjà connus de l'agence, proposés dans les menus déroulants du backoffice : villes des destinations,
 * villes de départ (temps de trajet, vols des transporteurs), aéroports des transferts, pays.
 */
final class LieuxDuCatalogue
{
    public function __construct(
        private readonly DestinationRepository $destinations,
        private readonly PrestataireRepository $prestataires,
    ) {
    }

    /** @return list<string> */
    public function villesDesDestinations(): array
    {
        return self::trier(array_map(static fn ($d): string => (string) $d->getVille(), $this->destinations->findAll()));
    }

    /**
     * Villes d'où partent les vols : celles des temps de trajet et des fourchettes des transporteurs, sans les
     * transferts sur place.
     *
     * @return list<string>
     */
    public function villesDeDepart(): array
    {
        $villes = [];
        foreach ($this->destinations->findAll() as $destination) {
            array_push($villes, ...array_map('strval', array_keys($destination->getTempsDeTrajet())));
        }
        foreach ($this->prestataires->findAll() as $prestataire) {
            foreach ($prestataire->getFourchettesTransport() as $fourchette) {
                if (!str_starts_with($fourchette['depart'], 'Aéroport de ')) {
                    $villes[] = $fourchette['depart'];
                }
            }
        }

        return self::trier($villes);
    }

    /**
     * Points de départ des transferts sur place : « Aéroport de Lisbonne » pour chaque destination.
     *
     * @return list<string>
     */
    public function aeroports(): array
    {
        return array_map(static fn (string $ville): string => 'Aéroport de '.$ville, $this->villesDesDestinations());
    }

    /**
     * Ce qu'une demande peut viser comme lieu ou zone : villes, régions, pays et zones des destinations.
     *
     * @return list<string>
     */
    public function lieuxEtZones(): array
    {
        $lieux = [];
        foreach ($this->destinations->findAll() as $destination) {
            array_push($lieux, (string) $destination->getVille(), (string) $destination->getPays(), (string) $destination->getRegion(), ...$destination->getZones());
        }

        return self::trier($lieux);
    }

    /**
     * Noms français des pays (symfony/intl), comme ils s'écrivent dans les destinations.
     *
     * @return list<string>
     */
    public function pays(): array
    {
        return self::trier(array_values(Countries::getNames('fr')));
    }

    /**
     * @param list<string> $valeurs
     *
     * @return list<string>
     */
    private static function trier(array $valeurs): array
    {
        $valeurs = array_values(array_unique(array_filter($valeurs, static fn (string $v): bool => '' !== $v)));
        // Sans l'extension intl (Collator) : « Égypte » se range avec les E.
        usort($valeurs, static fn (string $a, string $b): int => strcmp(self::sansAccent($a), self::sansAccent($b)));

        return $valeurs;
    }

    public static function sansAccent(string $texte): string
    {
        return u($texte)->ascii()->lower()->toString();
    }
}
