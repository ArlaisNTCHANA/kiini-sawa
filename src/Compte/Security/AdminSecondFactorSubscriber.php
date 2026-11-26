<?php

namespace App\Compte\Security;

use App\Compte\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Connexion en deux étapes des administrateurs : après le mot de passe, un code reçu par e-mail.
 * Exigé à chaque nouvelle session (y compris une reconnexion automatique « se souvenir de moi »).
 * Désactivable par ADMIN_SECOND_FACTOR=0 (environnement de test).
 */
final class AdminSecondFactorSubscriber implements EventSubscriberInterface
{
    public const SESSION_KEY = 'admin_second_factor_ok';

    private const ALLOWED_ROUTES = ['app_admin_login_code', 'app_admin_login_code_resend', 'app_logout', 'app_legal'];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%env(bool:default::ADMIN_SECOND_FACTOR)%')] private readonly ?bool $enabled = true,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le pare-feu (8) et la vérification de l'e-mail (4)
        return [KernelEvents::REQUEST => ['onKernelRequest', 3]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || false === $this->enabled) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isVerified() || !\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }
        $request = $event->getRequest();
        if (!$request->hasSession() || true === $request->getSession()->get(self::SESSION_KEY)) {
            return;
        }
        $route = (string) $request->attributes->get('_route');
        if (\in_array($route, self::ALLOWED_ROUTES, true) || str_starts_with($route, '_')) {
            return;
        }

        $event->setResponse(str_starts_with($request->getPathInfo(), '/api/')
            ? new JsonResponse(['error' => 'Code de connexion administrateur requis.'], 403)
            : new RedirectResponse($this->urls->generate('app_admin_login_code')));
    }
}
