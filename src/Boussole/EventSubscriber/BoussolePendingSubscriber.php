<?php

namespace App\Boussole\EventSubscriber;

use App\Boussole\Service\BoussoleRecorder;
use App\Compte\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Une Boussole 360 passée sans compte est enregistrée dans le compte dès la première page vue une fois connecté
 * (email vérifié), que la personne vienne de s'inscrire ou de se connecter. Les réponses attendent dans la session.
 */
final class BoussolePendingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly BoussoleRecorder $recorder,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le pare-feu (priorité 8), pour connaître l'utilisateur
        return [KernelEvents::REQUEST => ['onRequest', -10]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasPreviousSession() || str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }
        $session = $request->getSession();
        if (!$session->has(BoussoleRecorder::PENDING_KEY)) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isVerified()) {
            return;
        }
        if (null !== $this->recorder->flushPending($session, $user) && $session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'Votre résultat de la Boussole 360 est enregistré dans votre compte.');
        }
    }
}
