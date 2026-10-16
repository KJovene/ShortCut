<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Security\Voter\DemandeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Routes publiques du chatbot. L'envoi des messages en streaming (POST /api/chat) arrive avec le ticket #192.
 */
#[Route('/api/chat')]
final class ChatController extends AbstractController
{
    /** Ouvre une conversation et sa demande vide ; le jeton renvoyé authentifie les appels suivants. */
    #[Route('/conversations', name: 'chat_demarrer', methods: ['POST'])]
    public function demarrer(EntityManagerInterface $em): JsonResponse
    {
        $demande = new Demande();
        $conversation = new Conversation();
        $demande->setConversation($conversation);

        $em->persist($demande);
        $em->persist($conversation);
        $em->flush();

        return $this->json(['jeton' => $conversation->getJeton(), 'demande' => $demande->getId()], Response::HTTP_CREATED);
    }

    /** Récapitulatif de la demande en cours, affiché par le widget. */
    #[Route('/demandes/{id}', name: 'chat_demande', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function demande(Demande $demande): JsonResponse
    {
        $this->denyAccessUnlessGranted(DemandeVoter::VOIR, $demande);

        return $this->json([
            'id' => $demande->getId(),
            'statut' => $demande->getStatut()->value,
            'typeDeVoyage' => $demande->getTypeDeVoyage()?->value,
            'lieuOuZone' => $demande->getLieuOuZone(),
            'nombreDeJours' => $demande->getNombreDeJours(),
            'adultes' => $demande->getAdultes(),
            'enfants' => $demande->getEnfants(),
            'envies' => $demande->getEnvies(),
        ]);
    }
}
