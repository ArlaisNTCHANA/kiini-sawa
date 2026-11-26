<?php

namespace App\Academie\Controller\Admin;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseModule;
use App\Academie\Entity\Lesson;
use App\Academie\Entity\LessonQuestion;
use App\Academie\Repository\CourseRepository;
use App\Academie\Service\AcademyImages;
use App\Academie\Service\AcademyPlaces;
use App\Academie\Service\AcademyStudio;
use App\Academie\Service\PracticeCatalog;
use App\Academie\Service\VideoEmbed;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Studio auteur de l'Académie (docs/modules/academie.md) : le fondateur crée ses parcours, modules, leçons, questions
 * de discernement et exercices, les prévisualise comme un apprenant, les publie et les repasse en brouillon.
 * Toutes les actions qui modifient passent par POST avec le jeton CSRF « admin_academie ».
 */
#[Route('/admin/academie', name: 'admin_academie')]
#[IsGranted('ROLE_ADMIN')]
class AcademyAdminController extends AbstractController
{
    private const CSRF = 'admin_academie';

    public function __construct(private readonly EntityManagerInterface $em, private readonly CourseRepository $courses, private readonly AcademyImages $images)
    {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(e.course) AS cid, COUNT(e.id) AS enrolled, SUM(CASE WHEN e.completedAt IS NOT NULL THEN 1 ELSE 0 END) AS completed')
            ->from(\App\Academie\Entity\CourseEnrollment::class, 'e')
            ->groupBy('e.course')
            ->getQuery()->getArrayResult();
        $stats = [];
        foreach ($rows as $r) {
            $stats[(int) $r['cid']] = ['enrolled' => (int) $r['enrolled'], 'completed' => (int) $r['completed']];
        }

        return $this->render('academie/admin/index.html.twig', ['courses' => $this->courses->allOrdered(), 'stats' => $stats]);
    }

