<?php

namespace App\Notification\Service;

use App\Compte\Entity\User;
use App\Notification\Entity\PushSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Notifications sur le téléphone (Web Push) : envoyées aux appareils que l'utilisateur a autorisés.
 * Sans clés VAPID configurées, rien n'est envoyé. Les abonnements expirés sont supprimés.
 */
class PushSender
{
    /** Appareils autorisés par compte (un téléphone, une tablette, un ordinateur… : 10 suffisent largement). */
    public const MAX_DEVICES = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PushEndpointValidator $endpoints,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::VAPID_PUBLIC_KEY)%')] private readonly ?string $publicKey,
        #[Autowire('%env(default::VAPID_PRIVATE_KEY)%')] private readonly ?string $privateKey,
        #[Autowire('%env(default::VAPID_SUBJECT)%')] private readonly ?string $subject,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== (string) $this->publicKey && '' !== (string) $this->privateKey;
    }

    public function publicKey(): ?string
    {
        return $this->isConfigured() ? $this->publicKey : null;
    }

    public function hasDevice(User $user): bool
    {
        return $this->em->getRepository(PushSubscription::class)->count(['user' => $user]) > 0;
    }

    public function isEndpointAllowed(string $endpoint): bool
    {
        return $this->endpoints->isAllowed($endpoint);
    }

    /** @throws \InvalidArgumentException adresse refusée ou trop d'appareils */
    public function subscribe(User $user, string $endpoint, string $p256dh, string $auth, ?string $userAgent): PushSubscription
    {
        if (!$this->endpoints->isAllowed($endpoint)) {
            throw new \InvalidArgumentException('Adresse de notification non autorisée.');
        }
        $repo = $this->em->getRepository(PushSubscription::class);
        $existing = $repo->findOneBy(['endpointHash' => hash('sha256', $endpoint)]);
        if (!$existing && $repo->count(['user' => $user]) >= self::MAX_DEVICES) {
            throw new \InvalidArgumentException('Nombre maximal d\'appareils atteint.');
        }
        if ($existing) {
            $existing->setUser($user)->setKeys($p256dh, $auth);
        } else {
            $existing = new PushSubscription($user, $endpoint, $p256dh, $auth, $userAgent);
            $this->em->persist($existing);
        }
        $this->em->flush();

        return $existing;
    }

    public function unsubscribe(User $user, string $endpoint): void
    {
        $sub = $this->em->getRepository(PushSubscription::class)->findOneBy(['endpointHash' => hash('sha256', $endpoint), 'user' => $user]);
        if ($sub) {
            $this->em->remove($sub);
            $this->em->flush();
        }
    }

    /** @return int nombre d'appareils atteints */
    public function send(User $user, string $title, string $body, ?string $url = null, ?string $tag = null): int
    {
        if (!$this->isConfigured()) {
            return 0;
        }
        $subs = [];
        foreach ($this->em->getRepository(PushSubscription::class)->findBy(['user' => $user]) as $s) {
            // Contrôle renouvelé à chaque envoi : l'adresse d'un abonnement ancien ou altéré n'est jamais appelée (et il est supprimé)
            if ($this->endpoints->isAllowed($s->getEndpoint())) {
                $subs[] = $s;
            } else {
                $this->em->remove($s);
            }
        }
        $this->em->flush();
        if ([] === $subs) {
            return 0;
        }
        try {
            $push = new WebPush(['VAPID' => ['subject' => $this->subject ?: 'mailto:contact@kiinisawa.com', 'publicKey' => $this->publicKey, 'privateKey' => $this->privateKey]], ['TTL' => 6 * 3600]);
            $payload = json_encode(['title' => $title, 'body' => mb_strimwidth($body, 0, 180, '…'), 'url' => $url ?: '/dashboard', 'tag' => $tag], \JSON_UNESCAPED_UNICODE);
            $byEndpoint = [];
            foreach ($subs as $s) {
                $byEndpoint[$s->getEndpoint()] = $s;
                $push->queueNotification(Subscription::create(['endpoint' => $s->getEndpoint(), 'keys' => ['p256dh' => $s->getP256dh(), 'auth' => $s->getAuth()], 'contentEncoding' => 'aes128gcm']), $payload);
            }
            $sent = 0;
            foreach ($push->flush() as $report) {
                if ($report->isSuccess()) {
                    ++$sent;
                } elseif ($report->isSubscriptionExpired() && isset($byEndpoint[$report->getEndpoint()])) {
                    $this->em->remove($byEndpoint[$report->getEndpoint()]);
                }
            }
            $this->em->flush();

            return $sent;
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'envoi d\'une notification sur le téléphone', ['exception' => $e]);

            return 0;
        }
    }
}
