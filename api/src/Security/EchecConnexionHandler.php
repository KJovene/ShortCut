<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Connexion refusée (mauvais identifiants, trop de tentatives) : le message du pare-feu, traduit, sous la clé
 * « erreur » comme toutes les erreurs de l'API (D28).
 */
final class EchecConnexionHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(private readonly TranslatorInterface $traducteur)
    {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['erreur' => $this->traducteur->trans($exception->getMessageKey(), $exception->getMessageData(), 'security')],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
