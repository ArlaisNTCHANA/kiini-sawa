<?php

namespace App\Defis\Service;

use App\Compte\Entity\User;
use App\Defis\Entity\Challenge;
use App\Defis\Entity\ChallengeLog;
use App\Defis\Entity\ChallengeParticipant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Défis collectifs (ex. « 21 jours de discipline ») : inscription, jour en cours, validation quotidienne,
 * progression et classement. Une validation par jour, uniquement le jour même, pendant la durée du défi.
 */
final class ChallengeService
{
    /** Part des jours à valider pour que le défi soit « relevé ». */
    public const SUCCESS_RATIO = 0.8;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findPublished(string $slug): ?Challenge
    {
        return $this->em->getRepository(Challenge::class)->findOneBy(['slug' => $slug, 'published' => true]);
    }

    /** @return list<Challenge> défis publiés en cours ou à venir, du plus proche au plus lointain */
    public function openChallenges(\DateTimeImmutable $today): array
    {
        return $this->em->getRepository(Challenge::class)->createQueryBuilder('c')
            ->where('c.published = true')->andWhere('c.slug IS NOT NULL')->andWhere('c.endDate >= :today')
            ->setParameter('today', $today->setTime(0, 0))->orderBy('c.startDate', 'ASC')->getQuery()->getResult();
    }

    public function participation(Challenge $challenge, User $user): ?ChallengeParticipant
    {
        return $this->em->getRepository(ChallengeParticipant::class)->findOneBy(['challenge' => $challenge, 'user' => $user]);
    }

    /** @return list<ChallengeParticipant> participations aux défis publics en cours ou à venir */
    public function activeParticipations(User $user, \DateTimeImmutable $today): array
    {
        return $this->em->getRepository(ChallengeParticipant::class)->createQueryBuilder('p')
            ->join('p.challenge', 'c')->addSelect('c')
            ->where('p.user = :u')->andWhere('c.slug IS NOT NULL')->andWhere('c.endDate >= :today')
            ->setParameter('u', $user)->setParameter('today', $today->setTime(0, 0))
            ->orderBy('c.startDate', 'ASC')->getQuery()->getResult();
    }

