<?php

namespace App\Notification\Twig;

use App\Compte\Entity\User;
use App\Notification\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** {{ unread_notifications() }} : nombre de notifications non lues (pastille de la cloche). */
final class NotificationExtension extends AbstractExtension
{
    public function __construct(private readonly NotificationRepository $notifications, private readonly Security $security, private readonly \App\Notification\Service\PushSender $push)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notifications', function (): int {
                $user = $this->security->getUser();

                return $user instanceof User ? $this->notifications->countUnread($user) : 0;
            }),
            // Clé publique des notifications sur le téléphone (null si non configurées)
            new TwigFunction('push_public_key', fn (): ?string => $this->push->publicKey()),
        ];
    }
}
