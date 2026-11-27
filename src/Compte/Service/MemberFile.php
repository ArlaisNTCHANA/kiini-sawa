<?php

namespace App\Compte\Service;

use App\Abonnement\Entity\Transaction;
use App\Abonnement\Service\Entitlements;
use App\Academie\Entity\CourseEnrollment;
use App\Boussole\Entity\BoussoleResult;
use App\Compte\Entity\User;
use App\Couple\Entity\Couple;
use App\Media\Entity\SignupOrigin;
use App\Organisation\Entity\Membership;
use App\Personnalite\Entity\ProfileTest;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\Rituels\Entity\HabitLog;
use App\Social\Entity\AccountabilityCommitment;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fiche d'un membre pour l'administration (27/11/2026) : de quoi l'aider, sans lire sa vie.
 *
 * Seulement des faits de compte (offre, paiements, organisation, couple, provenance) et des **compteurs** d'usage.
 * Jamais le texte de ses plans, de son journal, de ses notes, ni les résultats de ses tests : la confidentialité promise
 * aux membres (« ses réflexions sont privées ») vaut aussi devant l'administrateur.
 */
final class MemberFile
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Entitlements $entitlements,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $count = fn (string $class, string $field = 'user', array $extra = []) => (int) $this->em->getRepository($class)->count([$field => $user] + $extra);

        $plans = [
            'Plan de Vie' => null !== $user->getPlanDeVie(),
            'Plan de Croissance' => null !== $user->getPlanDeCroissance(),
            'Plan de Développement personnel' => null !== $user->getPlanDeDeveloppementPersonnel(),
            'Plan d\'Hygiène de vie' => null !== $user->getPlanDHygiene(),
            'Plan d\'Intendance' => $count(StewardshipItem::class) > 0,
            'Plan de Transmission' => null !== $user->getPlanDeTransmission(),
        ];

        $lastTick = $this->em->createQueryBuilder()->select('MAX(l.date)')->from(HabitLog::class, 'l')
            ->join('l.habit', 'h')->where('h.user = :u')->andWhere('l.status = true')->setParameter('u', $user)
            ->getQuery()->getSingleScalarResult();

        $transactions = $this->em->createQueryBuilder()->select('t')->from(Transaction::class, 't')
            ->join('t.subscription', 's')->where('s.user = :u')->setParameter('u', $user)
            ->orderBy('t.createdAt', 'DESC')->setMaxResults(20)->getQuery()->getResult();

        $tests = [];
        foreach ($this->em->getRepository(ProfileTest::class)->findBy(['user' => $user], ['date' => 'DESC']) as $t) {
            $tests[$t->getType()] ??= $t->getDate(); // le plus récent de chaque test, sans son résultat
        }

        $courses = [];
        foreach ($this->em->getRepository(CourseEnrollment::class)->findBy(['user' => $user], ['lastActivityAt' => 'DESC']) as $e) {
            $courses[] = [
                'title' => $e->getCourse()->getTitle(),
                'done' => \count($e->getCompletions()),
                'total' => \count($e->getCourse()->getLessons(true)),
                'completed' => $e->getCompletedAt(),
                'last' => $e->getLastActivityAt(),
            ];
        }

        $couple = $this->em->createQueryBuilder()->select('c')->from(Couple::class, 'c')
            ->where('c.partnerA = :u OR c.partnerB = :u')->setParameter('u', $user)->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return [
            'isPro' => $this->entitlements->isPro($user),
            'trialing' => $this->entitlements->isTrialing($user),
            'until' => $this->entitlements->proUntil($user),
            'isAdmin' => \in_array('ROLE_ADMIN', $user->getRoles(), true),
            'transactions' => $transactions,
            'memberships' => $this->em->getRepository(Membership::class)->findBy(['user' => $user]),
            'couple' => $couple instanceof Couple ? [
                'with' => $couple->getPartnerA() === $user ? $couple->getPartnerB() : $couple->getPartnerA(),
                'invited' => $couple->getInvitedEmail(),
                'since' => $couple->getJoinedAt() ?? $couple->getCreatedAt(),
            ] : null,
            'origin' => $this->em->getRepository(SignupOrigin::class)->findOneBy(['user' => $user]),
            'plans' => $plans,
            'plansStarted' => \count(array_filter($plans)),
            'habits' => \count($user->getHabits()),
            'lastTick' => $lastTick ? new \DateTimeImmutable((string) $lastTick) : null,
            'notes' => \count($user->getReflectionNotes()),
            'tests' => $tests,
            'boussole' => $count(BoussoleResult::class),
            'courses' => $courses,
            'partners' => $count(AccountabilityCommitment::class, 'owner'),
            'partnerOf' => $count(AccountabilityCommitment::class, 'partnerUser'),
        ];
    }
}
