<?php

namespace App\Notification\Service;

use App\Abonnement\Service\Entitlements;
use App\Academie\Entity\Course;
use App\Academie\Entity\CourseEnrollment;
use App\Bilan\Entity\WeeklyReview;
use App\Boussole\Entity\BoussoleResult;
use App\Compte\Entity\User;
use App\Contenu\Service\SiteTexts;
use App\Defis\Entity\Challenge;
use App\Defis\Entity\ChallengeParticipant;
use App\Feedback\Entity\FeedbackRequest;
use App\Journal\Entity\ReflectionNote;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationType;
use App\Personnalite\Entity\Assessment;
use App\Social\Entity\AccountabilityCommitment;
use App\Social\Entity\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Messages « Saviez-vous que… » (TipCatalog), envoyés par app:reminders:send après tous les autres rappels :
 *
 * - seulement après les deux semaines d'accueil (à partir du 15e jour), puis au plus un message toutes les deux semaines ;
 * - seulement une fonction que le membre n'a jamais utilisée (vérifié dans ses données), et jamais deux fois le même ;
 * - jamais du 1er au 3 du mois (revue mensuelle), ni dans les 7 jours d'un anniversaire ou d'un rappel de revue
 *   mensuelle, ni dans les 2 jours d'un autre e-mail important (accueil, revue de la semaine, fin d'essai) ;
 * - plus rien après 60 jours sans connexion : on ne relance pas quelqu'un qui est parti ;
 * - membre gratuit : au plus un message sur trois présente une fonction PRO (et le dit clairement).
 *
 * Désactivable par le membre (préférence « Saviez-vous que… », lien en bas de chaque e-mail) ; chaque message
 * s'active ou se désactive dans Admin › Réglages › Réglages de l'application.
 */
