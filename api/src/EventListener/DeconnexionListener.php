<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Après la déconnexion : 204 pour un appel du site React (fetch), retour à la page de connexion
 * pour le lien « Déconnexion » du menu EasyAdmin.
 */
#[AsEventListener(dispatcher: 'security.event_dispatcher.main')]
final class DeconnexionListener
{
    public function __construct(#[Autowire('%env(SITE_URL)%')] private readonly string $siteUrl)
    {
    }

    public function __invoke(LogoutEvent $event): void
    {
        if ('POST' === $event->getRequest()->getMethod()) {
            $event->setResponse(new Response(status: Response::HTTP_NO_CONTENT));

            return;
        }

        $event->setResponse(new RedirectResponse(rtrim($this->siteUrl, '/').'/connexion'));
    }
}
