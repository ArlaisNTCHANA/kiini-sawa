<?php

namespace App\Mobile\Service;

use App\Compte\Entity\User;
use App\Mobile\Entity\MobilePushDevice;
use App\Mobile\Repository\MobilePushDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Envoi de notifications push natives via le service Expo Push API.
 * Gère le plafond d'appareils par compte et la suppression automatique des appareils désinscrits.
 */
class ExpoPushSender
{
    public const MAX_DEVICES = 10;
    public const TOKEN_REGEX = '/^Expo(nent)?PushToken\[[A-Za-z0-9\-_]+\]$/';
    public const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * Transport injectable pour les tests unitaires / fonctionnels.
     * Signature : fn(array $messages, User $user): ?array
     *
     * @var (\Closure(list<array<string, mixed>>, User): ?array)|null
     */
    public static ?\Closure $testTransport = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MobilePushDeviceRepository $devices,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.environment%')] private readonly string $appEnv,
    ) {
    }

    public function isValidToken(string $token): bool
    {
        $trimmed = trim($token);

        return '' !== $trimmed && \strlen($trimmed) <= 255 && 1 === preg_match(self::TOKEN_REGEX, $trimmed);
    }

    public function hasDevice(User $user): bool
    {
        return $this->devices->countForUser($user) > 0;
    }

    /**
     * @throws \InvalidArgumentException jeton invalide ou plafond dépassé
     */
    public function register(User $user, string $expoToken, string $platform = 'unknown', ?string $deviceName = null): MobilePushDevice
    {
        $token = trim($expoToken);
        if (!$this->isValidToken($token)) {
            throw new \InvalidArgumentException('Format de jeton Expo Push invalide.');
        }

        $platform = in_array($platform, ['ios', 'android', 'unknown'], true) ? $platform : 'unknown';
        $existing = $this->devices->findOneByToken($token);

        if (null !== $existing) {
            $existing->setUser($user)
                ->setPlatform($platform)
                ->setDeviceName($deviceName)
                ->touch();
            $this->em->flush();

            return $existing;
        }

        if ($this->devices->countForUser($user) >= self::MAX_DEVICES) {
            throw new \InvalidArgumentException('Nombre maximal d\'appareils atteint.');
        }

        $device = new MobilePushDevice($user, $token, $platform, $deviceName);
        $this->em->persist($device);
        $this->em->flush();

        return $device;
    }

    public function unregister(User $user, string $expoToken): void
    {
        $device = $this->devices->findOneByToken($expoToken);
        if (null !== $device && $device->getUser()->getId() === $user->getId()) {
            $this->em->remove($device);
            $this->em->flush();
        }
    }

    /**
     * Envoie la notification à tous les appareils Expo enregistrés pour cet utilisateur.
     *
     * @return int nombre de notifications transmises avec succès
     */
    public function send(User $user, string $title, string $body, ?string $url = null, ?string $tag = null): int
    {
        $devices = $this->devices->findByUser($user);
        if ([] === $devices) {
            return 0;
        }

        $messages = [];
        $devicesByToken = [];
        foreach ($devices as $device) {
            $token = $device->getExpoToken();
            $devicesByToken[$token] = $device;
            $messages[] = [
                'to' => $token,
                'title' => $title,
                'body' => mb_strimwidth($body, 0, 180, '…'),
                'sound' => 'default',
                'data' => [
                    'url' => $url ?: '/dashboard',
                    'tag' => $tag,
                ],
            ];
        }

        if (null !== self::$testTransport) {
            $simulated = (self::$testTransport)($messages, $user);
            if (\is_array($simulated)) {
                return $this->processTickets($simulated, $devicesByToken);
            }

            return \count($messages);
        }

        if ('test' === $this->appEnv) {
            return \count($messages);
        }

        return $this->sendHttpBatch($messages, $devicesByToken);
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @param array<string, MobilePushDevice> $devicesByToken
     */
    private function sendHttpBatch(array $messages, array $devicesByToken): int
    {
        $payload = json_encode($messages, \JSON_UNESCAPED_UNICODE);
        if (false === $payload) {
            return 0;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload,
                'timeout' => 5.0,
                'ignore_errors' => true,
            ],
        ]);

        try {
            $raw = @file_get_contents(self::EXPO_PUSH_URL, false, $context);
            if (false === $raw || '' === $raw) {
                return 0;
            }

            $response = json_decode($raw, true);
            if (!\is_array($response)) {
                return 0;
            }

            $tickets = $response['data'] ?? [];
            if (!\is_array($tickets)) {
                return 0;
            }

            return $this->processTickets($tickets, $devicesByToken);
        } catch (\Throwable $e) {
            $this->logger->error('Échec d\'envoi Expo Push', ['exception' => $e]);

            return 0;
        }
    }

    /**
     * @param list<array<string, mixed>> $tickets
     * @param array<string, MobilePushDevice> $devicesByToken
     */
    private function processTickets(array $tickets, array $devicesByToken): int
    {
        $tokens = array_keys($devicesByToken);
        $success = 0;
        $pruned = false;

        foreach ($tickets as $idx => $ticket) {
            $token = $tokens[$idx] ?? null;
            if (null === $token || !isset($devicesByToken[$token])) {
                continue;
            }

            $status = $ticket['status'] ?? null;
            if ('ok' === $status) {
                ++$success;
                $devicesByToken[$token]->touch();
            } elseif ('error' === $status) {
                $errorCode = $ticket['details']['error'] ?? null;
                if ('DeviceNotRegistered' === $errorCode) {
                    $this->em->remove($devicesByToken[$token]);
                    $pruned = true;
                }
            }
        }

        if ($success > 0 || $pruned) {
            $this->em->flush();
        }

        return $success;
    }
}
