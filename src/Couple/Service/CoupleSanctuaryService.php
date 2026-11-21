<?php

namespace App\Couple\Service;

use App\Compte\Entity\User;
use App\Couple\Entity\Couple;
use App\Couple\Entity\CoupleChallenge;
use App\Couple\Entity\CoupleItem;
use App\Couple\Entity\CoupleMeditation;
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
            'activeMeditation' => $meditation ? $this->serializeMeditation($meditation) : null,
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

    public function completeMeditationStep(Couple $couple, User $by): CoupleMeditation
    {
        $meditation = $this->activeMeditation($couple);
        if (null === $meditation) {
            throw new \DomainException('Aucun parcours de méditation actif.');
        }

        $stepCompleted = $meditation->getCurrentStep();
        $meditation->completeCurrentStep();
        $couple->recordActivity();
        $this->em->flush();

        $meta = CoupleSanctuaryCatalog::meditation($meditation->getPlanCode());
        $title = $meta['title'] ?? 'Méditation de couple';
        $this->notifyPartner(
            $couple,
            $by,
            'Méditation complétée',
            sprintf('%s a validé l\'étape %d de « %s ».', $by->getDisplayName(), $stepCompleted, $title),
            sprintf('couple-meditation-%d-step-%d', $meditation->getId(), $stepCompleted)
        );

        return $meditation;
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
        ];
    }

    /** @return array<string, mixed> */
    public function serializeMeditation(CoupleMeditation $meditation): array
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
