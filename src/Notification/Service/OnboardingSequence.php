<?php

namespace App\Notification\Service;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Notification\Enum\NotificationType;
use App\Personnalite\Entity\Assessment;
use App\Rituels\Entity\Habit;
use App\Social\Entity\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Emails d'accueil des deux premières semaines : une étape, une action, adaptée à ce que l'utilisateur a déjà fait.
 *
 * J0 bienvenue (dès la vérification de l'e-mail) · J1 vision · J3 premier rituel, ou profil complet si un rituel existe
 * · J7 revue de la semaine (+ partenaire) · J9 plans stratégiques (seulement pendant l'essai PRO ; le rappel de fin
 * d'essai part à J11). Désactivable (préférence « conseils de démarrage », lien en bas de chaque email).
 */
final class OnboardingSequence
{
    public const DAYS = [0, 1, 3, 7, 9];

    public function __construct(
        private readonly Notifier $notifier,
        private readonly EntityManagerInterface $em,
        private readonly Entitlements $entitlements,
    ) {
    }

    /** Email de bienvenue, envoyé juste après la vérification de l'adresse. */
    public function welcome(User $user): bool
    {
        return $this->sendStep($user, 0);
    }

    /** Appelé chaque jour par les rappels : envoie l'étape du jour s'il y en a une. */
    public function sendFor(User $user, \DateTimeImmutable $today): bool
    {
        $created = $user->getCreatedAt();
        if (null === $created || !$user->isVerified()) {
            return false;
        }
        $day = (int) $created->setTime(0, 0)->diff($today->setTime(0, 0))->format('%r%a');
        // Rattrapage de la bienvenue le lendemain si elle n'a pas pu partir
        if (1 === $day) {
            $this->sendStep($user, 0);
        }

        return \in_array($day, self::DAYS, true) && $this->sendStep($user, $day);
    }

    public function sendStep(User $user, int $day): bool
    {
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return false;
        }
        $step = $this->step($user, $day);
        if (null === $step) {
            return false;
        }
        [$template, $title, $url, $context] = $step;

        return $this->notifier->notify($user, NotificationType::ONBOARDING, $title, $title, $url, 'accueil J'.$day,
            ['template' => 'notification/email/onboarding/'.$template.'.html.twig', 'context' => $context]);
    }

    /** @return array{0: string, 1: string, 2: string, 3: array<string, mixed>}|null */
    private function step(User $user, int $day): ?array
    {
        $trialEnd = $this->entitlements->isTrialing($user) ? $this->entitlements->proUntil($user) : null;

        return match ($day) {
            0 => ['j0_bienvenue', 'Bienvenue dans KIINI SAWA', $user->isOnboardingCompleted() ? '/dashboard' : '/onboarding',
                ['onboarded' => $user->isOnboardingCompleted(), 'trialEnd' => $trialEnd]],
            1 => '' !== trim((string) $user->getPlanDeVie()?->getVision()?->getContent())
                ? null
                : ['j1_vision', 'Où voulez-vous être dans 10 ans ?', '/plan-de-vie/assistant', []],
            3 => match (true) {
                !$this->hasHabit($user) => ['j3_rituel', 'Un seul petit rituel pour commencer', '/mes-plans/rituels', []],
                !$this->hasAssessment($user) => ['j3_test', 'Découvrez comment Dieu vous a fait', '/mes-tests/questionnaire/'.($this->entitlements->canTakeTest($user, 'profil-complet') ? 'profil-complet' : $this->entitlements->firstFreeTest()), []],
                default => null,
            },
            7 => ['j7_revue', 'Votre première revue de la semaine', '/bilan/revue-hebdomadaire', ['hasPartner' => $this->hasPartner($user)]],
            9 => null === $trialEnd ? null : ['j9_pro', 'Votre vision a besoin d\'un plan', '/mes-plans', ['trialEnd' => $trialEnd]],
            default => null,
        };
    }

    private function hasHabit(User $user): bool
    {
        return $this->em->getRepository(Habit::class)->count(['user' => $user]) > 0;
    }

    private function hasAssessment(User $user): bool
    {
        return $this->em->getRepository(Assessment::class)->count(['user' => $user]) > 0;
    }

    private function hasPartner(User $user): bool
    {
        $repo = $this->em->getRepository(Connection::class);

        return $repo->count(['requester' => $user]) + $repo->count(['receiver' => $user]) > 0;
    }
}
