<?php

namespace App\Academie\Controller;

use App\Abonnement\Exception\ProRequiredException;
use App\Abonnement\Service\Entitlements;
use App\Academie\Entity\Course;
use App\Academie\Entity\CourseEnrollment;
use App\Academie\Entity\Lesson;
use App\Academie\Exception\PracticeRefused;
use App\Academie\Repository\CourseRepository;
use App\Academie\Repository\LessonNoteRepository;
use App\Academie\Service\AcademyCatalog;
use App\Academie\Service\AcademyJournal;
use App\Academie\Service\AcademyPlaces;
use App\Academie\Service\AcademyPlanBridge;
use App\Academie\Service\PracticeCatalog;
use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * L'Académie côté membres : catalogue, présentation d'un parcours, lecture d'une leçon, notes (dans le Journal) et
 * « Mettre en pratique » (dans les plans, avec l'accord du membre). Voir docs/modules/academie.md.
 */
#[Route('/academie', name: 'app_academie')]
final class AcademyController extends AbstractController
{
    private const CSRF = 'academie';

    public function __construct(
        private readonly AcademyCatalog $catalog,
        private readonly CourseRepository $courses,
        private readonly LessonNoteRepository $notes,
        private readonly AcademyPlanBridge $bridge,
        private readonly AcademyJournal $journal,
        private readonly Entitlements $entitlements,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->user();
        ['courses' => $courses, 'enrollments' => $enrollments] = $this->catalog->visibleFor($user);
        $place = $request->query->getString('lieu');
        $place = AcademyPlaces::isValid($place) && '' !== $place ? $place : null;
        $shown = null === $place ? $courses : array_values(array_filter($courses, fn (Course $c) => $c->getPlace() === $place));
        $inProgress = array_values(array_filter($enrollments, fn (CourseEnrollment $e) => !$e->isCompleted()));

        return $this->render('academie/index.html.twig', [
            'courses' => $shown,
            'all_count' => \count($courses),
            'enrollments' => $enrollments,
            'in_progress' => $inProgress,
            'place' => $place,
            'plans' => AcademyPlaces::plans(),
            'has_couple' => [] !== array_filter($courses, fn (Course $c) => $c->isCouple()),
            'recommended' => null === $place ? $this->catalog->recommended($user, $courses) : null,
            'is_pro' => $this->entitlements->isPro($user),
        ]);
    }

    #[Route('/{slug}', name: '_course', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function course(string $slug): Response
    {
        $user = $this->user();
        $course = $this->visibleCourse($slug);
        $enrollment = $this->catalog->enrollment($user, $course);

        return $this->render('academie/course.html.twig', [
            'course' => $course,
            'enrollment' => $enrollment,
            'locked' => $this->catalog->isLocked($user, $course),
            'notes' => null !== $enrollment ? $this->notes->forCourse($user, $course) : [],
            'boussole_due' => null !== $enrollment && $enrollment->isCompleted() && $this->catalog->boussoleIsDue($user),
            'can_journal' => $this->entitlements->can($user, 'journal'),
        ]);
    }

    #[Route('/{slug}/{lessonSlug}', name: '_lesson', requirements: ['slug' => '[a-z0-9-]+', 'lessonSlug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function lesson(string $slug, string $lessonSlug): Response
    {
        $user = $this->user();
        [$course, $lesson, $enrollment] = $this->open($slug, $lessonSlug);
        [$previous, $next, $position, $total] = AcademyCatalog::neighbours($course, $lesson);
        $completion = $enrollment->completionFor($lesson);

        if ($lesson->hasPractice()) {
            $practice = [
                'fixed' => true,
                'target' => $lesson->getTargetPlan(),
                'action' => $lesson->getTargetAction(),
                'form' => $this->bridge->form($user, $lesson->getTargetAction()),
                'blocker' => $this->bridge->blocker($user, $lesson->getTargetPlan(), $lesson->getTargetAction(), !$completion?->hasCreatedEntity()),
            ];
        } else {
            $destinations = [];
            foreach (AcademyPlanBridge::FREE_DESTINATIONS as $value) {
                [$target, $action] = explode('|', $value);
                $destinations[$value] = ['label' => PracticeCatalog::label($target, $action), 'blocker' => $this->bridge->blocker($user, $target, $action)];
            }
            $practice = ['fixed' => false, 'destinations' => $destinations, 'default' => $this->defaultDestination($course)];
        }

        return $this->render('academie/lesson.html.twig', [
            'course' => $course,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'completion' => $completion,
            'practice' => $practice,
            'notes' => $this->notes->forLesson($user, $lesson),
            'previous' => $previous,
            'next' => $next,
            'position' => $position,
            'total' => $total,
            'can_journal' => $this->entitlements->can($user, 'journal'),
        ]);
    }

    #[Route('/{slug}/{lessonSlug}/terminer', name: '_complete', requirements: ['slug' => '[a-z0-9-]+', 'lessonSlug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function complete(Request $request, string $slug, string $lessonSlug): Response
    {
        $this->checkCsrf($request);
        $user = $this->user();
        [$course, $lesson, $enrollment] = $this->open($slug, $lessonSlug);
        $p = $request->request;
        $back = $this->generateUrl('app_academie_lesson', ['slug' => $slug, 'lessonSlug' => $lessonSlug]).'#pratique';

        if ('plan' === $p->getString('mode')) {
            if ($lesson->hasPractice()) {
                [$target, $action] = [$lesson->getTargetPlan(), $lesson->getTargetAction()];
            } else {
                $destination = $p->getString('destination');
                if (!\in_array($destination, AcademyPlanBridge::FREE_DESTINATIONS, true)) {
                    $this->addFlash('error', 'Choisissez où enregistrer votre point à travailler.');

                    return $this->redirect($back);
                }
                [$target, $action] = explode('|', $destination);
            }
            $title = mb_substr($p->getString('title'), 0, 'add_note' === $action ? AcademyJournal::MAX_LENGTH : 2000);
            $detail = mb_substr($p->getString('detail'), 0, 2000);
            $choice = $p->getString('choice');
            try {
                $saved = $this->bridge->save($user, $lesson, $target, $action, $title, $detail, $choice, $enrollment->completionFor($lesson));
            } catch (PracticeRefused $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirect($back);
            }
            $enrollment->complete($lesson)->recordPractice(
                ['target' => $target, 'action' => $action, 'title' => trim($title), 'detail' => trim($detail) ?: null, 'choice' => $choice ?: null],
                $saved['type'],
                $saved['id'],
            );
            $this->addFlash('success', 'Votre réponse est enregistrée dans : '.PracticeCatalog::TARGETS[$target]['label'].'.');
        } else {
            $enrollment->complete($lesson);
        }
        $this->em->flush();

        [, $next] = AcademyCatalog::neighbours($course, $lesson);
        $upcoming = $enrollment->nextLesson() ?? $next;
        if (null !== $upcoming && !$enrollment->isCompleted()) {
            return $this->redirectToRoute('app_academie_lesson', ['slug' => $slug, 'lessonSlug' => $upcoming->getSlug()]);
        }

        return $this->redirectToRoute('app_academie_course', ['slug' => $slug]);
    }

    #[Route('/{slug}/{lessonSlug}/notes', name: '_note_add', requirements: ['slug' => '[a-z0-9-]+', 'lessonSlug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function addNote(Request $request, string $slug, string $lessonSlug): Response
    {
        $this->checkCsrf($request);
        [$course, $lesson] = $this->open($slug, $lessonSlug);
        try {
            $this->journal->addLessonNote($this->user(), $lesson, $request->request->getString('content'), $request->request->getString('plan') ?: $course->getPrimaryPlan());
            $this->addFlash('success', 'Note ajoutée à votre Journal, carnet « Académie ».');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirect($this->generateUrl('app_academie_lesson', ['slug' => $slug, 'lessonSlug' => $lessonSlug]).'#notes');
    }

    #[Route('/notes/{id}/supprimer', name: '_note_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteNote(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $link = $this->notes->findOneForUser($id, $this->user());
        if (null === $link) {
            throw $this->createNotFoundException();
        }
        $lesson = $link->getLesson();
        $this->journal->delete($link);
        $this->addFlash('success', 'Note supprimée.');
        $course = $lesson?->getCourse();

        return null !== $lesson && null !== $course
            ? $this->redirect($this->generateUrl('app_academie_lesson', ['slug' => $course->getSlug(), 'lessonSlug' => $lesson->getSlug()]).'#notes')
            : $this->redirectToRoute('app_academie');
    }

    /** Parcours publié, ou déjà commencé par ce membre ; 404 sinon. */
    private function visibleCourse(string $slug): Course
    {
        $course = $this->courses->findOneBy(['slug' => $slug]);
        if (null === $course || !$this->catalog->isVisible($this->user(), $course)) {
            throw $this->createNotFoundException();
        }

        return $course;
    }

    /**
     * Ouvre une leçon : parcours visible, accès PRO si besoin, leçon visible ; inscrit le membre au besoin.
     *
     * @return array{0: Course, 1: Lesson, 2: CourseEnrollment}
     */
    private function open(string $slug, string $lessonSlug): array
    {
        $user = $this->user();
        $course = $this->visibleCourse($slug);
        if ($this->catalog->isLocked($user, $course)) {
            throw new ProRequiredException('academie', 'Le parcours « '.$course->getTitle().' » fait partie de KIINI SAWA PRO.');
        }
        $lesson = $this->catalog->findLesson($course, $lessonSlug);
        if (null === $lesson) {
            throw $this->createNotFoundException();
        }

        return [$course, $lesson, $this->catalog->enroll($user, $course)];
    }

    private function defaultDestination(Course $course): string
    {
        foreach (AcademyPlanBridge::FREE_DESTINATIONS as $value) {
            if (str_starts_with($value, ($course->getPrimaryPlan() ?? 'VIE').'|')) {
                return $value;
            }
        }

        return AcademyPlanBridge::FREE_DESTINATIONS[0];
    }

    private function checkCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF, $request->request->getString('_csrf_token'))) {
            throw new AccessDeniedHttpException('Jeton de sécurité invalide. Rechargez la page.');
        }
    }

    private function user(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
