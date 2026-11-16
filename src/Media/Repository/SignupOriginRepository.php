<?php

namespace App\Media\Repository;

use App\Abonnement\Entity\Transaction;
use App\Media\Entity\SignupOrigin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SignupOrigin> */
class SignupOriginRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SignupOrigin::class);
    }

    /**
     * Inscrits, comptes vérifiés, onboarding terminé et payants, regroupés par canal ou par campagne.
     *
     * @param 'source'|'campaign' $by
     *
     * @return list<array{label: string, signups: int, verified: int, onboarded: int, paying: int}>
     */
    public function funnel(string $by, ?\DateTimeImmutable $since): array
    {
        $field = 'campaign' === $by ? "COALESCE(o.campaign, '(sans campagne)')" : 'o.source';

        $qb = $this->createQueryBuilder('o')
            ->select($field.' AS label', 'COUNT(o.id) AS signups',
                'SUM(CASE WHEN u.isVerified = true THEN 1 ELSE 0 END) AS verified',
                'SUM(CASE WHEN u.isOnboardingCompleted = true THEN 1 ELSE 0 END) AS onboarded')
            ->join('o.user', 'u')
            ->groupBy('label')
            ->orderBy('signups', 'DESC');
        // Payants : au moins un paiement validé
        $paying = $this->createQueryBuilder('o')
            ->select($field.' AS label', 'COUNT(DISTINCT u.id) AS paying')
            ->join('o.user', 'u')->join('u.subscription', 's')->join('s.transactions', 't')
            ->where('t.status = :validated')->andWhere('t.amount > 0')->setParameter('validated', Transaction::STATUS_VALIDATED)
            ->groupBy('label');
        if ($since) {
            $qb->where('o.createdAt >= :since')->setParameter('since', $since);
            $paying->andWhere('o.createdAt >= :since')->setParameter('since', $since);
        }
        $payingByLabel = array_column($paying->getQuery()->getArrayResult(), 'paying', 'label');

        return array_map(fn (array $r) => [
            'label' => (string) $r['label'], 'signups' => (int) $r['signups'], 'verified' => (int) $r['verified'],
            'onboarded' => (int) $r['onboarded'], 'paying' => (int) ($payingByLabel[$r['label']] ?? 0),
        ], $qb->getQuery()->getArrayResult());
    }

    /** @return list<SignupOrigin> */
    public function latest(int $limit = 20): array
    {
        return $this->createQueryBuilder('o')->join('o.user', 'u')->addSelect('u')->orderBy('o.createdAt', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
    }
}
