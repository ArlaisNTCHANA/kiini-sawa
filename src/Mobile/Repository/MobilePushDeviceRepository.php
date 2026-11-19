<?php

namespace App\Mobile\Repository;

use App\Compte\Entity\User;
use App\Mobile\Entity\MobilePushDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MobilePushDevice>
 */
class MobilePushDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MobilePushDevice::class);
    }

    /**
     * @return list<MobilePushDevice>
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['lastSeenAt' => 'DESC']);
    }

    public function countForUser(User $user): int
    {
        return (int) $this->count(['user' => $user]);
    }

    public function findOneByToken(string $expoToken): ?MobilePushDevice
    {
        return $this->findOneBy(['tokenHash' => hash('sha256', trim($expoToken))]);
    }

    /**
     * @param list<string> $expoTokens
     */
    public function deleteByTokens(array $expoTokens): int
    {
        if ([] === $expoTokens) {
            return 0;
        }

        $hashes = array_map(fn (string $token): string => hash('sha256', trim($token)), $expoTokens);

        return (int) $this->createQueryBuilder('d')
            ->delete()
            ->where('d.tokenHash IN (:hashes)')
            ->setParameter('hashes', $hashes)
            ->getQuery()
            ->execute();
    }
}
