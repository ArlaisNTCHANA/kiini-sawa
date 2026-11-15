<?php

namespace App\Compte\Security;

use App\Compte\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tant que son adresse email n'est pas vérifiée, un utilisateur connecté
 * n'a accès qu'à la page de saisie du code, à la déconnexion et aux pages légales.
 */
final class VerifiedUserSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = [
        'app_verify_email',
        'app_verify_email_resend',
        'app_logout',
        'app_legal',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le pare-feu (priorité 8), qui charge l'utilisateur connecté
        return [KernelEvents::REQUEST => ['onKernelRequest', 4]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->isVerified()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (\in_array($route, self::ALLOWED_ROUTES, true) || str_starts_with($route, '_')) {
            return;
        }

        $event->setResponse(str_starts_with($request->getPathInfo(), '/api/')
            ? new JsonResponse(['error' => 'Adresse email non vérifiée.'], 403)
            : new RedirectResponse($this->urls->generate('app_verify_email')));
    }
}
