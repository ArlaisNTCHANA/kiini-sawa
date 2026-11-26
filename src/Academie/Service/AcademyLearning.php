<?php

namespace App\Academie\Service;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseEnrollment;
use App\Academie\Entity\Lesson;
use App\Academie\Entity\LessonCompletion;
use App\Academie\Exception\PracticeRefused;
use App\Compte\Entity\User;
use App\Social\Service\AccountabilityService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ce qu'un membre fait dans une leçon, commun au site et à l'application mobile : le bloc « Mettre en pratique »
 * (ce qui est proposé, l'enregistrement dans un plan ou la fin de leçon sans rien modifier) et la proposition d'un
 * Partenaire de Redevabilité.
 */
final class AcademyLearning
{
    public function __construct(
        private readonly AcademyPlanBridge $bridge,
        private readonly AccountabilityService $accountability,
        private readonly EntityManagerInterface $em,
        private readonly PlanRubrics $rubrics,
    ) {
    }

    /**
     * Le bloc « Mettre en pratique » de cette leçon pour ce membre.
     *
     * @return array<string, mixed>
     */
    public function practice(User $user, Course $course, Lesson $lesson, ?LessonCompletion $completion): array
    {
        if ($lesson->hasPractice() && PlanRubrics::isRubric($lesson->getTargetAction())) {
            return [
                'fixed' => true,
                'target' => $lesson->getTargetPlan(),
                'action' => $lesson->getTargetAction(),
                'rubric' => $this->rubrics->state($user, (string) $lesson->getTargetAction()),
                'blocker' => $this->bridge->blocker($user, (string) $lesson->getTargetPlan(), (string) $lesson->getTargetAction()),
            ];
        }
        if ($lesson->hasPractice()) {
            return [
                'fixed' => true,
                'target' => $lesson->getTargetPlan(),
                'action' => $lesson->getTargetAction(),
                'form' => $this->bridge->form($user, $lesson->getTargetAction()),
                'blocker' => $this->bridge->blocker($user, $lesson->getTargetPlan(), $lesson->getTargetAction(), !$completion?->hasCreatedEntity()),
            ];
        }
        $destinations = [];
        foreach (AcademyPlanBridge::FREE_DESTINATIONS as $value) {
            [$target, $action] = explode('|', $value);
            $destinations[$value] = ['label' => PracticeCatalog::label($target, $action), 'blocker' => $this->bridge->blocker($user, $target, $action)];
        }

        return ['fixed' => false, 'destinations' => $destinations, 'default' => self::defaultDestination($course)];
    }

    /**
     * Termine la leçon. En mode « plan », enregistre d'abord la réponse dans le plan (création ou mise à jour, sans
     * doublon) ; en mode « sans », ne modifie rien.
     *
     * @param array{title?: string, detail?: string, choice?: string, destination?: string, text?: string, horizon?: string, items?: array<mixed>, goals?: array<string, mixed>} $input
     *
     * @return array{target: ?string, label: ?string}
     *
     * @throws PracticeRefused message à montrer au membre (rien n'est enregistré, la leçon n'est pas terminée)
     */
    public function complete(User $user, Lesson $lesson, CourseEnrollment $enrollment, bool $toPlan, array $input = []): array
    {
        if (!$toPlan) {
            $enrollment->complete($lesson);
            $this->em->flush();

            return ['target' => null, 'label' => null];
        }
        if ($lesson->hasPractice() && PlanRubrics::isRubric($lesson->getTargetAction())) {
            [$target, $action] = [(string) $lesson->getTargetPlan(), (string) $lesson->getTargetAction()];
            if (null !== $reason = $this->bridge->blocker($user, $target, $action)) {
                throw new PracticeRefused($reason);
            }
            $saved = $this->rubrics->save($user, $action, $input);
            $enrollment->complete($lesson)->recordPractice(['target' => $target, 'action' => $action, 'title' => mb_substr($saved['summary'], 0, 2000)], null, null);
            $this->em->flush();

            return ['target' => $target, 'label' => PracticeCatalog::TARGETS[$target]['label'], 'summary' => $saved['summary']];
        }
        if ($lesson->hasPractice()) {
            [$target, $action] = [$lesson->getTargetPlan(), $lesson->getTargetAction()];
        } else {
            $destination = (string) ($input['destination'] ?? '');
            if (!\in_array($destination, AcademyPlanBridge::FREE_DESTINATIONS, true)) {
                throw new PracticeRefused('Choisissez où enregistrer votre point à travailler.');
            }
            [$target, $action] = explode('|', $destination);
        }
        $title = mb_substr((string) ($input['title'] ?? ''), 0, 'add_note' === $action ? AcademyJournal::MAX_LENGTH : 2000);
        $detail = mb_substr((string) ($input['detail'] ?? ''), 0, 2000);
        $choice = (string) ($input['choice'] ?? '');
        $saved = $this->bridge->save($user, $lesson, $target, $action, $title, $detail, $choice, $enrollment->completionFor($lesson));
        $enrollment->complete($lesson)->recordPractice(
            ['target' => $target, 'action' => $action, 'title' => trim($title), 'detail' => trim($detail) ?: null, 'choice' => $choice ?: null],
            $saved['type'],
            $saved['id'],
        );
        $this->em->flush();

        return ['target' => $target, 'label' => PracticeCatalog::TARGETS[$target]['label']];
    }

    /**
     * Après une leçon qui a créé un rituel ou un objectif du Plan de Vie (ce que la redevabilité sait suivre), proposer
     * qu'un Partenaire de Redevabilité veille dessus : seulement si la personne peut ouvrir un engagement et qu'aucun ne
     * le suit déjà. La progression dans l'Académie, elle, n'est jamais montrée au Partenaire de Redevabilité.
     *
     * @return array{target: string, label: string}|null cible au format du formulaire de redevabilité (« HABIT:12 »)
     */
    public function accountabilityOffer(User $user, ?LessonCompletion $completion): ?array
    {
        $type = match ($completion?->getCreatedEntityType()) {
            'Habit' => 'HABIT',
            'LifeGoal' => 'GOAL',
            default => null,
        };
        if (null === $type || !$this->bridge->find($user, $completion->getCreatedEntityType(), (int) $completion->getCreatedEntityId())
            || !$this->accountability->canCreate($user) || $this->accountability->isFollowed($user, $type, (int) $completion->getCreatedEntityId())) {
            return null;
        }

        return ['target' => $type.':'.$completion->getCreatedEntityId(), 'label' => 'HABIT' === $type ? 'ce rituel' : 'cet objectif'];
    }

    public static function defaultDestination(Course $course): string
    {
        foreach (AcademyPlanBridge::FREE_DESTINATIONS as $value) {
            if (str_starts_with($value, ($course->getPrimaryPlan() ?? 'VIE').'|')) {
                return $value;
            }
        }

        return AcademyPlanBridge::FREE_DESTINATIONS[0];
    }
}
