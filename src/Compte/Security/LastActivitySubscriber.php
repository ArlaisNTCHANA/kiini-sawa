<?php

namespace App\Compte\Security;

use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enregistre la dernière activité de l'utilisateur (au plus une écriture par heure),
 * après l'envoi de la réponse. Sert aux rappels « partenaire inactif ».
 */
final class LastActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security, private readonly EntityManagerInterface $em)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onTerminate'];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$event->isMainRequest() || $event->getResponse()->isServerError()) {
            return;
        }
        $last = $user->getLastActiveAt();
        if (null !== $last && $last > new \DateTimeImmutable('-1 hour')) {
            return;
        }
        $this->em->getConnection()->update('`user`', ['last_active_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')], ['id' => $user->getId()]);
    }
}
