<?php

namespace App\Couple\Service;

use App\Compte\Entity\User;
use App\Couple\Entity\Couple;
use App\Couple\Entity\CoupleChallenge;
use App\Couple\Entity\CoupleItem;
use App\Couple\Entity\CoupleMeditation;
use App\Couple\Entity\CoupleRetreat;
use App\Couple\Entity\CoupleReview;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier du Sanctuaire du Couple 360 (RFC-001) :
 * vue globale, défis partagés, parcours de méditation et signaux de connexion.
 */
final class CoupleSanctuaryService
{
    /** Message affiché quand le jour (ou l'étape) suivant n'est pas encore ouvert : la régularité, pas la vitesse. */
    public const RHYTHM_MESSAGE = 'La suite s\'ouvre demain. Ici, c\'est la régularité qui fait grandir, pas la vitesse.';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Notifier $notifier,
    ) {
    }

    public function activeChallenge(Couple $couple): ?CoupleChallenge
    {
        return $this->em->getRepository(CoupleChallenge::class)->findOneBy(
            ['couple' => $couple, 'status' => CoupleChallenge::STATUS_ACTIVE],
            ['startedAt' => 'DESC']
        );
    }

    public function activeMeditation(Couple $couple): ?CoupleMeditation
    {
        return $this->em->getRepository(CoupleMeditation::class)->findOneBy(
            ['couple' => $couple, 'status' => CoupleMeditation::STATUS_IN_PROGRESS],
            ['startedAt' => 'DESC']
        );
    }

    /** @return array<string, mixed> */
    public function homeData(Couple $couple, User $user): array
    {
        $partner = $couple->partnerOf($user);
        $challenge = $this->activeChallenge($couple);
        $meditation = $this->activeMeditation($couple);

        $projects = $couple->itemsOfKind('PROJECT');
        $intentions = $couple->itemsOfKind('INTENTION');
        $rituals = $couple->itemsOfKind('RITUAL');
        $values = $couple->itemsOfKind('VALUE');

        $currentMonth = (new \DateTimeImmutable('first day of this month'))->setTime(0, 0);
        $currentReview = $this->em->getRepository(CoupleReview::class)->findOneBy([
            'couple' => $couple,
            'month' => $currentMonth,
        ]);

        $nextReviewDate = null === $currentReview
            ? (new \DateTimeImmutable('first day of this month'))->format('Y-m-d')
            : (new \DateTimeImmutable('first day of next month'))->format('Y-m-d');

        return [
            'couple' => [
                'id' => $couple->getId(),
                'myName' => $user->getDisplayName(),
                'partnerName' => $partner?->getDisplayName() ?? '',
                'streakDays' => $couple->getStreakDays(),
                'vision' => $couple->getVision(),
                'verse' => $couple->getVerse(),
                'joinedAt' => $couple->getJoinedAt()?->format('Y-m-d'),
                'dailyVerse' => CoupleSanctuaryCatalog::dailyVerse(),
            ],
            'activeChallenge' => $challenge ? $this->serializeChallenge($challenge, $user) : null,
            'activeMeditation' => $meditation ? $this->serializeMeditation($meditation, $user) : null,
            'retreat' => $this->serializeRetreat($this->currentRetreat($couple), $user),
            'counts' => [
                'projects' => \count($projects),
                'intentions' => \count($intentions),
                'rituals' => \count($rituals),
                'values' => \count($values),
            ],
            'nextReviewDate' => $nextReviewDate,
            'reviewDoneThisMonth' => null !== $currentReview,
            'pingPresets' => array_map(
                static fn (string $code, array $meta) => ['code' => $code, 'label' => $meta['label']],
                array_keys(CoupleSanctuaryCatalog::PING_PRESETS),
                CoupleSanctuaryCatalog::PING_PRESETS
            ),
        ];
    }

    public function startChallenge(Couple $couple, User $by, string $challengeCode): CoupleChallenge
    {
        $meta = CoupleSanctuaryCatalog::challenge($challengeCode);
        if (null === $meta) {
            throw new \InvalidArgumentException('Défi de couple introuvable.');
        }

        $current = $this->activeChallenge($couple);
        if (null !== $current) {
            $current->abandon();
        }

        $challenge = new CoupleChallenge($couple, $challengeCode, $meta['durationDays']);
        $this->em->persist($challenge);
        $this->em->flush();

        $this->notifyPartner(
            $couple,
            $by,
            'Nouveau défi de couple',
            sprintf('%s a lancé le défi « %s » dans votre Sanctuaire.', $by->getDisplayName(), $meta['title']),
            'couple-challenge-start-'.$challenge->getId()
        );

        return $challenge;
    }

    /**
     * Valide la participation du jour pour le conjoint connecté.
     * Quand les deux conjoints ont validé, le défi passe automatiquement au jour suivant (ou se termine).
     */
    public function checkChallengeDay(Couple $couple, User $by, ?string $note = null): CoupleChallenge
    {
        $challenge = $this->activeChallenge($couple);
        if (null === $challenge) {
            throw new \DomainException('Aucun défi de couple actif.');
        }

        if ($this->isChallengeDayLocked($challenge)) {
            throw new \DomainException(self::RHYTHM_MESSAGE);
        }

        $dayNumber = $challenge->getCurrentDay();
        $day = $challenge->getOrCreateDay($dayNumber);
        $alreadyMine = $day->isValidatedBy($by);
        $day->validateFor($by, $note);

        $couple->recordActivity();

        $bothDone = $day->isBothValidated();
        if ($bothDone) {
            $challenge->advanceDay();
        }

        $this->em->flush();

        if (!$alreadyMine) {
            $meta = CoupleSanctuaryCatalog::challenge($challenge->getChallengeCode());
            $title = $meta['title'] ?? 'Défi de couple';
            $message = $bothDone
                ? sprintf('%s a aussi validé le jour %d de « %s » ! Vous avancez ensemble.', $by->getDisplayName(), $dayNumber, $title)
                : sprintf('%s a validé le jour %d de « %s ». À votre tour pour maintenir la flamme !', $by->getDisplayName(), $dayNumber, $title);

            $this->notifyPartner(
                $couple,
                $by,
                'Défi de couple',
                $message,
                sprintf('couple-challenge-%d-day-%d-%d', $challenge->getId(), $dayNumber, $by->getId())
            );
        }

        return $challenge;
    }

    public function startMeditation(Couple $couple, User $by, string $planCode): CoupleMeditation
    {
        $meta = CoupleSanctuaryCatalog::meditation($planCode);
        if (null === $meta) {
            throw new \InvalidArgumentException('Parcours de méditation introuvable.');
        }

        $current = $this->activeMeditation($couple);
        if (null !== $current) {
            $current->abandon();
        }

        $meditation = new CoupleMeditation($couple, $planCode, \count($meta['steps']));
        $this->em->persist($meditation);
        $this->em->flush();

        $this->notifyPartner(
            $couple,
            $by,
            'Nouvelle méditation à deux',
            sprintf('%s a ouvert le parcours « %s » dans votre Sanctuaire.', $by->getDisplayName(), $meta['title']),
            'couple-meditation-start-'.$meditation->getId()
        );

        return $meditation;
    }

    /**
     * Le conjoint connecté valide l'étape en cours. L'étape n'avance que quand les deux l'ont vécue,
     * et une seule étape s'ouvre par jour (sauf pendant un temps de retraite confirmé).
     */
    public function completeMeditationStep(Couple $couple, User $by): CoupleMeditation
    {
        $meditation = $this->activeMeditation($couple);
        if (null === $meditation) {
            throw new \DomainException('Aucun parcours de méditation actif.');
        }
        if ($this->isMeditationStepLocked($meditation)) {
            throw new \DomainException(self::RHYTHM_MESSAGE);
        }

        $stepNumber = $meditation->getCurrentStep();
        $alreadyMine = $meditation->isStepValidatedBy($by);
        $bothDone = $meditation->validateStepFor($by);
        if ($bothDone) {
            $couple->recordActivity();
        }
        $this->em->flush();

        if (!$alreadyMine) {
            $meta = CoupleSanctuaryCatalog::meditation($meditation->getPlanCode());
            $title = $meta['title'] ?? 'Méditation de couple';
            $message = $bothDone
                ? sprintf('%s a aussi vécu l\'étape %d de « %s ». Vous l\'avez vécue ensemble.', $by->getDisplayName(), $stepNumber, $title)
                : sprintf('%s a vécu l\'étape %d de « %s ». Elle attend votre validation.', $by->getDisplayName(), $stepNumber, $title);
            $this->notifyPartner($couple, $by, 'Méditation à deux', $message, sprintf('couple-meditation-%d-step-%d-%d', $meditation->getId(), $stepNumber, $by->getId()));
        }

        return $meditation;
    }

    // ── Rythme quotidien et temps de retraite ──────────────────

    /** Le jour en cours du défi ne s'ouvre que le lendemain du jour précédent, sauf pendant une retraite. */
    public function isChallengeDayLocked(CoupleChallenge $challenge, ?\DateTimeImmutable $today = null): bool
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        if (!$challenge->isActive() || $challenge->getCurrentDay() <= 1) {
            return false;
        }
        $previous = $challenge->getDay($challenge->getCurrentDay() - 1);
        $completedAt = $previous?->getValidatedAt();
        if (null === $completedAt || $completedAt->setTime(0, 0) < $today) {
            return false;
        }

        return !$this->isRetreatActive($challenge->getCouple(), $today);
    }

    /** L'étape suivante d'une méditation s'ouvre le lendemain, sauf pendant une retraite. */
    public function isMeditationStepLocked(CoupleMeditation $meditation, ?\DateTimeImmutable $today = null): bool
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $last = $meditation->getLastStepCompletedAt();
        if (!$meditation->isInProgress() || null === $last || $last->setTime(0, 0) < $today) {
            return false;
        }

        return !$this->isRetreatActive($meditation->getCouple(), $today);
    }

    public function isRetreatActive(Couple $couple, ?\DateTimeImmutable $today = null): bool
    {
        $retreat = $this->currentRetreat($couple, $today);

        return null !== $retreat && $retreat->isActiveOn($today ?? new \DateTimeImmutable('today'));
    }

    /** La retraite en attente, à venir ou en cours (une seule à la fois). */
    public function currentRetreat(Couple $couple, ?\DateTimeImmutable $today = null): ?CoupleRetreat
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        foreach ($this->em->getRepository(CoupleRetreat::class)->findBy(['couple' => $couple, 'cancelledAt' => null], ['createdAt' => 'DESC'], 5) as $retreat) {
            if ($retreat->isOpenOn($today)) {
                return $retreat;
            }
        }

        return null;
    }

    /** @return list<CoupleRetreat> retraites confirmées et terminées ou en cours, les plus récentes d'abord */
    public function pastRetreats(Couple $couple, int $limit = 5): array
    {
        $all = $this->em->getRepository(CoupleRetreat::class)->findBy(['couple' => $couple], ['startsOn' => 'DESC'], 20);

        return \array_slice(array_values(array_filter($all, static fn (CoupleRetreat $r) => $r->isConfirmed())), 0, $limit);
    }

    /**
     * Un conjoint propose un temps de retraite. Les dates sont choisies par le couple ; la date de fin est obligatoire.
     * La retraite ne compte qu'une fois confirmée par l'autre conjoint.
     */
    public function proposeRetreat(Couple $couple, User $by, \DateTimeImmutable $startsOn, \DateTimeImmutable $endsOn, ?string $note = null): CoupleRetreat
    {
        $today = new \DateTimeImmutable('today');
        $startsOn = $startsOn->setTime(0, 0);
        $endsOn = $endsOn->setTime(0, 0);
        if ($startsOn < $today) {
            throw new \InvalidArgumentException('La retraite ne peut pas commencer dans le passé.');
        }
        if ($endsOn < $startsOn) {
            throw new \InvalidArgumentException('La date de fin doit être le jour du début ou après.');
        }
        if (null === $couple->partnerOf($by)) {
            throw new \DomainException('Votre conjoint n\'a pas encore rejoint l\'espace couple.');
        }
        $current = $this->currentRetreat($couple);
        if (null !== $current) {
            if ($current->isConfirmed()) {
                throw new \DomainException('Une retraite est déjà prévue. Arrêtez-la avant d\'en proposer une autre.');
            }
            $current->cancel();
        }

        $retreat = new CoupleRetreat($couple, $by, $startsOn, $endsOn, $note);
        $this->em->persist($retreat);
        $this->em->flush();

        $this->notifyPartner($couple, $by, 'Proposition de retraite', sprintf(
            '%s propose un temps de retraite du %s au %s. Confirmez-le pour qu\'il compte.',
            $by->getDisplayName(), $startsOn->format('d/m/Y'), $endsOn->format('d/m/Y')
        ), 'couple-retreat-proposed-'.$retreat->getId());

        return $retreat;
    }

    /** L'autre conjoint confirme : c'est l'accord des deux qui rend la retraite effective. */
    public function confirmRetreat(Couple $couple, User $by): CoupleRetreat
    {
        $retreat = $this->currentRetreat($couple);
        if (null === $retreat || $retreat->isConfirmed()) {
            throw new \DomainException('Aucune retraite en attente de confirmation.');
        }
        if ($retreat->getProposedBy() === $by) {
            throw new \DomainException('C\'est à votre conjoint de confirmer la retraite que vous avez proposée.');
        }
        $retreat->confirm($by);
        $this->em->flush();

        $this->notifyPartner($couple, $by, 'Retraite confirmée', sprintf(
            '%s a confirmé votre temps de retraite du %s au %s.',
            $by->getDisplayName(), $retreat->getStartsOn()->format('d/m/Y'), $retreat->getEndsOn()->format('d/m/Y')
        ), 'couple-retreat-confirmed-'.$retreat->getId());

        return $retreat;
    }

    /** L'un ou l'autre peut arrêter la retraite (ou retirer la proposition) à tout moment. */
    public function stopRetreat(Couple $couple, User $by): void
    {
        $retreat = $this->currentRetreat($couple);
        if (null === $retreat) {
            throw new \DomainException('Aucune retraite en cours.');
        }
        $retreat->cancel();
        $this->em->flush();

        if ($retreat->isConfirmed()) {
            $this->notifyPartner($couple, $by, 'Retraite arrêtée', sprintf('%s a mis fin au temps de retraite. Le rythme d\'un jour à la fois reprend.', $by->getDisplayName()), 'couple-retreat-stopped-'.$retreat->getId());
        }
    }

    /** Nombre de jours de défi vécus par les deux pendant cette retraite (trace visible dans l'historique). */
    public function challengeDaysDuring(CoupleRetreat $retreat): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(\App\Couple\Entity\CoupleChallengeDay::class, 'd')
            ->join('d.challenge', 'c')
            ->where('c.couple = :couple')
            ->andWhere('d.validatedByPartnerA = true AND d.validatedByPartnerB = true')
            ->andWhere('d.validatedAt >= :start AND d.validatedAt < :end')
            ->setParameter('couple', $retreat->getCouple())
            ->setParameter('start', $retreat->getStartsOn())
            ->setParameter('end', $retreat->getEndsOn()->modify('+1 day'))
            ->getQuery()->getSingleScalarResult();
    }

    /** @return array<string, mixed>|null */
    public function serializeRetreat(?CoupleRetreat $retreat, User $user): ?array
    {
        if (null === $retreat) {
            return null;
        }
        $today = new \DateTimeImmutable('today');
        $status = !$retreat->isConfirmed() ? 'pending' : ($retreat->isActiveOn($today) ? 'active' : ($retreat->getStartsOn() > $today ? 'upcoming' : 'finished'));

        return [
            'id' => $retreat->getId(),
            'status' => $status,
            'startsOn' => $retreat->getStartsOn()->format('Y-m-d'),
            'endsOn' => $retreat->getEndsOn()->format('Y-m-d'),
            'note' => $retreat->getNote(),
            'proposedByMe' => $retreat->getProposedBy() === $user,
            'proposedBy' => $retreat->getProposedBy()?->getDisplayName(),
            'canConfirm' => !$retreat->isConfirmed() && null !== $retreat->getProposedBy() && $retreat->getProposedBy() !== $user,
        ];
    }

    public function sendPing(Couple $couple, User $by, string $preset, ?string $customMessage = null): void
    {
        $partner = $couple->partnerOf($by);
        if (null === $partner) {
            throw new \DomainException('Votre conjoint n\'a pas encore rejoint l\'espace couple.');
        }

        $meta = CoupleSanctuaryCatalog::PING_PRESETS[$preset] ?? null;
        if (null === $meta) {
            throw new \InvalidArgumentException('Type de signal inconnu.');
        }

        $senderName = $by->getDisplayName();
        $title = str_contains($meta['title'], '%s') ? sprintf($meta['title'], $senderName) : $meta['title'];
        $cleanCustom = null !== $customMessage ? trim($customMessage) : '';
        $body = '' !== $cleanCustom
            ? sprintf('%s : « %s »', $senderName, mb_substr($cleanCustom, 0, 240))
            : sprintf($meta['body'], $senderName);

        $this->notifier->notify(
            $partner,
            NotificationType::COUPLE_SANCTUARY,
            $title,
            $body,
            '/couple',
            sprintf('couple-ping-%d-%s-%d', $couple->getId(), $preset, (int) floor(time() / 60))
        );
    }

    /** @return array<string, mixed> */
    public function serializeChallenge(CoupleChallenge $challenge, User $user): array
    {
        $meta = CoupleSanctuaryCatalog::challenge($challenge->getChallengeCode());
        $dayNum = $challenge->getCurrentDay();
        $dayEntity = $challenge->getDay($dayNum);
        $couple = $challenge->getCouple();
        $isA = $user === $couple->getPartnerA();

        $doneByMe = $dayEntity ? ($isA ? $dayEntity->isValidatedByPartnerA() : $dayEntity->isValidatedByPartnerB()) : false;
        $doneByPartner = $dayEntity ? ($isA ? $dayEntity->isValidatedByPartnerB() : $dayEntity->isValidatedByPartnerA()) : false;
        $partnerNote = $dayEntity ? ($isA ? $dayEntity->getNoteB() : $dayEntity->getNoteA()) : null;

        return [
            'id' => $challenge->getId(),
            'code' => $challenge->getChallengeCode(),
            'title' => $meta['title'] ?? $challenge->getChallengeCode(),
            'subtitle' => $meta['subtitle'] ?? '',
            'theme' => $meta['theme'] ?? null,
            'status' => $challenge->getStatus(),
            'currentDay' => $dayNum,
            'totalDays' => $challenge->getTotalDays(),
            'todayPrompt' => $meta['days'][$dayNum] ?? '',
            'todayDoneByMe' => $doneByMe,
            'todayDoneByPartner' => $doneByPartner,
            'partnerNote' => $partnerNote,
            'startedAt' => $challenge->getStartedAt()->format('Y-m-d'),
            'completedAt' => $challenge->getCompletedAt()?->format('Y-m-d'),
            'locked' => $this->isChallengeDayLocked($challenge),
            'rhythmMessage' => self::RHYTHM_MESSAGE,
        ];
    }

    /** @return array<string, mixed> */
    public function serializeMeditation(CoupleMeditation $meditation, ?User $user = null): array
    {
        $meta = CoupleSanctuaryCatalog::meditation($meditation->getPlanCode());
        $stepNum = $meditation->getCurrentStep();
        $stepData = $meta['steps'][$stepNum] ?? null;

        return [
            'id' => $meditation->getId(),
            'code' => $meditation->getPlanCode(),
            'title' => $meta['title'] ?? $meditation->getPlanCode(),
            'subtitle' => $meta['subtitle'] ?? '',
            'durationMinutes' => $meta['durationMinutes'] ?? 7,
            'status' => $meditation->getStatus(),
            'currentStep' => $stepNum,
            'totalSteps' => $meditation->getTotalSteps(),
            'step' => $stepData,
            'startedAt' => $meditation->getStartedAt()->format('Y-m-d'),
            'completedAt' => $meditation->getCompletedAt()?->format('Y-m-d'),
            'stepDoneByMe' => null !== $user && $meditation->isStepValidatedBy($user),
            'stepDoneByPartner' => null !== $user && $meditation->isStepValidatedByPartnerOf($user),
            'locked' => $this->isMeditationStepLocked($meditation),
            'rhythmMessage' => self::RHYTHM_MESSAGE,
        ];
    }

    /** @return array<string, mixed> */
    public function serializeItem(CoupleItem $item): array
    {
        return [
            'id' => $item->getId(),
            'kind' => $item->getKind(),
            'title' => $item->getTitle(),
            'details' => $item->getDetails(),
            'category' => $item->getCategory(),
            'categoryLabel' => CoupleService::PROJECT_CATEGORIES[$item->getCategory() ?? ''] ?? $item->getCategory(),
            'deadline' => $item->getDeadline()?->format('Y-m-d'),
            'progress' => $item->getProgress(),
            'createdBy' => $item->getCreatedBy()?->getDisplayName(),
            'createdAt' => $item->getCreatedAt()->format('Y-m-d'),
        ];
    }

    private function notifyPartner(Couple $couple, User $by, string $title, string $message, string $dedupeKey): void
    {
        $partner = $couple->partnerOf($by);
        if (null === $partner) {
            return;
        }
        $this->notifier->notify($partner, NotificationType::COUPLE_SANCTUARY, $title, $message, '/couple', $dedupeKey);
    }
}
