<?php

namespace App\Controller;

use App\Entity\Destination;
use App\Entity\Prestation;
use App\Repository\DestinationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/catalogue')]
final class CatalogueController extends AbstractController
{
    #[Route('/destinations', name: 'api_catalogue_destinations', methods: ['GET'])]
    public function destinations(DestinationRepository $repository): JsonResponse
    {
        $resultats = [];
        foreach ($repository->findBy(['publiee' => true], ['ville' => 'ASC']) as $destination) {
            $resultats[] = [
                'id' => $destination->getId(),
                'slug' => $destination->getSlug(),
                'ville' => $destination->getVille(),
                'pays' => $destination->getPays(),
                'region' => $destination->getRegion(),
                'description' => $destination->getDescription(),
                'pointsForts' => $destination->getPointsForts(),
                'tempsDeTrajet' => $destination->getTempsDeTrajet(),
                'photo' => $destination->getPhoto(),
                'miseEnAvant' => $destination->isMiseEnAvant(),
                'climat' => $this->climat($destination),
                'prestations' => array_map(
                    fn (Prestation $prestation): array => $this->prestation($prestation),
                    $destination->getPrestations()->toArray(),
                ),
            ];
        }

        return $this->json(['destinations' => $resultats]);
    }

    /**
     * @return list<array{debut: string, fin: string, temperatureMoyenne: float, pluieMm: ?int}>
     */
    private function climat(Destination $destination): array
    {
        $climat = [];
        foreach ($destination->getPeriodesClimatiques() as $periode) {
            if (null === $periode->getDebut() || null === $periode->getFin() || null === $periode->getTemperatureMoyenne()) {
                continue;
            }
            $climat[] = [
                'debut' => $periode->getDebut()->format('Y-m-d'),
                'fin' => $periode->getFin()->format('Y-m-d'),
                'temperatureMoyenne' => $periode->getTemperatureMoyenne(),
                'pluieMm' => $periode->getPluieMm(),
            ];
        }

        return $climat;
    }

    /**
     * @return array{id: int|null, nom: string|null, destination: string|null, categorie: string|null, capacite: int, accessible: bool, duree: int|null, envies: list<string>, photo: string|null, tarifs: list<array{debut: string, fin: string, prixCentimes: int, unite: string, sejourMinimumNuits: int|null}>}
     */
    private function prestation(Prestation $prestation): array
    {
        $tarifs = [];
        foreach ($prestation->getTarifs() as $tarif) {
            if (null === $tarif->getDebut() || null === $tarif->getFin() || null === $tarif->getPrixCentimes()) {
                continue;
            }
            $tarifs[] = [
                'debut' => $tarif->getDebut()->format('Y-m-d'),
                'fin' => $tarif->getFin()->format('Y-m-d'),
                'prixCentimes' => $tarif->getPrixCentimes(),
                'unite' => $tarif->getUnite()->value,
                'sejourMinimumNuits' => $tarif->getSejourMinimumNuits(),
            ];
        }

        return [
            'id' => $prestation->getId(),
            'nom' => $prestation->getNom(),
            'destination' => $prestation->getDestination()?->getSlug(),
            'categorie' => $prestation->getCategorie(),
            'capacite' => $prestation->getCapacite(),
            'accessible' => $prestation->isAccessible(),
            'duree' => $prestation->getDureeMinutes(),
            'envies' => $prestation->getEnvies(),
            'photo' => $prestation->getPhotos()[0] ?? null,
            'tarifs' => $tarifs,
        ];
    }
}