final class TipSender
{
    public const START_DAY = 15;
    public const INTERVAL_DAYS = 14;
    public const INACTIVE_DAYS = 60;
    public const BATCH_SIZE = 200;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Notifier $notifier,
        private readonly Entitlements $entitlements,
        private readonly SiteTexts $texts,
    ) {
    }

    /** @return int nombre de messages envoyés */
    public function sendAll(\DateTimeImmutable $today): int
    {
        $today = $today->setTime(0, 0);
        $createdBefore = $today->modify('-'.(self::START_DAY - 1).' days');
        $activeSince = $today->modify('-'.self::INACTIVE_DAYS.' days');
        $sent = 0;
        $lastId = 0;
        do {
            // Par lots d'identifiants : la mémoire reste constante, quel que soit le nombre de membres
            $ids = $this->em->createQueryBuilder()->select('u.id')->from(User::class, 'u')
                ->where('u.isVerified = true')->andWhere('u.id > :last')->andWhere('u.createdAt < :created')
                ->andWhere('u.lastActiveAt >= :active OR (u.lastActiveAt IS NULL AND u.createdAt >= :active)')
                ->setParameter('last', $lastId)->setParameter('created', $createdBefore)->setParameter('active', $activeSince)
                ->orderBy('u.id', 'ASC')->setMaxResults(self::BATCH_SIZE)->getQuery()->getSingleColumnResult();
            if ([] === $ids) {
                break;
            }
            foreach ($this->em->getRepository(User::class)->findBy(['id' => $ids], ['id' => 'ASC']) as $user) {
                $sent += (int) $this->sendFor($user, $today);
            }
            $lastId = (int) max($ids);
            $this->em->clear();
        } while (self::BATCH_SIZE === \count($ids));

        return $sent;
    }

    public function sendFor(User $user, \DateTimeImmutable $today): bool
    {
        $code = $this->nextTip($user, $today);
        if (null === $code) {
            return false;
        }
        [$url, $feature] = TipCatalog::TIPS[$code];
        $pro = null !== $feature && !$this->entitlements->can($user, $feature);
        $title = TipCatalog::text($this->texts, 'astuce.'.$code.'.titre');
        $paragraphs = array_values(array_filter(array_map('trim', explode("\n", TipCatalog::text($this->texts, 'astuce.'.$code.'.texte')))));

        return $this->notifier->notify($user, NotificationType::TIP, $title, implode(' ', $paragraphs), $url, 'astuce '.$code, [
            'template' => 'notification/email/tip.html.twig',
            'context' => [
                'paragraphs' => $paragraphs,
                'actionLabel' => TipCatalog::text($this->texts, 'astuce.'.$code.'.bouton'),
                'proNote' => $pro ? TipCatalog::text($this->texts, 'astuce.pro') : null,
                'footerText' => TipCatalog::text($this->texts, 'astuce.pied'),
                'unsubscribeLabel' => TipCatalog::text($this->texts, 'astuce.desabonnement'),
            ],
        ]);
    }

    /** Le message à envoyer aujourd'hui, ou null (trop tôt, trop proche d'un autre e-mail, rien à présenter). */
    public function nextTip(User $user, \DateTimeImmutable $today): ?string
    {
        $today = $today->setTime(0, 0);
        if (!$user->isVerified() || \in_array('ROLE_ADMIN', $user->getRoles(), true) || !$this->notifier->preferences($user)->isNotifyTips()) {
            return null;
        }
        $created = $user->getCreatedAt();
        if (null === $created || $created->setTime(0, 0)->diff($today)->days < self::START_DAY - 1 || $created > $today) {
            return null;
        }
        $lastActive = $user->getLastActiveAt() ?? $created;
        if ($lastActive < $today->modify('-'.self::INACTIVE_DAYS.' days')) {
            return null;
        }
        if ((int) $today->format('j') <= 3 || !$this->isQuiet($user, $today)) {
            return null;
        }

        $sentCodes = $this->sentCodes($user);
        $disabled = $this->entitlements->config()->getDisabledTips();
        $free = null;
        $pro = null;
        foreach (array_keys(TipCatalog::TIPS) as $code) {
            if (\in_array($code, $sentCodes, true) || \in_array($code, $disabled, true) || $this->hasUsed($user, $code) || !$this->isAvailable($code, $today)) {
                continue;
            }
            if ($this->isPro($user, $code)) {
                $pro ??= $code;
            } else {
                $free ??= $code;
            }
        }
        if (null === $pro) {
            return $free;
        }
        // Membre gratuit : au plus un message sur trois pour une fonction PRO (en comptant celui d'aujourd'hui).
        // S'il ne reste plus rien de gratuit à présenter, un message PRO reste possible, mais jamais deux de suite.
        $proSent = \count(array_filter($sentCodes, fn (string $c) => $this->isPro($user, $c)));
        if (($proSent + 1) * 3 <= \count($sentCodes) + 1) {
            return null === $free || array_search($pro, array_keys(TipCatalog::TIPS), true) < array_search($free, array_keys(TipCatalog::TIPS), true) ? $pro : $free;
        }
        if (null !== $free) {
            return $free;
        }
        $last = $this->lastSentCode($user);

        return null !== $last && $this->isPro($user, $last) ? null : $pro;
    }

    private function lastSentCode(User $user): ?string
    {
        $last = $this->em->getRepository(Notification::class)->findOneBy(['user' => $user, 'type' => NotificationType::TIP], ['createdAt' => 'DESC', 'id' => 'DESC']);

        return $last ? preg_replace('/^astuce /', '', (string) $last->getMetadata()) : null;
    }

    /** Assez d'espace depuis le dernier « Saviez-vous » et les autres e-mails importants. */
    private function isQuiet(User $user, \DateTimeImmutable $today): bool
    {
        $rules = [
            [[NotificationType::TIP], self::INTERVAL_DAYS],
            [[NotificationType::BIRTHDAY, NotificationType::MONTHLY_REVIEW_REMINDER], 7],
            [[NotificationType::ONBOARDING, NotificationType::WEEKLY_REVIEW_REMINDER, NotificationType::TRIAL_ENDING, NotificationType::SUBSCRIPTION_ENDING], 2],
        ];
        foreach ($rules as [$types, $days]) {
            $recent = (int) $this->em->createQueryBuilder()->select('COUNT(n.id)')->from(Notification::class, 'n')
                ->where('n.user = :user')->andWhere('n.type IN (:types)')->andWhere('n.createdAt > :since')
                ->setParameter('user', $user)->setParameter('types', array_map(fn (NotificationType $t) => $t->value, $types))
                ->setParameter('since', \DateTime::createFromImmutable($today->modify('-'.($days - 1).' days')))
                ->getQuery()->getSingleScalarResult();
            if ($recent > 0) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> codes des messages déjà envoyés à ce membre */
    private function sentCodes(User $user): array
    {
        $keys = $this->em->createQueryBuilder()->select('n.metadata')->from(Notification::class, 'n')
            ->where('n.user = :user')->andWhere('n.type = :type')
            ->setParameter('user', $user)->setParameter('type', NotificationType::TIP->value)
            ->getQuery()->getSingleColumnResult();

        return array_values(array_unique(array_map(fn (?string $k) => preg_replace('/^astuce /', '', (string) $k), $keys)));
    }

    private function isPro(User $user, string $code): bool
    {
        $feature = TipCatalog::TIPS[$code][1] ?? null;

        return null !== $feature && !$this->entitlements->can($user, $feature);
    }

    /** La fonction a-t-elle déjà été utilisée par ce membre ? */
    public function hasUsed(User $user, string $code): bool
    {
        $count = fn (string $class, array $criteria): int => $this->em->getRepository($class)->count($criteria);

        return match ($code) {
            'revue' => $count(WeeklyReview::class, ['user' => $user]) > 0,
            'test' => $count(Assessment::class, ['user' => $user]) > 0,
            'boussole' => $count(BoussoleResult::class, ['user' => $user]) > 0,
            'feedback' => $count(FeedbackRequest::class, ['user' => $user]) > 0,
            'partenaire' => $count(Connection::class, ['requester' => $user]) + $count(Connection::class, ['receiver' => $user])
                + $count(AccountabilityCommitment::class, ['owner' => $user]) > 0,
            'journal' => $count(ReflectionNote::class, ['user' => $user]) > 0,
            'academie' => $count(CourseEnrollment::class, ['user' => $user]) > 0,
            'defis' => $count(ChallengeParticipant::class, ['user' => $user]) > 0,
            default => true,
        };
    }

    /** Y a-t-il de quoi présenter ? (un parcours publié, un défi ouvert) */
    private function isAvailable(string $code, \DateTimeImmutable $today): bool
    {
        return match ($code) {
            'academie' => $this->em->getRepository(Course::class)->count(['status' => Course::STATUS_PUBLISHED]) > 0,
            'defis' => (int) $this->em->createQueryBuilder()->select('COUNT(c.id)')->from(Challenge::class, 'c')
                ->where('c.published = true')->andWhere('c.slug IS NOT NULL')->andWhere('c.endDate >= :today')
                ->setParameter('today', \DateTime::createFromImmutable($today))->getQuery()->getSingleScalarResult() > 0,
            default => true,
        };
    }
}