    public function join(Challenge $challenge, User $user): ChallengeParticipant
    {
        if ($existing = $this->participation($challenge, $user)) {
            return $existing;
        }
        $p = (new ChallengeParticipant())->setChallenge($challenge)->setUser($user)->setStatus('ACTIVE')->setProgress(0);
        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    /** Jour du défi (1 = premier jour) ; 0 avant le début ; > durée après la fin. */
    public function dayNumber(Challenge $challenge, \DateTimeImmutable $today): int
    {
        $start = \DateTimeImmutable::createFromInterface($challenge->getStartDate())->setTime(0, 0);

        return (int) $start->diff($today->setTime(0, 0))->format('%r%a') + 1;
    }

    /** @return 'upcoming'|'running'|'ended' */
    public function phase(Challenge $challenge, \DateTimeImmutable $today): string
    {
        $day = $this->dayNumber($challenge, $today);

        return $day < 1 ? 'upcoming' : ($day > $challenge->getDurationDays() ? 'ended' : 'running');
    }

    public function hasCheckedIn(ChallengeParticipant $p, \DateTimeImmutable $day): bool
    {
        return null !== $this->logFor($p, $day);
    }

    /** Valide la journée ; refusé hors de la période du défi. */
    public function checkIn(ChallengeParticipant $p, \DateTimeImmutable $today): bool
    {
        if ('running' !== $this->phase($p->getChallenge(), $today) || $this->hasCheckedIn($p, $today)) {
            return false;
        }
        $this->em->persist((new ChallengeLog())->setParticipant($p)->setDate(\DateTime::createFromImmutable($today->setTime(12, 0)))->setStatus(true));
        $p->setProgress($p->getProgress() + 1);
        if ($this->isSuccessful($p)) {
            $p->setStatus('COMPLETED');
        }
        $this->em->flush();

        return true;
    }

    public function successThreshold(Challenge $challenge): int
    {
        return (int) ceil($challenge->getDurationDays() * self::SUCCESS_RATIO);
    }

    public function isSuccessful(ChallengeParticipant $p): bool
    {
        return $p->getProgress() >= $this->successThreshold($p->getChallenge());
    }

    /** Série en cours : jours validés d'affilée jusqu'à aujourd'hui (ou hier si aujourd'hui n'est pas encore validé). */
    public function streak(ChallengeParticipant $p, \DateTimeImmutable $today): int
    {
        $days = [];
        $dates = $this->em->getRepository(ChallengeLog::class)->createQueryBuilder('l')->select('l.date')
            ->where('l.participant = :p')->andWhere('l.status = true')->setParameter('p', $p)->getQuery()->getArrayResult();
        foreach ($dates as $row) {
            $days[$row['date']->format('Y-m-d')] = true;
        }
        $cursor = $today->setTime(0, 0);
        if (!isset($days[$cursor->format('Y-m-d')])) {
            $cursor = $cursor->modify('-1 day');
        }
        $n = 0;
        while (isset($days[$cursor->format('Y-m-d')])) {
            ++$n;
            $cursor = $cursor->modify('-1 day');
        }

        return $n;
    }

    /** @return array{participants: int, checkedToday: int, successful: int} */
    public function stats(Challenge $challenge, \DateTimeImmutable $today): array
    {
        $participants = $this->em->getRepository(ChallengeParticipant::class)->count(['challenge' => $challenge]);
        $checkedToday = (int) $this->em->getRepository(ChallengeLog::class)->createQueryBuilder('l')
            ->select('COUNT(l.id)')->join('l.participant', 'p')
            ->where('p.challenge = :c')->andWhere('l.date BETWEEN :from AND :to')
            ->setParameter('c', $challenge)->setParameter('from', $today->setTime(0, 0))->setParameter('to', $today->setTime(23, 59, 59))
            ->getQuery()->getSingleScalarResult();
        $successful = (int) $this->em->getRepository(ChallengeParticipant::class)->createQueryBuilder('p')
            ->select('COUNT(p.id)')->where('p.challenge = :c')->andWhere('p.progress >= :t')
            ->setParameter('c', $challenge)->setParameter('t', max(1, $this->successThreshold($challenge)))
            ->getQuery()->getSingleScalarResult();

        return ['participants' => $participants, 'checkedToday' => $checkedToday, 'successful' => $successful];
    }

    /**
     * Classement : jours validés, puis ancienneté d'inscription. Prénom et initiale du nom seulement.
     *
     * @return list<array{name: string, progress: int, me: bool}>
     */
    public function leaderboard(Challenge $challenge, ?User $me, int $limit = 10): array
    {
        $rows = $this->em->getRepository(ChallengeParticipant::class)->createQueryBuilder('p')
            ->join('p.user', 'u')->addSelect('u')
            ->where('p.challenge = :c')->setParameter('c', $challenge)
            ->orderBy('p.progress', 'DESC')->addOrderBy('p.joinedAt', 'ASC')->setMaxResults($limit)
            ->getQuery()->getResult();

        return array_map(fn (ChallengeParticipant $p) => [
            'name' => self::publicName($p->getUser()),
            'progress' => $p->getProgress(),
            'me' => null !== $me && $p->getUser() === $me,
        ], $rows);
    }

    public static function publicName(User $u): string
    {
        $profile = $u->getUserProfile();
        $first = trim((string) $profile?->getFirstName()) ?: 'Participant';
        $last = trim((string) $profile?->getLastName());

        return $first.('' !== $last ? ' '.mb_strtoupper(mb_substr($last, 0, 1)).'.' : '');
    }

    private function logFor(ChallengeParticipant $p, \DateTimeImmutable $day): ?ChallengeLog
    {
        return $this->em->getRepository(ChallengeLog::class)->createQueryBuilder('l')
            ->where('l.participant = :p')->andWhere('l.date BETWEEN :from AND :to')
            ->setParameter('p', $p)->setParameter('from', $day->setTime(0, 0))->setParameter('to', $day->setTime(23, 59, 59))
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
}
