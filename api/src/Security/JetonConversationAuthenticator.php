<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authentifie le chatbot par l'en-tête X-Conversation-Token, reçu à la création de la conversation.
 */
final class JetonConversationAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const EN_TETE = 'X-Conversation-Token';

    public function supports(Request $request): bool
    {
        return $request->headers->has(self::EN_TETE);
    }

    public function authenticate(Request $request): Passport
    {
        return new SelfValidatingPassport(new UserBadge((string) $request->headers->get(self::EN_TETE)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['erreur' => 'Conversation introuvable : recommencez une nouvelle conversation.'],
            Response::HTTP_UNAUTHORIZED,
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            ['erreur' => \sprintf('Jeton de conversation manquant (en-tête %s).', self::EN_TETE)],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
