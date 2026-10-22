<?php

namespace App\Controller;

use App\Chatbot\AgentConversation;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\Langue;
use App\Enum\Role;
use App\Repository\ConversationRepository;
use App\Repository\DemandeRepository;
use App\Security\VisiteurChatbot;
use App\Security\Voter\DemandeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Routes publiques du chatbot. Toute la logique IA passe par ici : le site ne parle jamais à l'API Claude.
 * Chaque appel est limité en débit par adresse IP (LimiteChatbotSubscriber).
 */
#[Route('/api/chat')]
final class ChatController extends AbstractController
{
    /** Longueur maximale d'un message du client, en caractères. */
    public const MESSAGE_MAX = 2000;

    public const MESSAGE_VOYAGES_MAX = 'Vous avez déjà 3 voyages en cours, le maximum. Poursuivez l\'un d\'eux depuis « Mon espace », ou demandez à votre conseiller d\'en clore un pour en commencer un nouveau.';
    public const MESSAGE_VOYAGES_MAX_EN = 'You already have 3 trips in progress, the maximum. Continue one of them from “My account”, or ask your advisor to close one to start a new one.';

    /**
     * Envoie un message du client et diffuse la réponse de l'assistant en Server-Sent Events.
     * Corps JSON {"message": "…"} ; la conversation est celle du jeton (en-tête X-Conversation-Token).
     */
    #[Route('', name: 'chat_envoyer', methods: ['POST'])]
    public function envoyer(
        Request $request,
        #[CurrentUser] VisiteurChatbot $visiteur,
        ConversationRepository $conversations,
        AgentConversation $agent,
    ): Response {
        try {
            $message = $request->toArray()['message'] ?? null;
        } catch (JsonException) {
            $message = null;
        }
        $message = \is_string($message) ? trim($message) : '';
        if ('' === $message || mb_strlen($message) > self::MESSAGE_MAX) {
            return $this->json(
                ['erreur' => \sprintf('Le message doit contenir entre 1 et %d caractères.', self::MESSAGE_MAX)],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $conversation = $conversations->find($visiteur->getConversationId())
            ?? throw $this->createNotFoundException('Conversation introuvable.');
        $this->denyAccessUnlessGranted(DemandeVoter::MODIFIER, $conversation);

        return new EventStreamResponse(static fn () => $agent->repondre($conversation, $message));
    }

    /**
     * Ouvre une conversation et sa demande vide ; le jeton renvoyé authentifie les appels suivants.
     * Un client connecté sur le site est rattaché d'office à la demande, s'il n'a pas déjà
     * Demande::VOYAGES_EN_COURS_MAX voyages en cours (409 sinon).
     */
    #[Route('/conversations', name: 'chat_demarrer', methods: ['POST'])]
    public function demarrer(Request $request, EntityManagerInterface $em, DemandeRepository $demandes, #[CurrentUser] ?Utilisateur $utilisateur): JsonResponse
    {
        $demande = new Demande();
        // Langue choisie sur le site (bouton FR | EN) : l'assistant commence dans cette langue, puis suit celle du client.
        try {
            $langue = $request->toArray()['langue'] ?? null;
        } catch (JsonException) {
            $langue = null;
        }
        $demande->setLangue(Langue::tryFrom(\is_string($langue) ? $langue : '') ?? Langue::Francais);
        if (Role::Client === $utilisateur?->getRole()) {
            if ($demandes->compterEnCoursDuClient($utilisateur) >= Demande::VOYAGES_EN_COURS_MAX) {
                return $this->json(['erreur' => $demande->getLangue()->choisir(self::MESSAGE_VOYAGES_MAX, self::MESSAGE_VOYAGES_MAX_EN)], Response::HTTP_CONFLICT);
            }
            $demande->setClient($utilisateur);
        }
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
