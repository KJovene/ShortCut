<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/** Le chatbot reçoit un 403 en JSON, jamais une page HTML. */
final class AccesRefuseChatbotHandler implements AccessDeniedHandlerInterface
{
    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return new JsonResponse(['erreur' => 'Cette demande ne fait pas partie de votre conversation.'], Response::HTTP_FORBIDDEN);
    }
}
