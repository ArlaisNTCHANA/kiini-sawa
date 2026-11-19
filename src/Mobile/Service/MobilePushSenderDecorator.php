<?php

namespace App\Mobile\Service;

use App\Compte\Entity\User;
use App\Notification\Entity\PushSubscription;
use App\Notification\Service\PushEndpointValidator;
use App\Notification\Service\PushSender;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Décorateur transparent de PushSender intégrant les notifications Expo Push pour mobiles.
 * Modifie ZERO fichier existant du module Notification.
 */
#[AsDecorator(decorates: PushSender::class)]
final class MobilePushSenderDecorator extends PushSender
{
    public function __construct(
        private readonly PushSender $inner,
        private readonly ExpoPushSender $expo,
        EntityManagerInterface $em,
        PushEndpointValidator $endpoints,
        LoggerInterface $logger,
    ) {
        parent::__construct($em, $endpoints, $logger, null, null, null);
    }

    public function isConfigured(): bool
    {
        return $this->inner->isConfigured();
    }

    public function publicKey(): ?string
    {
        return $this->inner->publicKey();
    }

    public function hasDevice(User $user): bool
    {
        return $this->inner->hasDevice($user) || $this->expo->hasDevice($user);
    }

    public function isEndpointAllowed(string $endpoint): bool
    {
        return $this->inner->isEndpointAllowed($endpoint);
    }

    public function subscribe(User $user, string $endpoint, string $p256dh, string $auth, ?string $userAgent): PushSubscription
    {
        return $this->inner->subscribe($user, $endpoint, $p256dh, $auth, $userAgent);
    }

    public function unsubscribe(User $user, string $endpoint): void
    {
        $this->inner->unsubscribe($user, $endpoint);
    }

    public function send(User $user, string $title, string $body, ?string $url = null, ?string $tag = null): int
    {
        $webCount = $this->inner->send($user, $title, $body, $url, $tag);
        $expoCount = $this->expo->send($user, $title, $body, $url, $tag);

        return $webCount + $expoCount;
    }
}
