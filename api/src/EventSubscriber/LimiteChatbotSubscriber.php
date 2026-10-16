<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Limite le débit des routes du chatbot par adresse IP (voir config/packages/rate_limiter.yaml).
 * Passe avant le pare-feu : une rafale de requêtes est coupée avant tout accès à la base.
 */
final class LimiteChatbotSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Target('chatbot_messages.limiter')]
        private readonly RateLimiterFactoryInterface $messages,
        #[Target('chatbot_conversations.limiter')]
        private readonly RateLimiterFactoryInterface $conversations,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le routeur (32), avant le pare-feu (8).
        return [KernelEvents::REQUEST => ['onKernelRequest', 16]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api/chat')) {
            return;
        }

        $cle = $request->getClientIp() ?? 'inconnue';
        $limiteur = 'chat_demarrer' === $request->attributes->get('_route')
            ? $this->conversations->create($cle)
            : $this->messages->create($cle);

        $limite = $limiteur->consume();
        if ($limite->isAccepted()) {
            return;
        }

        $secondes = max(1, $limite->getRetryAfter()->getTimestamp() - time());
        $event->setResponse(new JsonResponse(
            [
                'erreur' => \sprintf('Vous envoyez trop de messages. Réessayez dans %d secondes.', $secondes),
                'reessayerDans' => $secondes,
            ],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $secondes],
        ));
    }
}
