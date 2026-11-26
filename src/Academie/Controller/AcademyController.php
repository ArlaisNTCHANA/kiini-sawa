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
use App\Academie\Service\AcademyLearning;
use App\Academie\Service\AcademyPlaces;
use App\Academie\Service\AcademyPlanBridge;
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
        private readonly AcademyJournal $journal,
        private readonly Entitlements $entitlements,
        private readonly EntityManagerInterface $em,
        private readonly AcademyLearning $learning,
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

        // Sans filtre, les parcours de plan sont rangés à part, en tête (décision du fondateur du 26/11/2026)
        $journeys = null === $place ? array_values(array_filter($shown, fn (Course $c) => $c->isPlanJourney())) : [];
        if ([] !== $journeys) {
            $shown = array_values(array_filter($shown, fn (Course $c) => !$c->isPlanJourney()));
        }

        return $this->render('academie/index.html.twig', [
            'journeys' => $journeys,
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

    /** « Par où commencer ? » : les parcours de plan, le parcours de 90 jours ou le mode libre (26/11/2026). */
    #[Route('/par-ou-commencer', name: '_start', methods: ['GET'], priority: 10)]
    public function start(): Response
    {
        $user = $this->user();
        $journeys = $this->catalog->planJourneys();
        if ([] === $journeys) {
            return $this->redirectToRoute('app_journey');
        }

        return $this->render('academie/par_ou_commencer.html.twig', [
            'journeys' => $journeys,
            'enrollments' => $this->catalog->visibleFor($user)['enrollments'],
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
            'is_pro' => $this->entitlements->isPro($user),
        ]);
    }

    #[Route('/{slug}/{lessonSlug}', name: '_lesson', requirements: ['slug' => '[a-z0-9-]+', 'lessonSlug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function lesson(string $slug, string $lessonSlug): Response
    {
        $user = $this->user();
        [$course, $lesson, $enrollment] = $this->open($slug, $lessonSlug);
        [$previous, $next, $position, $total] = AcademyCatalog::neighbours($course, $lesson);
        $completion = $enrollment->completionFor($lesson);

        $practice = $this->learning->practice($user, $course, $lesson, $completion);

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
            'accountability' => $this->learning->accountabilityOffer($user, $completion),
        ]);
    }

    #[Route('/{slug}/{lessonSlug}/terminer', name: '_complete', requirements: ['slug' => '[a-z0-9-]+', 'lessonSlug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function complete(Request $request, string $slug, string $lessonSlug): Response
    {
        $this->checkCsrf($request);
        $user = $this->user();
        [$course, $lesson, $enrollment] = $this->open($slug, $lessonSlug);
        $p = $request->request;
        $toPlan = 'plan' === $p->getString('mode');
        try {
            $result = $this->learning->complete($user, $lesson, $enrollment, $toPlan, [
                'title' => $p->getString('title'), 'detail' => $p->getString('detail'), 'choice' => $p->getString('choice'), 'destination' => $p->getString('destination'),
                // Séances des parcours de plan (PlanRubrics) : texte, ou nouvelles lignes d'une rubrique
                'text' => $p->getString('text'), 'horizon' => $p->getString('horizon'),
                'items' => $p->all('items'), 'goals' => $p->all('goals'),
            ]);
        } catch (PracticeRefused $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirect($this->generateUrl('app_academie_lesson', ['slug' => $slug, 'lessonSlug' => $lessonSlug]).'#pratique');
        }
        if ($course->isPlanJourney()) {
            return $this->celebrate($course, $enrollment, $toPlan ? $result : null);
        }
        if ($toPlan) {
            $this->addFlash('success', 'Votre réponse est enregistrée dans : '.$result['label'].'.');
            // Un rituel ou un objectif du Plan de Vie : on reste sur la leçon pour proposer un Partenaire de Redevabilité
            if (null !== $this->learning->accountabilityOffer($user, $enrollment->completionFor($lesson))) {
                return $this->redirect($this->generateUrl('app_academie_lesson', ['slug' => $slug, 'lessonSlug' => $lessonSlug]).'#redevabilite');
            }
        }

        [, $next] = AcademyCatalog::neighbours($course, $lesson);
        $upcoming = $enrollment->nextLesson() ?? $next;
        if (null !== $upcoming && !$enrollment->isCompleted()) {
            return $this->redirectToRoute('app_academie_lesson', ['slug' => $slug, 'lessonSlug' => $upcoming->getSlug()]);
        }

        return $this->redirectToRoute('app_academie_course', ['slug' => $slug]);
    }

    /**
     * Fin d'une séance d'un parcours de plan : ce qui vient d'être posé, la station franchie et la suivante ; à la fin du
     * parcours, le plan rempli (parcours guidés, 26/11/2026).
     *
     * @param array{label: ?string, summary?: string}|null $result
     */
    private function celebrate(Course $course, CourseEnrollment $enrollment, ?array $result): Response
    {
        if (null !== $result && isset($result['summary'])) {
            $this->addFlash('success', 'Posé dans votre '.$result['label'].' : '.rtrim(mb_strimwidth($result['summary'], 0, 160, '…'), '. ').'.');
        }
        $progress = $enrollment->progress();
        if ($enrollment->isCompleted()) {
            $this->addFlash('success', 'Bravo ! Vous avez terminé le parcours « '.$course->getTitle().' » : '.$progress['total'].' stations sur '.$progress['total'].'. Voici votre plan, tel que vous l\'avez posé.');
            $route = AcademyPlanBridge::route((string) $course->getPrimaryPlan());

            return null !== $route ? $this->redirectToRoute($route) : $this->redirectToRoute('app_academie_course', ['slug' => $course->getSlug()]);
        }
        $upcoming = $enrollment->nextLesson();
        $this->addFlash('info', 'Station '.$progress['done'].' sur '.$progress['total'].' franchie.'
            .(null !== $upcoming ? ' Prochaine séance : « '.$upcoming->getTitle().' », '.$upcoming->getDurationMinutes().' min.' : ''));

        return null !== $upcoming
            ? $this->redirectToRoute('app_academie_lesson', ['slug' => $course->getSlug(), 'lessonSlug' => $upcoming->getSlug()])
            : $this->redirectToRoute('app_academie_course', ['slug' => $course->getSlug()]);
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

    #[Route('/{slug}/rythme', name: '_pace', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function pace(Request $request, string $slug): Response
    {
        $this->checkCsrf($request);
        $user = $this->user();
        $course = $this->visibleCourse($slug);
        if ($this->catalog->isLocked($user, $course)) {
            throw new ProRequiredException('academie', 'Le parcours « '.$course->getTitle().' » fait partie de KIINI SAWA PRO.');
        }
        $mode = $request->request->getString('rythme');
        if (!\in_array($mode, CourseEnrollment::PACES, true)) {
            throw new AccessDeniedHttpException('Rythme inconnu.');
        }
        $this->catalog->enroll($user, $course)->setPace($mode);
        $this->em->flush();
        $this->addFlash('success', match ($mode) {
            CourseEnrollment::PACE_DAILY => 'C\'est noté : vous recevrez une leçon chaque matin par e-mail.',
            CourseEnrollment::PACE_THREE_PER_WEEK => 'C\'est noté : vous recevrez une leçon le lundi, le mercredi et le vendredi.',
            default => 'C\'est noté : vous avancez à votre rythme, sans e-mail.',
        });

        return $this->redirect($this->generateUrl('app_academie_course', ['slug' => $slug]).'#rythme');
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