    // ── Parcours ──────────────────────────────────────────────────────────

    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    #[Route('/{id}', name: '_course', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function course(Request $request, AcademyImages $images, ?int $id = null): Response
    {
        $course = $id ? $this->find(Course::class, $id) : null;
        $errors = [];
        $data = $course ? [
            'title' => $course->getTitle(), 'slug' => $course->getSlug(), 'subtitle' => (string) $course->getSubtitle(),
            'description' => (string) $course->getDescription(), 'place' => (string) $course->getPlace(),
            'access' => $course->getAccessLevel(), 'sortOrder' => $course->getSortOrder(), 'kind' => $course->getKind(),
        ] : ['title' => '', 'slug' => '', 'subtitle' => '', 'description' => '', 'place' => 'VIE', 'access' => Course::ACCESS_PRO, 'sortOrder' => \count($this->courses->allOrdered()) + 1, 'kind' => Course::KIND_THEME];

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request);
            $p = $request->request;
            $data = [
                'title' => trim($p->getString('title')), 'slug' => trim($p->getString('slug')), 'subtitle' => trim($p->getString('subtitle')),
                'description' => trim($p->getString('description')), 'place' => $p->getString('place'),
                'access' => $p->getString('access'), 'sortOrder' => $p->getInt('sortOrder'),
                'kind' => $p->getString('kind', Course::KIND_THEME),
            ];
            $slug = AcademyStudio::slugify('' !== $data['slug'] ? $data['slug'] : $data['title']);
            if ('' === $data['title'] || mb_strlen($data['title']) > 160) { $errors[] = 'Le titre est obligatoire (160 caractères au plus).'; }
            if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) { $errors[] = 'Adresse invalide (3 à 80 caractères : lettres, chiffres, tirets).'; }
            if (mb_strlen($data['subtitle']) > 255) { $errors[] = 'L\'accroche est trop longue (255 caractères au plus).'; }
            if (!AcademyPlaces::isValid('' === $data['place'] ? null : $data['place'])) { $errors[] = 'Choisissez un plan dans la liste.'; }
            if (!isset(Course::ACCESS_LEVELS[$data['access']])) { $errors[] = 'Choisissez qui peut suivre ce parcours.'; }
            if (!isset(Course::KINDS[$data['kind']])) { $errors[] = 'Choisissez le genre du parcours.'; }
            if (Course::KIND_PLAN === $data['kind'] && !\in_array($data['place'], array_keys(\App\PlanDeVie\Service\PlanCatalog::PLANS), true)) { $errors[] = 'Un parcours de plan est rattaché à l\'un des 6 plans.'; }
            $other = $this->courses->findOneBy(['slug' => $slug]);
            if ($other && $other !== $course) { $errors[] = 'Cette adresse est déjà utilisée par un autre parcours.'; }

            if ([] === $errors) {
                $isNew = null === $course;
                $course ??= new Course();
                $course->setTitle($data['title'])->setSlug($slug)->setSubtitle($data['subtitle'] ?: null)
                    ->setDescription($data['description'] ?: null)->setPlace($data['place'] ?: null)
                    ->setAccessLevel($data['access'])->setSortOrder(max(0, $data['sortOrder']))->setKind($data['kind']);
                if ($isNew) {
                    $course->addModule((new CourseModule())->setTitle('Module 1'));
                }
                $this->em->persist($course);
                $this->em->flush();
                $this->addFlash('success', $isNew ? 'Parcours créé, en brouillon. Ajoutez maintenant ses leçons.' : 'Parcours enregistré.');

                return $this->redirectToRoute('admin_academie_course', ['id' => $course->getId()]);
            }
        }

        return $this->render('academie/admin/course.html.twig', [
            'course' => $course, 'data' => $data, 'errors' => $errors,
            'plans' => AcademyPlaces::plans(), 'accessLevels' => Course::ACCESS_LEVELS, 'kinds' => Course::KINDS,
            'problems' => $course ? AcademyStudio::publishProblems($course) : [],
            'canDelete' => $course && AcademyStudio::canDelete($course),
            'images' => $course ? $images->list($course->getId()) : [],
        ]);
    }

    #[Route('/{id}/statut', name: '_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $course = $this->find(Course::class, $id);
        if ('publier' === $request->request->getString('action')) {
            $problems = AcademyStudio::publishProblems($course);
            if ([] !== $problems) {
                foreach ($problems as $problem) {
                    $this->addFlash('error', $problem);
                }

                return $this->redirectToRoute('admin_academie_course', ['id' => $id]);
            }
            $course->publish();
            $this->addFlash('success', 'Parcours publié : il apparaît dans l\'Académie.');
        } else {
            $course->unpublish();
            $this->addFlash('success', 'Parcours repassé en brouillon : il a quitté le catalogue. Les membres qui l\'avaient commencé gardent leur progression et leurs notes.');
        }
        $this->em->flush();

        return $this->redirectToRoute('admin_academie_course', ['id' => $id]);
    }

    /**
     * Vignette du parcours (image envoyée ici ou déjà dans le studio, sinon vignette dessinée avec un mot) et plans
     * ajoutés à la main. L'image passe par AcademyImages : types et taille contrôlés, réenregistrée en WebP.
     */
    #[Route('/{id}/vignette', name: '_cover', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cover(Request $request, AcademyImages $images, int $id): Response
    {
        $this->checkCsrf($request);
        $course = $this->find(Course::class, $id);
        $p = $request->request;
        $file = $request->files->get('cover');
        $message = 'Vignette et plans enregistrés.';
        if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            try {
                $course->setCoverImage($images->store($course->getId(), $file));
                $message = 'Nouvelle vignette enregistrée.';
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirect($this->generateUrl('admin_academie_course', ['id' => $id]).'#vignette');
            }
        } else {
            $choice = $p->getString('image');
            if ('' === $choice) {
                $course->setCoverImage(null);
            } elseif (null !== $images->file($course->getId(), $choice)) {
                $course->setCoverImage($choice);
            }
        }
        $course->setCoverWord($p->getString('coverWord'));
        $plans = $p->all('plans');
        $course->setExtraPlans(array_values(array_filter($plans, 'is_string')));
        $this->em->flush();
        $this->addFlash('success', $message);

        return $this->redirect($this->generateUrl('admin_academie_course', ['id' => $id]).'#vignette');
    }

    /** Image envoyée pour les leçons du parcours (réenregistrée en WebP, voir AcademyImages). */
    #[Route('/{id}/images', name: '_image_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function uploadImage(Request $request, AcademyImages $images, int $id): Response
    {
        $this->checkCsrf($request);
        $course = $this->find(Course::class, $id);
        $wantsJson = str_contains((string) $request->headers->get('Accept', ''), 'application/json');
        $file = $request->files->get('image');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            if ($wantsJson) {
                return new JsonResponse(['error' => 'Choisissez une image à envoyer.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->addFlash('error', 'Choisissez une image à envoyer.');
        } else {
            try {
                $name = $images->store($course->getId(), $file);
                if ($wantsJson) {
                    return new JsonResponse(['name' => $name, 'path' => AcademyImages::publicPath($course->getId(), $name)]);
                }
                $this->addFlash('success', 'Image ajoutée. Copiez sa ligne et collez-la dans le texte d\'une leçon, à l\'endroit voulu.');

                return $this->redirect($this->generateUrl('admin_academie_course', ['id' => $id]).'#image-'.$name);
            } catch (\InvalidArgumentException $e) {
                if ($wantsJson) {
                    return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirect($this->generateUrl('admin_academie_course', ['id' => $id]).'#images');
    }

    #[Route('/{id}/images/{name}/supprimer', name: '_image_delete', requirements: ['id' => '\d+', 'name' => '[a-f0-9]{16}'], methods: ['POST'])]
    public function deleteImage(Request $request, AcademyImages $images, int $id, string $name): Response
    {
        $this->checkCsrf($request);
        $course = $this->find(Course::class, $id);
        $path = AcademyImages::publicPath($course->getId(), $name);
        $used = array_filter($course->getLessons(), fn (Lesson $l) => str_contains($l->getContentMarkdown(), $path));
        if ($course->getCoverImage() === $name) {
            $this->addFlash('error', 'Cette image sert de vignette au parcours. Choisissez d\'abord une autre vignette.');
        } elseif ([] !== $used) {
            $this->addFlash('error', 'Cette image est encore utilisée dans : '.implode(', ', array_map(fn (Lesson $l) => '« '.$l->getTitle().' »', $used)).'. Retirez-la d\'abord du texte.');
        } elseif ($images->delete($course->getId(), $name)) {
            $this->addFlash('success', 'Image supprimée.');
        }

        return $this->redirect($this->generateUrl('admin_academie_course', ['id' => $id]).'#images');
    }

    #[Route('/{id}/supprimer', name: '_course_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteCourse(Request $request, AcademyImages $images, int $id): Response
    {
        $this->checkCsrf($request);
        $course = $this->find(Course::class, $id);
        if (!AcademyStudio::canDelete($course)) {
            $this->addFlash('error', 'Ce parcours a déjà été publié : des membres ont pu le commencer. Repassez-le en brouillon plutôt que de le supprimer.');

            return $this->redirectToRoute('admin_academie_course', ['id' => $id]);
        }
        $this->em->remove($course);
        $this->em->flush();
        $images->deleteAll($id);
        $this->addFlash('success', 'Parcours supprimé.');

        return $this->redirectToRoute('admin_academie');
    }

    // ── Modules ───────────────────────────────────────────────────────────

    #[Route('/{id}/modules', name: '_module_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addModule(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $course = $this->find(Course::class, $id);
        $title = trim($request->request->getString('title'));
        $course->addModule((new CourseModule())->setTitle(mb_substr('' !== $title ? $title : 'Module '.(\count($course->getModules()) + 1), 0, 160)));
        $this->em->flush();
        $this->addFlash('success', 'Module ajouté.');

        return $this->redirectToRoute('admin_academie_course', ['id' => $id, '_fragment' => 'structure']);
    }

    #[Route('/module/{id}', name: '_module_rename', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function renameModule(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $module = $this->find(CourseModule::class, $id);
        $title = trim($request->request->getString('title'));
        if ('' !== $title) {
            $module->setTitle(mb_substr($title, 0, 160));
            $this->em->flush();
            $this->addFlash('success', 'Module renommé.');
        }

        return $this->redirectToRoute('admin_academie_course', ['id' => $module->getCourse()->getId(), '_fragment' => 'structure']);
    }

    #[Route('/module/{id}/deplacer', name: '_module_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function moveModule(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $module = $this->find(CourseModule::class, $id);
        AcademyStudio::move($module->getCourse()->getModules(), $module, 'monter' === $request->request->getString('sens') ? -1 : 1);
        $this->em->flush();

        return $this->redirectToRoute('admin_academie_course', ['id' => $module->getCourse()->getId(), '_fragment' => 'structure']);
    }

    #[Route('/module/{id}/supprimer', name: '_module_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteModule(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $module = $this->find(CourseModule::class, $id);
        $course = $module->getCourse();
        if (!$module->getLessons()->isEmpty() && !AcademyStudio::canDelete($course)) {
            $this->addFlash('error', 'Ce parcours a déjà été publié : on ne supprime pas un module qui contient des leçons. Masquez plutôt ses leçons.');
        } else {
            $course->removeModule($module);
            AcademyStudio::move($course->getModules(), $module, 0);
            $this->em->flush();
            $this->addFlash('success', 'Module supprimé.');
        }

        return $this->redirectToRoute('admin_academie_course', ['id' => $course->getId(), '_fragment' => 'structure']);
    }

    // ── Leçons ────────────────────────────────────────────────────────────

    #[Route('/module/{id}/lecon', name: '_lesson_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function newLesson(Request $request, int $id): Response
    {
        $module = $this->find(CourseModule::class, $id);

        return $this->lessonForm($request, $module, null);
    }

    #[Route('/lecon/{id}', name: '_lesson', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function lesson(Request $request, int $id): Response
    {
        $lesson = $this->find(Lesson::class, $id);

        return $this->lessonForm($request, $lesson->getModule(), $lesson);
    }

    private function lessonForm(Request $request, CourseModule $module, ?Lesson $lesson): Response
    {
        $course = $module->getCourse();
        $errors = [];
        $data = $lesson ? [
            'title' => $lesson->getTitle(), 'module' => $module->getId(), 'duration' => $lesson->getDurationMinutes(),
            'content' => $lesson->getContentMarkdown(),
            'video' => $lesson->hasVideo() ? VideoEmbed::watchUrl($lesson->getMediaProvider(), $lesson->getMediaRef()) : '',
            'practice' => $lesson->hasPractice() ? $lesson->getTargetPlan().'|'.$lesson->getTargetAction() : '',
            'prompt' => (string) $lesson->getPracticePrompt(), 'visible' => $lesson->isVisible(),
        ] : ['title' => '', 'module' => $module->getId(), 'duration' => 7, 'content' => '', 'video' => '', 'practice' => '', 'prompt' => '', 'visible' => !$course->isPublished()];

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request);
            $p = $request->request;
            $data = [
                'title' => trim($p->getString('title')), 'module' => $p->getInt('module'), 'duration' => $p->getInt('duration'),
                // Éditeur par blocs : les blocs sont convertis en texte, enregistré au même format qu'avant
                'content' => 'blocs' === $p->getString('editor')
                    ? \App\Academie\Service\LessonBlocks::toMarkdown(\App\Academie\Service\LessonBlocks::sanitize(json_decode($p->getString('blocks'), true)))
                    : str_replace("\r\n", "\n", $p->getString('content')),
                'video' => trim($p->getString('video')),
                'practice' => $p->getString('practice'), 'prompt' => trim($p->getString('prompt')), 'visible' => $p->getBoolean('visible'),
            ];
            $target = null;
            foreach ($course->getModules() as $m) {
                if ($m->getId() === $data['module']) {
                    $target = $m;
                }
            }
            $video = '' === $data['video'] ? null : VideoEmbed::parse($data['video']);
            [$plan, $action] = array_pad(explode('|', $data['practice'], 2), 2, '');
            if ('' === $data['title'] || mb_strlen($data['title']) > 160) { $errors[] = 'Le titre est obligatoire (160 caractères au plus).'; }
            if (null === $target) { $errors[] = 'Choisissez un module de ce parcours.'; }
            if ($data['duration'] < 1 || $data['duration'] > 60) { $errors[] = 'Durée : entre 1 et 60 minutes.'; }
            if ('' !== $data['video'] && null === $video) { $errors[] = 'Lien vidéo non reconnu : collez l\'adresse d\'une vidéo YouTube ou Vimeo (par exemple https://www.youtube.com/watch?v=… ou https://vimeo.com/…).'; }
            if ('' !== $data['practice'] && !PracticeCatalog::isValid($plan, $action)) { $errors[] = 'Choisissez un exercice dans la liste.'; }
            if ('' !== $data['practice'] && '' === $data['prompt']) { $errors[] = 'Écrivez la consigne de l\'exercice.'; }

            if ([] === $errors) {
                $isNew = null === $lesson;
                $lesson ??= (new Lesson())->setSlug(AcademyStudio::lessonSlug($course, $data['title']));
                if ($isNew) {
                    $target->addLesson($lesson);
                } elseif ($lesson->getModule() !== $target) {
                    // Changement de module : sans passer par removeLesson(), qui supprimerait la leçon (orphanRemoval).
                    $lesson->setModule($target)->setSortOrder(\count($target->getLessons()) + 1);
                }
                $lesson->setTitle($data['title'])->setDurationMinutes($data['duration'])->setContentMarkdown($data['content'])
                    ->setVideo($video[0] ?? null, $video[1] ?? null)
                    ->setPractice('' !== $data['practice'] ? $plan : null, '' !== $data['practice'] ? $action : null)
                    ->setPracticePrompt($data['prompt'] ?: null)->setVisible($data['visible']);
                $this->em->persist($lesson);
                $this->em->flush();
                $this->addFlash('success', $isNew ? 'Leçon créée.'.($lesson->isVisible() ? '' : ' Elle reste masquée aux apprenants tant que vous ne cochez pas « Visible ».') : 'Leçon enregistrée.');

                return $this->redirectToRoute('admin_academie_lesson', ['id' => $lesson->getId()]);
            }
        }

        $lessons = $course->getLessons();
        $index = $lesson ? array_search($lesson, $lessons, true) : false;

        return $this->render('academie/admin/lesson.html.twig', [
            'course' => $course, 'module' => $module, 'lesson' => $lesson, 'data' => $data, 'errors' => $errors,
            'previous' => false !== $index ? ($lessons[$index - 1] ?? null) : null,
            'next' => false !== $index ? ($lessons[$index + 1] ?? null) : null,
            'position' => false !== $index ? $index + 1 : null,
            'total' => \count($lessons),
            'practices' => PracticeCatalog::choices(), 'maxQuestions' => LessonQuestion::MAX_PER_LESSON,
            'canDelete' => $lesson && AcademyStudio::canDelete($course),
            'images' => $this->images->list($course->getId()), 'diagrams' => \App\Academie\Service\LessonRenderer::DIAGRAMS,
            'callouts' => \App\Academie\Service\LessonMarkdown::CALLOUTS,
            'customDiagrams' => $this->em->getRepository(\App\Academie\Entity\LessonDiagram::class)->findBy([], ['title' => 'ASC']),
            'editor' => $this->blockEditor($course, $data['content']),
        ]);
    }

    #[Route('/lecon/{id}/deplacer', name: '_lesson_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function moveLesson(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $lesson = $this->find(Lesson::class, $id);
        AcademyStudio::move($lesson->getModule()->getLessons(), $lesson, 'monter' === $request->request->getString('sens') ? -1 : 1);
        $this->em->flush();

        return $this->redirectToRoute('admin_academie_course', ['id' => $lesson->getCourse()->getId(), '_fragment' => 'structure']);
    }

    #[Route('/lecon/{id}/visibilite', name: '_lesson_visibility', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleLesson(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $lesson = $this->find(Lesson::class, $id);
        $lesson->setVisible(!$lesson->isVisible());
        $this->em->flush();
        $this->addFlash('success', $lesson->isVisible() ? 'Leçon visible des apprenants.' : 'Leçon masquée aux apprenants.');

        return $this->redirectToRoute('admin_academie_course', ['id' => $lesson->getCourse()->getId(), '_fragment' => 'structure']);
    }

    #[Route('/lecon/{id}/supprimer', name: '_lesson_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteLesson(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $lesson = $this->find(Lesson::class, $id);
        $course = $lesson->getCourse();
        if (!AcademyStudio::canDelete($course)) {
            $this->addFlash('error', 'Ce parcours a déjà été publié : masquez cette leçon plutôt que de la supprimer, pour garder les progressions des membres.');

            return $this->redirectToRoute('admin_academie_lesson', ['id' => $id]);
        }
        $module = $lesson->getModule();
        $module->removeLesson($lesson);
        AcademyStudio::move($module->getLessons(), $lesson, 0);
        $this->em->flush();
        $this->addFlash('success', 'Leçon supprimée.');

        return $this->redirectToRoute('admin_academie_course', ['id' => $course->getId(), '_fragment' => 'structure']);
    }

    /** Aperçu du texte pendant la rédaction (bouton « Prévisualiser le texte ») : même rendu sûr que pour l'apprenant. */
    #[Route('/apercu-texte', name: '_markdown', methods: ['POST'])]
    public function markdownPreview(Request $request, \App\Academie\Service\LessonRenderer $renderer): Response
    {
        $this->checkCsrf($request);
        $content = $request->request->has('blocks')
            ? \App\Academie\Service\LessonBlocks::toMarkdown(\App\Academie\Service\LessonBlocks::sanitize(json_decode($request->request->getString('blocks'), true)))
            : $request->request->getString('content');

        return new Response($renderer->toHtml(mb_substr($content, 0, 100000)), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** Passage d'un mode d'édition à l'autre : texte → blocs (`content`) ou blocs → texte (`blocks`), sans rien enregistrer. */
    #[Route('/blocs', name: '_blocks', methods: ['POST'])]
    public function convertBlocks(Request $request): Response
    {
        $this->checkCsrf($request);
        if ($request->request->has('blocks')) {
            return $this->json(['content' => \App\Academie\Service\LessonBlocks::toMarkdown(\App\Academie\Service\LessonBlocks::sanitize(json_decode($request->request->getString('blocks'), true)))]);
        }

        return $this->json(['blocks' => \App\Academie\Service\LessonBlocks::fromMarkdown(mb_substr(str_replace("\r\n", "\n", $request->request->getString('content')), 0, 100000))]);
    }

    // ── Questions de discernement ─────────────────────────────────────────

    #[Route('/lecon/{id}/question', name: '_question_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function newQuestion(Request $request, int $id): Response
    {
        $lesson = $this->find(Lesson::class, $id);
        if (\count($lesson->getQuestions()) >= LessonQuestion::MAX_PER_LESSON) {
            $this->addFlash('error', 'Une leçon compte '.LessonQuestion::MAX_PER_LESSON.' questions au plus.');

            return $this->redirectToRoute('admin_academie_lesson', ['id' => $id, '_fragment' => 'questions']);
        }

        return $this->questionForm($request, $lesson, null);
    }

    #[Route('/question/{id}', name: '_question', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function question(Request $request, int $id): Response
    {
        $question = $this->find(LessonQuestion::class, $id);

        return $this->questionForm($request, $question->getLesson(), $question);
    }

    private function questionForm(Request $request, Lesson $lesson, ?LessonQuestion $question): Response
    {
        $errors = [];
        $options = array_fill(0, LessonQuestion::MAX_OPTIONS, ['label' => '', 'correct' => false]);
        foreach ($question?->getOptions() ?? [] as $i => $o) {
            $options[$i] = ['label' => $o['label'], 'correct' => $question->isCorrect($o['id'])];
        }
        $data = ['question' => (string) $question?->getQuestion(), 'explanation' => (string) $question?->getExplanation(), 'options' => $options];

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request);
            $p = $request->request;
            $labels = $p->all('options');
            $correct = array_map('intval', array_keys($p->all('correct')));
            $data = ['question' => trim($p->getString('question')), 'explanation' => trim($p->getString('explanation')), 'options' => []];
            $kept = [];
            $keptCorrect = [];
            for ($i = 0; $i < LessonQuestion::MAX_OPTIONS; ++$i) {
                $label = trim(\is_string($labels[$i] ?? null) ? $labels[$i] : '');
                $isCorrect = \in_array($i, $correct, true);
                $data['options'][$i] = ['label' => $label, 'correct' => $isCorrect];
                if ('' !== $label) {
                    if ($isCorrect) {
                        $keptCorrect[] = \count($kept);
                    }
                    $kept[] = mb_substr($label, 0, 300);
                }
            }
            if ('' === $data['question']) { $errors[] = 'Écrivez la question (une mise en situation de préférence).'; }
            if (\count($kept) < 2) { $errors[] = 'Proposez au moins deux réponses.'; }
            if ([] === $keptCorrect) { $errors[] = 'Cochez au moins une bonne réponse.'; }
            if ('' === $data['explanation']) { $errors[] = 'Écrivez l\'explication : c\'est elle qui fait progresser, que la réponse soit juste ou non.'; }

            if ([] === $errors) {
                $isNew = null === $question;
                $question ??= new LessonQuestion();
                $question->setQuestion($data['question'])->setAnswers($kept, $keptCorrect)->setExplanation($data['explanation']);
                if ($isNew) {
                    $lesson->addQuestion($question);
                }
                $this->em->persist($question);
                $this->em->flush();
                $this->addFlash('success', $isNew ? 'Question ajoutée.' : 'Question enregistrée.');

                return $this->redirectToRoute('admin_academie_lesson', ['id' => $lesson->getId(), '_fragment' => 'questions']);
            }
        }

        return $this->render('academie/admin/question.html.twig', [
            'course' => $lesson->getCourse(), 'lesson' => $lesson, 'question' => $question, 'data' => $data, 'errors' => $errors,
        ]);
    }

    #[Route('/question/{id}/deplacer', name: '_question_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function moveQuestion(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $question = $this->find(LessonQuestion::class, $id);
        AcademyStudio::move($question->getLesson()->getQuestions(), $question, 'monter' === $request->request->getString('sens') ? -1 : 1);
        $this->em->flush();

        return $this->redirectToRoute('admin_academie_lesson', ['id' => $question->getLesson()->getId(), '_fragment' => 'questions']);
    }

    #[Route('/question/{id}/supprimer', name: '_question_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteQuestion(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $question = $this->find(LessonQuestion::class, $id);
        $lesson = $question->getLesson();
        $lesson->removeQuestion($question);
        AcademyStudio::move($lesson->getQuestions(), $question, 0);
        $this->em->flush();
        $this->addFlash('success', 'Question supprimée.');

        return $this->redirectToRoute('admin_academie_lesson', ['id' => $lesson->getId(), '_fragment' => 'questions']);
    }

    // ── Aperçu comme un apprenant ─────────────────────────────────────────

    #[Route('/{id}/apercu', name: '_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(int $id): Response
    {
        $course = $this->find(Course::class, $id);

        return $this->render('academie/admin/preview_course.html.twig', ['course' => $course]);
    }

    #[Route('/lecon/{id}/apercu', name: '_lesson_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function previewLesson(int $id): Response
    {
        $lesson = $this->find(Lesson::class, $id);
        $lessons = $lesson->getCourse()->getLessons();
        $i = array_search($lesson, $lessons, true);

        return $this->render('academie/admin/preview_lesson.html.twig', [
            'course' => $lesson->getCourse(), 'lesson' => $lesson,
            'previous' => $lessons[$i - 1] ?? null, 'next' => $lessons[$i + 1] ?? null,
            'position' => $i + 1, 'total' => \count($lessons),
        ]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    /** Liste JSON des schémas (atelier + code) pour actualiser le sélecteur dans une leçon sans recharger la page. */
    #[Route('/schemas-liste', name: '_diagrams_json', methods: ['GET'])]
    public function diagramsJson(): JsonResponse
    {
        return new JsonResponse(['diagrams' => $this->diagramOptions()]);
    }

    /** @return list<array{code: string, label: string, perso: bool}> */
    private function diagramOptions(): array
    {
        $diagrams = [];
        foreach ($this->em->getRepository(\App\Academie\Entity\LessonDiagram::class)->findBy([], ['title' => 'ASC']) as $d) {
            $diagrams[] = ['code' => $d->getCode(), 'label' => $d->getTitle(), 'perso' => true];
        }
        foreach (\App\Academie\Service\LessonRenderer::DIAGRAMS as $code => $label) {
            $diagrams[] = ['code' => $code, 'label' => $label, 'perso' => false];
        }

        return $diagrams;
    }

    /** Données de l'éditeur par blocs : les blocs de la leçon et ce qu'on peut y placer (encadrés, schémas, images). */
    private function blockEditor(Course $course, string $content): array
    {
        return [
            'blocks' => \App\Academie\Service\LessonBlocks::fromMarkdown($content),
            'callouts' => array_map(fn (string $code, array $c) => ['code' => $code, 'icon' => $c[0], 'label' => $c[1]], array_keys(\App\Academie\Service\LessonMarkdown::CALLOUTS), \App\Academie\Service\LessonMarkdown::CALLOUTS),
            'diagrams' => $this->diagramOptions(),
            'images' => array_column($this->images->list($course->getId()), 'path'),
        ];
    }

    private function find(string $class, int $id): object
    {
        return $this->em->find($class, $id) ?? throw $this->createNotFoundException();
    }

    private function checkCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF, $request->request->getString('_csrf_token'))) {
            throw new AccessDeniedHttpException('La page a expiré, merci de recharger et de réessayer.');
        }
    }
}
