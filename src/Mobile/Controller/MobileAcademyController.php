<?php

namespace App\Mobile\Controller;

use App\Abonnement\Exception\ProRequiredException;
use App\Academie\Entity\Course;
use App\Academie\Entity\CourseEnrollment;
use App\Academie\Entity\Lesson;
use App\Academie\Entity\LessonNote;
use App\Academie\Exception\PracticeRefused;
use App\Academie\Repository\CourseRepository;
use App\Academie\Repository\LessonNoteRepository;
use App\Academie\Service\AcademyCatalog;
use App\Academie\Service\AcademyJournal;
use App\Academie\Service\AcademyLearning;
use App\Academie\Service\AcademyOverview;
use App\Academie\Service\AcademyPlaces;
use App\Academie\Service\PracticeCatalog;
use App\Academie\Service\VideoEmbed;
use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * L'Académie dans l'application mobile (Lot 4) : mêmes règles que le site (AcademyCatalog, AcademyLearning,
 * AcademyPlanBridge). Adresses et clés en français, réponses no-store, aucun prix : un parcours PRO est seulement
 * signalé comme réservé à l'offre PRO.
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/mobile/v1/academie')]
final class MobileAcademyController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(
        private readonly AcademyCatalog $catalog,
        private readonly AcademyLearning $learning,
        private readonly AcademyJournal $journal,
        private readonly CourseRepository $courses,
        private readonly LessonNoteRepository $notes,
        private readonly EntityManagerInterface $em,
        private readonly \App\Academie\Service\LessonRenderer $renderer,
        private readonly \App\Academie\Service\AcademyImages $images,
    ) {
    }

    #[Route('', name: 'api_mobile_v1_academie_catalog', methods: ['GET'])]
    public function catalog(): JsonResponse
    {
        $user = $this->currentMobileUser();
        ['courses' => $courses, 'enrollments' => $enrollments] = $this->catalog->visibleFor($user);
        $recommended = $this->catalog->recommended($user, $courses);

        return $this->jsonNoStore([
            'parcours' => array_map(fn (Course $c) => $this->courseSummary($user, $c, $enrollments[$c->getId()] ?? null), $courses),
            'enCours' => array_values(array_map(fn (CourseEnrollment $e) => $e->getCourse()->getSlug(), array_filter($enrollments, fn (CourseEnrollment $e) => !$e->isCompleted()))),
            'recommande' => null === $recommended ? null : [
                'parcours' => $recommended['course']->getSlug(),
                'plan' => $recommended['plan'],
                'libellePlan' => AcademyPlaces::label($recommended['plan']),
            ],
            'plans' => array_map(fn (string $key, string $label) => ['cle' => $key, 'libelle' => $label], array_keys(AcademyPlaces::plans()), AcademyPlaces::plans()),
            'couple' => ['cle' => AcademyPlaces::COUPLE, 'libelle' => 'Sanctuaire du Couple'],
        ]);
    }

    #[Route('/notes/{id}', name: 'api_mobile_v1_academie_note_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteNote(int $id): JsonResponse
    {
        $link = $this->notes->findOneForUser($id, $this->currentMobileUser());
        if (null === $link) {
            return $this->jsonError('Note introuvable.', Response::HTTP_NOT_FOUND, 'NOTE_NOT_FOUND');
        }
        $this->journal->delete($link);

        return $this->jsonNoStore(['supprime' => true]);
    }

    #[Route('/{slug}', name: 'api_mobile_v1_academie_course', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function course(string $slug): JsonResponse
    {
        $user = $this->currentMobileUser();
        $course = $this->visibleCourse($user, $slug);
        if (null === $course) {
            return $this->jsonError('Parcours introuvable.', Response::HTTP_NOT_FOUND, 'COURSE_NOT_FOUND');
        }
        $enrollment = $this->catalog->enrollment($user, $course);
        $modules = [];
        foreach ($course->getModules() as $module) {
            $lessons = [];
            foreach ($module->getLessons() as $lesson) {
                if ($lesson->isVisible()) {
                    $a = AcademyOverview::lessonActivities($lesson);
                    $lessons[] = ['adresse' => $lesson->getSlug(), 'titre' => $lesson->getTitle(), 'minutes' => $lesson->getDurationMinutes(),
                        'video' => $lesson->hasVideo(), 'faite' => (bool) $enrollment?->hasCompleted($lesson),
                        'questions' => $a['questions'],
                        'exercice' => null === $a['target'] ? null : ['cible' => $a['target'], 'libelle' => $a['target_label'], 'sorte' => match ($a['target_kind']) { 'plan' => 'plan', 'couple' => 'couple', default => 'quotidien' }],
                    ];
                }
            }
            if ($lessons) {
                $modules[] = ['titre' => $module->getTitle(), 'lecons' => $lessons];
            }
        }
        $first = $course->getLessons(true)[0] ?? null;

        return $this->jsonNoStore([
            'parcours' => $this->courseSummary($user, $course, $enrollment) + [
                'presentationHtml' => $course->getDescription() ? $this->renderer->toHtml($course->getDescription(), true) : null,
                'modules' => $modules,
                'sixPlans' => array_map(fn (array $p) => $this->plan($p), AcademyOverview::plans($course)),
                'quotidien' => AcademyOverview::daily($course),
                'chiffres' => (function (array $s) { return ['etapes' => $s['modules'], 'lecons' => $s['lessons'], 'minutes' => $s['minutes'], 'questions' => $s['questions'],
                    'exercices' => $s['exercises'], 'videos' => $s['videos'], 'minutesLeconMin' => $s['min_lesson'], 'minutesLeconMax' => $s['max_lesson']]; })(AcademyOverview::stats($course)),
                'prochaineLecon' => ($enrollment ? $enrollment->nextLesson() : $first)?->getSlug(),
                'rythme' => ['mode' => $enrollment?->getPaceMode() ?? CourseEnrollment::PACE_FREE, 'enPause' => (bool) $enrollment?->isPacePaused()],
                'proposerBoussole' => null !== $enrollment && $enrollment->isCompleted() && $this->catalog->boussoleIsDue($user),
            ],
        ]);
    }

    #[Route('/{slug}/rythme', name: 'api_mobile_v1_academie_pace', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function pace(Request $request, string $slug): JsonResponse
    {
        $user = $this->currentMobileUser();
        $course = $this->openableCourse($user, $slug);
        if ($course instanceof JsonResponse) {
            return $course;
        }
        $mode = (string) ($this->decodeJson($request)['rythme'] ?? '');
        if (!\in_array($mode, CourseEnrollment::PACES, true)) {
            return $this->jsonError('Rythme inconnu.', Response::HTTP_BAD_REQUEST, 'PACE_INVALID');
        }
        $enrollment = $this->catalog->enroll($user, $course)->setPace($mode);
        $this->em->flush();

        return $this->jsonNoStore(['rythme' => ['mode' => $enrollment->getPaceMode(), 'enPause' => $enrollment->isPacePaused()]]);
    }

    #[Route('/{slug}/{lessonSlug}', name: 'api_mobile_v1_academie_lesson', requirements: ['slug' => '[a-z0-9-]+', 'lessonSlug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function lesson(string $slug, string $lessonSlug): JsonResponse
    {
        $user = $this->currentMobileUser();
        $opened = $this->open($user, $slug, $lessonSlug);
        if ($opened instanceof JsonResponse) {
            return $opened;
        }
        [$course, $lesson, $enrollment] = $opened;
        [$previous, $next, $position, $total] = AcademyCatalog::neighbours($course, $lesson);
        $completion = $enrollment->completionFor($lesson);

        return $this->jsonNoStore(['lecon' => [
            'adresse' => $lesson->getSlug(),
            'titre' => $lesson->getTitle(),
            'parcours' => ['adresse' => $course->getSlug(), 'titre' => $course->getTitle(), 'lieu' => $course->getPlace()],
            'module' => $lesson->getModule()?->getTitle(),
            'position' => $position,
            'total' => $total,
            'minutes' => $lesson->getDurationMinutes(),
            'faite' => null !== $completion,
            'progression' => $this->progress($enrollment),
            'texteHtml' => '' !== trim($lesson->getContentMarkdown()) ? $this->renderer->toHtml($lesson->getContentMarkdown(), true) : null,
            'video' => $lesson->hasVideo() ? [
                'fournisseur' => VideoEmbed::providerLabel($lesson->getMediaProvider()),
                'lien' => VideoEmbed::watchUrl($lesson->getMediaProvider(), (string) $lesson->getMediaRef()),
            ] : null,
            'questions' => array_values(array_map(fn ($q) => [
                'id' => $q->getId(),
                'question' => $q->getQuestion(),
                'plusieurs' => $q->isMultiple(),
                'reponses' => array_map(fn (array $o) => ['id' => $o['id'], 'libelle' => $o['label']], $q->getOptions()),
                'bonnes' => $q->getCorrectOptionIds(),
                'explication' => $q->getExplanation(),
            ], $lesson->getQuestions()->toArray())),
            'pratique' => $this->practice($user, $course, $lesson, $completion),
            'notes' => array_map($this->note(...), $this->notes->forLesson($user, $lesson)),
            'plans' => array_map(fn (string $key, string $label) => ['cle' => $key, 'libelle' => $label], array_keys(AcademyPlaces::plans()), AcademyPlaces::plans()),
            'precedente' => $previous?->getSlug(),
            'suivante' => $next?->getSlug(),
        ]]);
    }

    #[Route('/{slug}/{lessonSlug}/terminer', name: 'api_mobile_v1_academie_complete', requirements: ['slug' => '[a-z0-9-]+', 'lessonSlug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function complete(Request $request, string $slug, string $lessonSlug): JsonResponse
    {
        $user = $this->currentMobileUser();
        $opened = $this->open($user, $slug, $lessonSlug);
        if ($opened instanceof JsonResponse) {
            return $opened;
        }
        [$course, $lesson, $enrollment] = $opened;
        $data = $this->decodeJson($request) ?? [];
        $toPlan = 'plan' === ($data['mode'] ?? 'sans');
        try {
            $result = $this->learning->complete($user, $lesson, $enrollment, $toPlan, [
                'title' => (string) ($data['texte'] ?? ''), 'detail' => (string) ($data['precisions'] ?? ''),
                'choice' => (string) ($data['choix'] ?? ''), 'destination' => (string) ($data['destination'] ?? ''),
                // Séance d'un parcours de plan : texte (raison d'être, vision) ou nouvelles lignes d'une rubrique
                'text' => (string) ($data['texte'] ?? ''), 'horizon' => (string) ($data['horizon'] ?? ''),
                'items' => \is_array($data['elements'] ?? null) ? $data['elements'] : [],
                'goals' => \is_array($data['objectifs'] ?? null) ? $data['objectifs'] : [],
            ]);
        } catch (PracticeRefused $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, 'PRACTICE_REFUSED');
        }
        $offer = $toPlan ? $this->learning->accountabilityOffer($user, $enrollment->completionFor($lesson)) : null;

        return $this->jsonNoStore([
            'enregistreDans' => $result['label'],
            'leconSuivante' => $enrollment->nextLesson()?->getSlug(),
            'parcoursTermine' => $enrollment->isCompleted(),
            'progression' => $this->progress($enrollment),
            'redevabilite' => null === $offer ? null : [
                'cible' => $offer['target'],
                'question' => 'Voulez-vous qu\'un Partenaire de Redevabilité veille sur '.$offer['label'].' ?',
            ],
        ]);
    }

    #[Route('/{slug}/{lessonSlug}/notes', name: 'api_mobile_v1_academie_note_add', requirements: ['slug' => '[a-z0-9-]+', 'lessonSlug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function addNote(Request $request, string $slug, string $lessonSlug): JsonResponse
    {
        $user = $this->currentMobileUser();
        $opened = $this->open($user, $slug, $lessonSlug);
        if ($opened instanceof JsonResponse) {
            return $opened;
        }
        [$course, $lesson] = $opened;
        $data = $this->decodeJson($request) ?? [];
        try {
            $link = $this->journal->addLessonNote($user, $lesson, (string) ($data['contenu'] ?? ''), (string) ($data['plan'] ?? '') ?: $course->getPrimaryPlan());
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, 'NOTE_INVALID');
        }

        return $this->jsonNoStore(['note' => $this->note($link)], Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function courseSummary(User $user, Course $course, ?CourseEnrollment $enrollment): array
    {
        $look = AcademyPlaces::look($course->getPlace());

        return [
            'adresse' => $course->getSlug(),
            'titre' => $course->getTitle(),
            'accroche' => $course->getSubtitle(),
            'lieu' => $course->getPlace(),
            'libelleLieu' => AcademyPlaces::label($course->getPlace()),
            'icone' => $look['icon'],
            'gratuit' => $course->isFree(),
            'reservePro' => $this->catalog->isLocked($user, $course),
            'enRevision' => !$course->isPublished(),
            'nombreLecons' => \count($course->getLessons(true)),
            'minutes' => $course->getEstimatedMinutes(),
            'progression' => null !== $enrollment ? $this->progress($enrollment) : null,
            'termine' => (bool) $enrollment?->isCompleted(),
            'vignette' => $this->images->coverUrl($course),
            'motVignette' => $course->getCoverWord(),
            'plansCouverts' => array_map(fn (array $p) => $this->plan($p), AcademyOverview::coveredPlans($course)),
        ];
    }

    /** Un des 6 plans tel que l'affichent la carte et la page d'un parcours (AcademyOverview). */
    private function plan(array $p): array
    {
        return ['cle' => $p['key'], 'libelle' => $p['label'], 'court' => $p['short'], 'formule' => $p['question'], 'icone' => $p['icon'],
            'principal' => $p['main'], 'couvert' => $p['covered'], 'exercices' => $p['exercises']];
    }

    /** @return array{faites: int, total: int, pourcentage: int} */
    private function progress(CourseEnrollment $enrollment): array
    {
        $p = $enrollment->progress();

        return ['faites' => $p['done'], 'total' => $p['total'], 'pourcentage' => $p['percent']];
    }

    /** @return array<string, mixed> */
    private function practice(User $user, Course $course, Lesson $lesson, $completion): array
    {
        $p = $this->learning->practice($user, $course, $lesson, $completion);
        $answer = $completion?->getResponse();
        $common = [
            'dejaEnregistre' => (bool) $completion?->hasCreatedEntity(),
            'reponse' => null === $answer ? null : ['texte' => $answer['title'] ?? '', 'precisions' => $answer['detail'] ?? null, 'choix' => $answer['choice'] ?? null,
                'destination' => isset($answer['target'], $answer['action']) ? $answer['target'].'|'.$answer['action'] : null],
        ];
        if ($p['fixed'] && isset($p['rubric'])) {
            $r = $p['rubric'];

            return $common + [
                'type' => 'seance',
                'consigne' => $lesson->getPracticePrompt(),
                'cible' => PracticeCatalog::label($p['target'], $p['action']),
                'bouton' => PracticeCatalog::button($p['target']),
                'rubrique' => [
                    'genre' => ['text' => 'texte', 'list' => 'liste', 'goals' => 'objectifs'][$r['kind']],
                    'libelle' => $r['label'],
                    'exemple' => $r['example'] ?? null,
                    'texte' => $r['text'] ?? null,
                    'horizon' => $r['horizon'] ?? null,
                    'elements' => $r['items'] ?? null,
                    'objectifs' => isset($r['goals']) ? array_map(fn (array $g) => ['libelle' => $g['label'], 'elements' => $g['items']], $r['goals']) : null,
                ],
                'blocage' => $p['blocker'],
            ];
        }
        if ($p['fixed']) {
            $form = $p['form'];

            return $common + [
                'type' => 'exercice',
                'consigne' => $lesson->getPracticePrompt(),
                'cible' => PracticeCatalog::label($p['target'], $p['action']),
                'bouton' => PracticeCatalog::button($p['target']),
                'libelleTexte' => $form['title'],
                'texteLong' => 'add_note' === $p['action'],
                'libellePrecisions' => $form['detail'],
                'choix' => null === $form['choice'] ? null : ['libelle' => $form['choice']['label'], 'defaut' => $form['choice']['default'],
                    'options' => array_map(fn (string $v, string $l) => ['valeur' => (string) $v, 'libelle' => $l], array_keys($form['choice']['options']), $form['choice']['options'])],
                'blocage' => $p['blocker'],
            ];
        }

        return $common + [
            'type' => 'point',
            'bouton' => 'Inscrire dans mon plan',
            'destinationParDefaut' => $p['default'],
            'destinations' => array_map(fn (string $v, array $d) => ['valeur' => $v, 'libelle' => $d['label'], 'blocage' => $d['blocker']], array_keys($p['destinations']), $p['destinations']),
        ];
    }

    /** @return array<string, mixed> */
    private function note(LessonNote $link): array
    {
        return [
            'id' => $link->getId(),
            'contenu' => $link->getNote()->getContent(),
            'plan' => $link->getPlanKey(),
            'libellePlan' => null !== $link->getPlanKey() ? AcademyPlaces::label($link->getPlanKey()) : null,
            'creeeLe' => $link->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    private function visibleCourse(User $user, string $slug): ?Course
    {
        $course = $this->courses->findOneBy(['slug' => $slug]);

        return null !== $course && $this->catalog->isVisible($user, $course) ? $course : null;
    }

    private function openableCourse(User $user, string $slug): Course|JsonResponse
    {
        $course = $this->visibleCourse($user, $slug);
        if (null === $course) {
            return $this->jsonError('Parcours introuvable.', Response::HTTP_NOT_FOUND, 'COURSE_NOT_FOUND');
        }
        if ($this->catalog->isLocked($user, $course)) {
            throw new ProRequiredException('academie');
        }

        return $course;
    }

    /** @return array{0: Course, 1: Lesson, 2: CourseEnrollment}|JsonResponse */
    private function open(User $user, string $slug, string $lessonSlug): array|JsonResponse
    {
        $course = $this->openableCourse($user, $slug);
        if ($course instanceof JsonResponse) {
            return $course;
        }
        $lesson = $this->catalog->findLesson($course, $lessonSlug);
        if (null === $lesson) {
            return $this->jsonError('Leçon introuvable.', Response::HTTP_NOT_FOUND, 'LESSON_NOT_FOUND');
        }

        return [$course, $lesson, $this->catalog->enroll($user, $course)];
    }
}
