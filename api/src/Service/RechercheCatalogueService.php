<?php

namespace App\Service;

use App\Entity\Destination;
use App\Entity\PeriodeClimatique;
use App\Entity\Prestation;
use App\Entity\TarifParPeriode;
use App\Repository\DestinationRepository;

final class RechercheCatalogueService
{
    public function __construct(
        private readonly DestinationRepository $destinations,
    ) {
    }

    /**
     * @return list<array{
     *     destination: array{id: int, ville: string, pays: string, slug: string, temperatureMoyenne: float},
     *     prestations: list<array{id: int, nom: string, type: string, capacite: int, prixCentimes: int, unite: string, sejourMinimumNuits: ?int}>
     * }>
     */
    public function rechercher(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        ?int $temperatureMin = null,
        ?int $temperatureMax = null,
    ): array {
        $resultats = [];

        foreach ($this->destinations->findBy([], ['ville' => 'ASC']) as $destination) {
            $climat = $this->climatCouvrant($destination, $debut, $fin, $temperatureMin, $temperatureMax);
            if (null === $climat) {
                continue;
            }

            $prestations = [];
            foreach ($destination->getPrestations() as $prestation) {
                $tarif = $this->tarifCouvrant($prestation, $debut, $fin);
                if (null === $tarif) {
                    continue;
                }

                $prestations[] = [
                    'id' => $prestation->getId(),
                    'nom' => $prestation->getNom(),
                    'type' => $prestation->getType()?->value,
                    'capacite' => $prestation->getCapacite(),
                    'prixCentimes' => $tarif->getPrixCentimes(),
                    'unite' => $tarif->getUnite()->value,
                    'sejourMinimumNuits' => $tarif->getSejourMinimumNuits(),
                ];
            }

            if ([] === $prestations) {
                continue;
            }

            $resultats[] = [
                'destination' => [
                    'id' => $destination->getId(),
                    'ville' => $destination->getVille(),
                    'pays' => $destination->getPays(),
                    'slug' => $destination->getSlug(),
                    'temperatureMoyenne' => $climat->getTemperatureMoyenne(),
                ],
                'prestations' => $prestations,
            ];
        }

        return $resultats;
    }

    private function climatCouvrant(
        Destination $destination,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        ?int $temperatureMin,
        ?int $temperatureMax,
    ): ?PeriodeClimatique {
        foreach ($destination->getPeriodesClimatiques() as $climat) {
            $temperature = $climat->getTemperatureMoyenne();
            if (
                null !== $climat->getDebut()
                && null !== $climat->getFin()
                && $climat->getDebut() <= $debut
                && $climat->getFin() >= $fin
                && null !== $temperature
                && (null === $temperatureMin || $temperature >= $temperatureMin)
                && (null === $temperatureMax || $temperature <= $temperatureMax)
            ) {
                return $climat;
            }
        }

        return null;
    }

    private function tarifCouvrant(Prestation $prestation, \DateTimeImmutable $debut, \DateTimeImmutable $fin): ?TarifParPeriode
    {
        foreach ($prestation->getTarifs() as $tarif) {
            if (
                null !== $tarif->getDebut()
                && null !== $tarif->getFin()
                && $tarif->getDebut() <= $debut
                && $tarif->getFin() >= $fin
            ) {
                return $tarif;
            }
        }

        return null;
    }
}
