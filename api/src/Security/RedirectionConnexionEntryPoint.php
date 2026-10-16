<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Accès sans session : l'API répond 401 en JSON, le backoffice renvoie vers la page de connexion du site React.
 */
final class RedirectionConnexionEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(#[Autowire('%env(SITE_URL)%')] private readonly string $siteUrl)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse(['erreur' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse(rtrim($this->siteUrl, '/').'/connexion');
    }
}
