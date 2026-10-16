<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Client anonyme du chatbot, identifié par le jeton de sa conversation.
 * Il n'a accès qu'à la demande liée à cette conversation (voir DemandeVoter).
 */
final class VisiteurChatbot implements UserInterface
{
    public const ROLE = 'ROLE_CHATBOT';

    /**
     * @param non-empty-string $jeton
     */
    public function __construct(
        private readonly string $jeton,
        private readonly int $conversationId,
        private readonly int $demandeId,
    ) {
    }

    public function getConversationId(): int
    {
        return $this->conversationId;
    }

    public function getDemandeId(): int
    {
        return $this->demandeId;
    }

    public function getUserIdentifier(): string
    {
        return $this->jeton;
    }

    public function getRoles(): array
    {
        return [self::ROLE];
    }

    public function eraseCredentials(): void
    {
    }
}
