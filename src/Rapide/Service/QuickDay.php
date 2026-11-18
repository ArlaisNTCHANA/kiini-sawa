<?php

namespace App\Rapide\Service;

use App\PlanDHygiene\Entity\DailyLog;
use App\Compte\Entity\User;
use App\Dashboard\Service\NextStep;
use App\Journal\Entity\ReflectionNote;
use App\Journal\Enum\ReflectionType;
use App\PlanDHygiene\Entity\PlanDHygiene;
use App\Rapide\Entity\SyncOperation;
use App\Rapide\Repository\SyncOperationRepository;
use App\Rituels\Entity\Habit;
use App\Rituels\Entity\HabitLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * « Aujourd'hui en un geste » : l'essentiel du quotidien (rituels, bilan du soir, journal) sous forme légère, pour fonctionner
 * hors connexion. `snapshot()` donne l'état du jour ; `apply()` reçoit les saisies faites hors ligne, une à une, sans jamais
 * échouer en bloc (chaque opération a son résultat) ni s'appliquer deux fois (identifiant créé par le téléphone).
 */
final class QuickDay
{
    public const MAX_OPERATIONS = 100;
    public const MAX_AGE_DAYS = 7;

    public const JOURNAL_TYPES = ['REFLECTION' => 'Pensée', 'IDEA' => 'Idée', 'RESOLUTION' => 'Résolution', 'MILESTONE' => 'Victoire', 'TEMOIGNAGE' => 'Témoignage', 'INSIGHT' => 'Déclic'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SyncOperationRepository $operations,
        private readonly NextStep $nextStep,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function snapshot(User $user, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $next = $this->nextStep->forUser($user, $now);

        $habits = [];
        foreach ($user->getHabits() as $habit) {
            if ($habit->getEndDate() && $habit->getEndDate() < new \DateTime('today')) {
                continue;
            }
            $habits[] = ['id' => $habit->getId(), 'title' => (string) $habit->getTitle(), 'frequency' => $habit->getFrequency()->value, 'done' => $habit->isCompletedForCurrentPeriod()];
        }
        usort($habits, fn ($a, $b) => [['DAILY' => 0, 'WEEKLY' => 1, 'MONTHLY' => 2][$a['frequency']] ?? 3, $a['title']] <=> [['DAILY' => 0, 'WEEKLY' => 1, 'MONTHLY' => 2][$b['frequency']] ?? 3, $b['title']]);

        $plan = $user->getPlanDHygiene();
        $log = $plan ? $this->em->getRepository(DailyLog::class)->findOneBy(['planDHygiene' => $plan, 'date' => new \DateTime($now->format('Y-m-d'))]) : null;

        $notes = $this->em->getRepository(ReflectionNote::class)->findBy(['user' => $user], ['date' => 'DESC', 'id' => 'DESC'], 5);

        return [
            'date' => $now->format('Y-m-d'),
            'name' => $user->getDisplayName(),
            'next' => ['title' => $next['title'], 'eyebrow' => $next['eyebrow'], 'text' => $next['text'], 'cta' => $next['cta'], 'url' => $this->urls->generate($next['route'], $next['params'] ?? [])],
            'habits' => $habits,
            'review' => $log ? ['intention' => $log->getIntention(), 'kept' => $log->isIntentionKept(), 'energy' => $log->getEnergyLevel(), 'mood' => $log->getMoodLevel(), 'stress' => $log->getStressLevel(), 'gratitude' => $log->getGratitude()] : null,
            'journal' => array_map(fn (ReflectionNote $n) => ['id' => $n->getId(), 'text' => mb_strimwidth((string) $n->getContent(), 0, 280, '…'), 'type' => $n->getType()->value, 'at' => $n->getDate()?->format('c')], $notes),
            'types' => self::JOURNAL_TYPES,
        ];
    }

    // ── Écriture ─────────────────────────────────────────────────────────────

    /**
     * @param list<array<string, mixed>> $ops chaque opération : uid, type (habit.log | review.save | journal.add), date (AAAA-MM-JJ du téléphone), + ses champs
     *
     * @return array<string, string> résultat par uid : « ok » (appliquée), « deja » (déjà reçue), « refusee » (invalide : à abandonner)
     */
    public function apply(User $user, array $ops, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $results = [];
        foreach (\array_slice($ops, 0, self::MAX_OPERATIONS) as $op) {
            $uid = \is_string($op['uid'] ?? null) ? $op['uid'] : '';
            $type = \is_string($op['type'] ?? null) ? $op['type'] : '';
            if (!preg_match('/^[A-Za-z0-9\-]{8,64}$/', $uid)) {
                continue;
            }
            if ($this->operations->seen($user, $uid)) {
                $results[$uid] = 'deja';
                continue;
            }
            $date = $this->day($op['date'] ?? null, $now);
            $applied = null !== $date && match ($type) {
                'habit.log' => $this->habitLog($user, $op, $date),
                'review.save' => $this->reviewSave($user, $op, $date),
                'journal.add' => $this->journalAdd($user, $op, $date, $now),
                default => false,
            };
            if ($applied) {
                $this->em->persist(new SyncOperation($user, $uid, $type));
                $this->em->flush();
            }
            $results[$uid] = $applied ? 'ok' : 'refusee';
        }

        return $results;
    }

    /** Le jour de la saisie, accepté s'il est dans les sept derniers jours (jamais dans le futur lointain : un téléphone mal réglé). */
    private function day(mixed $value, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        if (!\is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$day || $day->format('Y-m-d') !== $value) {
            return null;
        }
        $today = $now->setTime(0, 0);

        return $day >= $today->modify('-'.self::MAX_AGE_DAYS.' days') && $day <= $today->modify('+1 day') ? $day : null;
    }

    private function habitLog(User $user, array $op, \DateTimeImmutable $day): bool
    {
        $habit = $this->em->getRepository(Habit::class)->findOneBy(['id' => (int) ($op['habit'] ?? 0), 'user' => $user]);
        if (!$habit) {
            return false;
        }
        $status = filter_var($op['status'] ?? false, \FILTER_VALIDATE_BOOLEAN);
        foreach ($habit->getLogs() as $log) {
            if ($log->getDate()->format('Y-m-d') === $day->format('Y-m-d')) {
                $log->setStatus($status);

                return true;
            }
        }
        $log = (new HabitLog())->setDate(\DateTime::createFromImmutable($day))->setStatus($status);
        $habit->addLog($log);
        $this->em->persist($log);

        return true;
    }

    private function reviewSave(User $user, array $op, \DateTimeImmutable $day): bool
    {
        $plan = $user->getPlanDHygiene();
        if (!$plan) {
            $plan = (new PlanDHygiene())->setUser($user);
            $user->setPlanDHygiene($plan);
            $this->em->persist($plan);
        }
        $log = $this->em->getRepository(DailyLog::class)->findOneBy(['planDHygiene' => $plan, 'date' => \DateTime::createFromImmutable($day)]);
        if (!$log) {
            $log = (new DailyLog())->setPlanDHygiene($plan)->setDate(\DateTime::createFromImmutable($day));
            $this->em->persist($log);
        }
        $level = fn (string $k) => isset($op[$k]) && ($v = (int) $op[$k]) >= 1 && $v <= 5 ? $v : null;
        $intention = trim((string) ($op['intention'] ?? ''));
        if ('' !== $intention) {
            $log->setIntention(mb_substr($intention, 0, 255));
        }
        $log->setIntentionKept(\array_key_exists('kept', $op) && null !== $op['kept'] ? filter_var($op['kept'], \FILTER_VALIDATE_BOOLEAN) : null)
            ->setEnergyLevel($level('energy'))->setMoodLevel($level('mood'))->setStressLevel($level('stress'))
            ->setGratitude(mb_substr(trim((string) ($op['gratitude'] ?? '')), 0, 2000));

        return true;
    }

    private function journalAdd(User $user, array $op, \DateTimeImmutable $day, \DateTimeImmutable $now): bool
    {
        $content = trim((string) ($op['content'] ?? ''));
        if ('' === $content) {
            return false;
        }
        // L'heure exacte de la saisie si elle est plausible, sinon midi du jour indiqué
        $at = \DateTimeImmutable::createFromFormat(\DATE_ATOM, (string) ($op['at'] ?? ''));
        $when = $at && $at->format('Y-m-d') === $day->format('Y-m-d') && $at <= $now->modify('+1 day') ? $at : $day->setTime(12, 0);
        $note = (new ReflectionNote())->setUser($user)->setContent(mb_substr($content, 0, 5000))->setDate(\DateTime::createFromImmutable($when))
            ->setType(ReflectionType::tryFrom((string) ($op['entryType'] ?? '')) ?? ReflectionType::REFLECTION);
        $this->em->persist($note);

        return true;
    }
}
