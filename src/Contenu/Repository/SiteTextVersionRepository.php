<?php

namespace App\Contenu\Repository;

use App\Contenu\Entity\SiteTextVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SiteTextVersion> */
class SiteTextVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteTextVersion::class);
    }

    /** @return list<SiteTextVersion> */
    public function history(string $code, int $limit = 20): array
    {
        return $this->findBy(['code' => $code], ['id' => 'DESC'], $limit);
    }
}
