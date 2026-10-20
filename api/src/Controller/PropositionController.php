<?php

namespace App\Controller;

use App\Confirmation\ConfirmationImpossible;
use App\Confirmation\PropositionClient;
use App\Entity\Proposition;
use App\Repository\PropositionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page « Ma proposition » (#210) : routes publiques, le lien unique (jeton non devinable) tient lieu d'accès.
 * Le jeton d'une version remplacée mène à la version qui fait foi ; les actions portent sur celle-ci. Chaque version
 * reçue reste consultable par son numéro.
 */
#[Route('/api/propositions/{jeton}', requirements: ['jeton' => '[0-9a-f]{64}'])]
final class PropositionController extends AbstractController
{
    public function __construct(
        private readonly PropositionRepository $propositions,
        private readonly PropositionClient $client,
    ) {
    }

    #[Route('', name: 'api_proposition', methods: ['GET'])]
    public function afficher(string $jeton): JsonResponse
    {
        [$demandee, $proposition] = $this->trouver($jeton);

        return $this->json($this->client->presenter($proposition, $demandee));
    }

    /** Une version précise, même remplacée : le client compare ses versions (#211, #212). */
    #[Route('/versions/{numero}', name: 'api_proposition_version', methods: ['GET'], requirements: ['numero' => '\d+'])]
    public function afficherUneVersion(string $jeton, int $numero): JsonResponse
    {
        [, $proposition] = $this->trouver($jeton);
        $version = $this->client->version($proposition, $numero) ?? throw $this->createNotFoundException('Version introuvable.');

        return $this->json($this->client->presenter($version));
    }

    /** Le client souhaite faire confirmer la proposition estimée par son conseiller. */
    #[Route('/demande-de-confirmation', name: 'api_proposition_demande_de_confirmation', methods: ['POST'])]
    public function demanderConfirmation(string $jeton): JsonResponse
    {
        [$demandee, $proposition] = $this->trouver($jeton);

        return $this->agir(fn () => $this->client->demanderConfirmation($proposition), $proposition, $demandee);
    }

    /** « J'accepte » : le client accepte la version confirmée. */
    #[Route('/acceptation', name: 'api_proposition_acceptation', methods: ['POST'])]
    public function accepter(string $jeton): JsonResponse
    {
        [$demandee, $proposition] = $this->trouver($jeton);

        return $this->agir(fn () => $this->client->accepter($proposition), $proposition, $demandee);
    }

    /** @return array{Proposition, Proposition} la version du lien et celle qui fait foi */
    private function trouver(string $jeton): array
    {
        $demandee = $this->propositions->findOneBy(['jetonPublic' => $jeton]);
        $proposition = null === $demandee ? null : $this->client->versionQuiFaitFoi($demandee);
        if (null === $demandee || null === $proposition) {
            throw $this->createNotFoundException('Proposition introuvable.');
        }

        return [$demandee, $proposition];
    }

    /** Applique l'action ; un refus (409) renvoie le message à afficher au client. */
    private function agir(\Closure $action, Proposition $proposition, Proposition $demandee): JsonResponse
    {
        try {
            $action();
        } catch (ConfirmationImpossible $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json($this->client->presenter($proposition, $demandee));
    }
}
