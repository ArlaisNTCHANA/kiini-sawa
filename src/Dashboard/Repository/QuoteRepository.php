<?php

namespace App\Dashboard\Repository;

use App\Dashboard\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    /**
     * Citation du jour : celle programmée à cette date dans l'administration,
     * sinon une rotation stable sur les citations actives (la même toute la journée).
     */
    public function findForDate(\DateTimeInterface $date): ?Quote
    {
        $start = \DateTime::createFromInterface($date)->setTime(0, 0);
        $scheduled = $this->createQueryBuilder('q')
            ->where('q.isActive = true')
            ->andWhere('q.displayDate >= :start AND q.displayDate < :end')
            ->setParameter('start', $start)
            ->setParameter('end', (clone $start)->modify('+1 day'))
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
        if ($scheduled) {
            return $scheduled;
        }

        $ids = $this->createQueryBuilder('q')->select('q.id')
            ->where('q.isActive = true')->andWhere('q.displayDate IS NULL')
            ->orderBy('q.id')->getQuery()->getSingleColumnResult();
        if ([] === $ids) {
            return null;
        }

        return $this->find($ids[((int) $date->format('z') + 365 * (int) $date->format('Y')) % \count($ids)]);
    }
}
