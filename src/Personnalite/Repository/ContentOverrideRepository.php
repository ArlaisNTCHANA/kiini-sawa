<?php

namespace App\Personnalite\Repository;

use App\Personnalite\Entity\ContentOverride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ContentOverride> */
class ContentOverrideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentOverride::class);
    }

    /** @return array<string, string> chemin => texte */
    public function map(): array
    {
        $rows = $this->createQueryBuilder('o')->select('o.path, o.content')->getQuery()->getArrayResult();

        return array_column($rows, 'content', 'path');
    }

    /** @return array<string, ContentOverride> */
    public function byPrefix(string $prefix): array
    {
        $out = [];
        foreach ($this->createQueryBuilder('o')->where('o.path LIKE :p')->setParameter('p', $prefix.'%')->getQuery()->getResult() as $o) {
            $out[$o->getPath()] = $o;
        }

        return $out;
    }
}
