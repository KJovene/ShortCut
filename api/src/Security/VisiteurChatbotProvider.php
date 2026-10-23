<?php

namespace App\Security;

use App\Repository\ConversationRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Charge le visiteur du chatbot à partir du jeton de sa conversation.
 *
 * @implements UserProviderInterface<VisiteurChatbot>
 */
final class VisiteurChatbotProvider implements UserProviderInterface
{
    public function __construct(private readonly ConversationRepository $conversations)
    {
    }

    public function loadUserByIdentifier(string $identifier): VisiteurChatbot
    {
        $conversation = '' === $identifier ? null : $this->conversations->findOneBy(['jeton' => $identifier]);
        $conversationId = $conversation?->getId();
        $demandeId = $conversation?->getDemande()?->getId();

        if ('' === $identifier || null === $conversationId || null === $demandeId) {
            throw new UserNotFoundException('Conversation introuvable.');
        }

        return new VisiteurChatbot($identifier, $conversationId, $demandeId);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        // Pare-feu sans session : le visiteur est rechargé à chaque requête par son jeton.
        throw new UnsupportedUserException('Le visiteur du chatbot n\'est jamais stocké en session.');
    }

    public function supportsClass(string $class): bool
    {
        return VisiteurChatbot::class === $class;
    }
}
