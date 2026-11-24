<?php

namespace App\Academie\Command;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseModule;
use App\Academie\Entity\Lesson;
use App\Academie\Entity\LessonQuestion;
use App\Academie\Repository\CourseRepository;
use App\Academie\Service\AcademyStudio;
use App\Academie\Service\PracticeCatalog;
use App\PlanDeVie\Service\PlanCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Charge un parcours écrit dans un fichier (content/academie/*.yaml) **en brouillon** dans le studio de l'Académie.
 * Ensuite, tout se relit et se publie dans Admin › Académie ; le fichier ne sert qu'au premier chargement.
 *
 * Un parcours de même adresse n'est jamais écrasé, sauf avec --remplacer et seulement s'il n'a jamais été publié
 * (personne n'a pu le commencer). --verifier contrôle le fichier sans rien enregistrer.
 */
#[AsCommand(name: 'app:academie:import', description: 'Charge un parcours (fichier YAML) en brouillon dans l\'Académie')]
class ImportCourseCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CourseRepository $courses,
        private readonly \App\Academie\Service\AcademyImages $images,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du fichier YAML du parcours')
            ->addOption('remplacer', null, InputOption::VALUE_NONE, 'Remplace un brouillon de même adresse jamais publié')
            ->addOption('verifier', null, InputOption::VALUE_NONE, 'Vérifie le fichier sans rien enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('fichier');
        if (!is_file($path)) {
            $io->error('Fichier introuvable : '.$path);

            return Command::FAILURE;
        }
        try {
            $data = Yaml::parseFile($path);
            $course = self::build(\is_array($data) ? $data : []);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $lessons = $course->getLessons();
        $summary = sprintf('« %s » : %d modules, %d leçons, %d questions, environ %d minutes.', $course->getTitle(), \count($course->getModules()),
            \count($lessons), array_sum(array_map(fn (Lesson $l) => \count($l->getQuestions()), $lessons)), $course->getEstimatedMinutes());
        if ($input->getOption('verifier')) {
            $io->success('Fichier valable. '.$summary);

            return Command::SUCCESS;
        }

        $existing = $this->courses->findOneBy(['slug' => $course->getSlug()]);
        if ($existing) {
            if (!$input->getOption('remplacer') || !AcademyStudio::canDelete($existing)) {
                $io->error($existing->wasEverPublished()
                    ? 'Un parcours publié porte déjà cette adresse : il n\'est jamais remplacé. Modifiez-le dans le studio.'
                    : 'Un brouillon porte déjà cette adresse. Relancez avec --remplacer pour le remplacer.');

                return Command::FAILURE;
            }
            if ($problem = $this->editedInStudio($existing)) {
                $io->error($problem.' Il n\'est pas remplacé, pour ne pas perdre ce travail.');

                return Command::FAILURE;
            }
            $this->em->remove($existing);
            $this->em->flush();
        }
        $course->setSortOrder(\count($this->courses->findAll()) + 1);
        $this->em->persist($course);
        $this->em->flush();
        $io->success('Parcours chargé en brouillon. '.$summary.' À relire et publier dans Admin › Académie.');

        return Command::SUCCESS;
    }

    /** Le brouillon a-t-il été retouché dans le studio depuis son chargement (leçon modifiée, image ajoutée) ? */
    private function editedInStudio(Course $course): ?string
    {
        if ([] !== $this->images->list((int) $course->getId())) {
            return 'Ce brouillon a des images envoyées dans le studio.';
        }
        $limit = $course->getCreatedAt()->modify('+2 minutes');
        foreach ($course->getLessons() as $lesson) {
            if ($lesson->getUpdatedAt() > $limit || $course->getUpdatedAt() > $limit) {
                return 'Ce brouillon a été modifié dans le studio après son chargement.';
            }
        }

        return null;
    }

    /** Construit le parcours (non enregistré) à partir du fichier, en refusant toute valeur que le studio refuserait. */
    public static function build(array $d): Course
    {
        $title = trim((string) ($d['title'] ?? ''));
        if ('' === $title) {
            throw new \InvalidArgumentException('Le parcours n\'a pas de titre (title).');
        }
        $place = $d['place'] ?? null;
        if (null !== $place && 'COUPLE' !== $place && !isset(PlanCatalog::PLANS[$place])) {
            throw new \InvalidArgumentException('Rattachement inconnu (place) : '.$place);
        }
        $access = $d['access'] ?? Course::ACCESS_PRO;
        if (!isset(Course::ACCESS_LEVELS[$access])) {
            throw new \InvalidArgumentException('Accès inconnu (access) : '.$access.' (FREE ou PRO).');
        }
        $course = (new Course())->setTitle(mb_substr($title, 0, 160))->setSlug(AcademyStudio::slugify((string) ($d['slug'] ?? $title)))
            ->setSubtitle(isset($d['subtitle']) ? trim((string) $d['subtitle']) : null)->setDescription(isset($d['description']) ? trim((string) $d['description']) : null)
            ->setPlace($place)->setAccessLevel($access);

        foreach ($d['modules'] ?? [] as $m => $moduleData) {
            $module = (new CourseModule())->setTitle(trim((string) ($moduleData['title'] ?? 'Module '.($m + 1))));
            $course->addModule($module);
            foreach ($moduleData['lessons'] ?? [] as $lessonData) {
                $where = '« '.($lessonData['title'] ?? '?').' »';
                $lesson = (new Lesson())->setTitle(mb_substr(trim((string) ($lessonData['title'] ?? '')), 0, 160))
                    ->setContentMarkdown(trim((string) ($lessonData['content'] ?? '')))
                    ->setDurationMinutes(max(1, (int) ($lessonData['duration'] ?? 5)))->setVisible(true);
                if ('' === $lesson->getTitle() || '' === $lesson->getContentMarkdown()) {
                    throw new \InvalidArgumentException('Leçon sans titre ou sans texte : '.$where);
                }
                $module->addLesson($lesson);
                $lesson->setSlug(AcademyStudio::lessonSlug($course, $lesson->getTitle(), $lesson));

                if (isset($lessonData['practice'])) {
                    $p = $lessonData['practice'];
                    if (!PracticeCatalog::isValid((string) ($p['target'] ?? ''), (string) ($p['action'] ?? ''))) {
                        throw new \InvalidArgumentException('Exercice inconnu dans '.$where.' (voir PracticeCatalog::TARGETS).');
                    }
                    $lesson->setPractice($p['target'], $p['action'])->setPracticePrompt(trim((string) ($p['prompt'] ?? '')) ?: null);
                }

                $questions = $lessonData['questions'] ?? [];
                if (\count($questions) > LessonQuestion::MAX_PER_LESSON) {
                    throw new \InvalidArgumentException('Plus de '.LessonQuestion::MAX_PER_LESSON.' questions dans '.$where);
                }
                foreach ($questions as $q) {
                    $answers = array_values(array_map(fn ($a) => trim((string) $a), $q['answers'] ?? []));
                    $correct = array_values(array_map('intval', $q['correct'] ?? []));
                    if (\count($answers) < 2 || \count($answers) > LessonQuestion::MAX_OPTIONS || [] === $correct
                        || [] !== array_filter($correct, fn (int $i) => $i < 0 || $i >= \count($answers)) || '' === trim((string) ($q['question'] ?? ''))) {
                        throw new \InvalidArgumentException('Question mal formée dans '.$where.' : de 2 à '.LessonQuestion::MAX_OPTIONS.' réponses et au moins une bonne réponse.');
                    }
                    $lesson->addQuestion((new LessonQuestion())->setQuestion(trim((string) $q['question']))
                        ->setAnswers($answers, $correct)->setExplanation(trim((string) ($q['explanation'] ?? ''))));
                }
            }
        }
        if ([] === $course->getLessons()) {
            throw new \InvalidArgumentException('Le parcours ne contient aucune leçon.');
        }

        return $course;
    }
}
