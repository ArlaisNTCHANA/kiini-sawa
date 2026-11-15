<?php

namespace App\Notification\Repository;

use App\Compte\Entity\User;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countUnread(User $user): int
    {
        return (int) $this->count(['user' => $user, 'read' => false]);
    }

    /** @return Notification[] */
    public function findRecent(User $user, int $limit = 50): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    public function markAllRead(User $user): void
    {
        $this->createQueryBuilder('n')->update()->set('n.read', 'true')
            ->where('n.user = :user')->andWhere('n.read = false')
            ->setParameter('user', $user)->getQuery()->execute();
    }

    /** Une notification portant cette clé (ex. « semaine 2026-W46 ») existe-t-elle déjà ? Évite les doublons de rappels. */
    public function existsWithKey(User $user, NotificationType $type, string $key): bool
    {
        return null !== $this->findOneBy(['user' => $user, 'type' => $type, 'metadata' => $key]);
    }

    public function lastOfType(User $user, NotificationType $type): ?Notification
    {
        return $this->findOneBy(['user' => $user, 'type' => $type], ['createdAt' => 'DESC']);
    }
}
