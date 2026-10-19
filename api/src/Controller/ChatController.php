<?php

namespace App\Controller;

use App\Chatbot\AgentConversation;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Repository\ConversationRepository;
use App\Security\VisiteurChatbot;
use App\Security\Voter\DemandeVoter;
use App\Service\RechercheCatalogueService;
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
    #[Route('/catalogue', name: 'chat_catalogue', methods: ['GET'])]
    public function catalogue(Request $request, RechercheCatalogueService $recherche): JsonResponse
    {
        $debut = $this->dateQuery($request, 'dateDebut');
        $fin = $this->dateQuery($request, 'dateFin');
        if (null === $debut || null === $fin || $debut > $fin) {
            return $this->json([
                'erreur' => 'Les paramètres dateDebut et dateFin sont requis au format AAAA-MM-JJ.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $temperatureMin = $this->integerQuery($request, 'temperatureMin');
        $temperatureMax = $this->integerQuery($request, 'temperatureMax');
        if ($temperatureMin > $temperatureMax) {
            return $this->json([
                'erreur' => 'La température minimale doit être inférieure ou égale à la température maximale.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $destinations = $recherche->rechercher($debut, $fin, $temperatureMin, $temperatureMax);

        return $this->json([
            'destinations' => $destinations,
            'resultat' => [] === $destinations ? 'aucune_disponibilite' : 'disponibilites_trouvees',
        ]);
    }

    /** Longueur maximale d'un message du client, en caractères. */
    public const MESSAGE_MAX = 2000;

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
    #[Route('/demandes/{id}', name: 'chat_demande', methods: ['GET'], requirements: ['id' => '\\d+'])]
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

    private function dateQuery(Request $request, string $name): ?\DateTimeImmutable
    {
        $value = $request->query->get($name);
        if (!is_string($value) || '' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        $errors = \DateTimeImmutable::getLastErrors();

        return false !== $date && (false === $errors || 0 === $errors['warning_count'] + $errors['error_count']) ? $date : null;
    }

    private function integerQuery(Request $request, string $name): ?int
    {
        $value = $request->query->get($name);

        return is_string($value) && '' !== $value && filter_var($value, \FILTER_VALIDATE_INT) !== false
            ? (int) $value
            : null;
    }
}
