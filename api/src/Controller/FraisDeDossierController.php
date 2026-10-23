<?php

namespace App\Controller;

use App\Enum\TypeDeVoyage;
use App\Repository\RegleDePrixRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Barème des frais de dossier pour la page « Types de voyages » du site : les montants fixés par l'administrateur
 * (règles de prix), en centimes, indexés par type de voyage.
 */
final class FraisDeDossierController
{
    #[Route('/api/public/frais-de-dossier', name: 'api_frais_de_dossier', methods: ['GET'])]
    public function __invoke(RegleDePrixRepository $regles): JsonResponse
    {
        $regle = $regles->courante();
        $frais = [];
        foreach (TypeDeVoyage::cases() as $type) {
            $frais[$type->value] = $regle->getFraisDeDossierCentimes($type);
        }

        return new JsonResponse($frais);
    }
}
