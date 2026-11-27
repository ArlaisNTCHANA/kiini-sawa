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
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        private readonly \App\Academie\Repository\LessonDiagramRepository $diagramRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du fichier YAML du parcours')
            ->addOption('remplacer', null, InputOption::VALUE_NONE, 'Remplace un brouillon de même adresse jamais publié')
            ->addOption('verifier', null, InputOption::VALUE_NONE, 'Vérifie le fichier sans rien enregistrer')
            ->addOption('textes', null, InputOption::VALUE_NONE, 'Met à jour le texte des leçons d\'un parcours déjà chargé (même publié), sans toucher à la progression des membres');
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
            $data = \is_array($data) ? $data : [];
            $course = self::build($data);
            $coverFile = self::coverFile($data, \dirname($path));
            $diagrams = self::diagrams($data);
            $illustrations = self::illustrationFiles($course, \dirname($path));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $lessons = $course->getLessons();
        $summary = sprintf('« %s » : %d modules, %d leçons, %d questions, environ %d minutes.', $course->getTitle(), \count($course->getModules()),
            \count($lessons), array_sum(array_map(fn (Lesson $l) => \count($l->getQuestions()), $lessons)), $course->getEstimatedMinutes());
        $summary .= ([] !== $diagrams ? ' '.\count($diagrams).' schéma(s).' : '').([] !== $illustrations ? ' '.\count($illustrations).' illustration(s).' : '');
        if ($input->getOption('verifier')) {
            $io->success('Fichier valable. '.$summary);

            return Command::SUCCESS;
        }

        $existing = $this->courses->findOneBy(['slug' => $course->getSlug()]);
        if ($input->getOption('textes')) {
            return $this->updateTexts($io, $course, $existing);
        }
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
        // Schémas fournis avec le parcours : créés dans l'atelier s'ils n'existent pas ; jamais écrasés (le fondateur a pu
        // les retoucher)
        foreach ($diagrams as $code => $diagram) {
            if (isset(\App\Academie\Service\LessonRenderer::DIAGRAMS[$code]) || null !== $this->diagramRepository->findOneBy(['code' => $code])) {
                $io->note('Schéma « '.$code.' » déjà présent dans l\'atelier : gardé tel quel.');
                continue;
            }
            $this->em->persist((new \App\Academie\Entity\LessonDiagram())->setCode($code)->setTitle($diagram['title'])->setModel($diagram['model'])->setData($diagram['data']));
        }
        $course->setSortOrder(\count($this->courses->findAll()) + 1);
        $this->em->persist($course);
        $this->em->flush();
        // Illustrations fournies avec le parcours (fichiers à côté du YAML) : enregistrées comme si elles avaient été
        // envoyées dans le studio, puis leur adresse remplace le chemin du fichier dans le texte
        if ([] !== $illustrations) {
            $map = [];
            foreach ($illustrations as $relative => $file) {
                $map['('.$relative.')'] = '('.\App\Academie\Service\AcademyImages::publicPath((int) $course->getId(), $this->images->store((int) $course->getId(), new UploadedFile($file, basename($file), null, null, true))).')';
            }
            foreach ($course->getLessons() as $lesson) {
                $lesson->setContentMarkdown(strtr($lesson->getContentMarkdown(), $map));
            }
            $course->setDescription(null !== $course->getDescription() ? strtr($course->getDescription(), $map) : null);
            $this->em->flush();
        }
        if (null !== $coverFile) {
            try {
                $course->setCoverImage($this->images->store((int) $course->getId(), new UploadedFile($coverFile, basename($coverFile), null, null, true)));
                $this->em->flush();
            } catch (\InvalidArgumentException $e) {
                $io->warning('Vignette non chargée : '.$e->getMessage().' Le parcours est chargé ; ajoutez la vignette dans le studio.');
            }
        }
        $io->success('Parcours chargé en brouillon. '.$summary.' À relire et publier dans Admin › Académie.');

        return Command::SUCCESS;
    }

    /**
     * Remplace seulement le texte des leçons (retrouvées par leur adresse) : mêmes leçons, mêmes identifiants, donc
     * progression, notes et exercices des membres intacts. Refusé si une leçon manque ou a été retouchée dans le studio
     * après le chargement : on ne perd jamais un travail fait à la main.
     */
    private function updateTexts(SymfonyStyle $io, Course $fromFile, ?Course $existing): int
    {
        if (null === $existing) {
            $io->error('Aucun parcours ne porte cette adresse : chargez-le d\'abord (sans --textes).');

            return Command::FAILURE;
        }
        $bySlug = [];
        foreach ($existing->getLessons() as $lesson) {
            $bySlug[$lesson->getSlug()] = $lesson;
        }
        $limit = $existing->getCreatedAt()->modify('+2 minutes');
        $changes = [];
        foreach ($fromFile->getLessons() as $new) {
            $current = $bySlug[$new->getSlug()] ?? null;
            if (null === $current) {
                $io->error('Leçon introuvable dans le parcours en ligne : « '.$new->getTitle().' ». Rien n\'a été modifié.');

                return Command::FAILURE;
            }
            if ($current->getUpdatedAt() > $limit) {
                $io->error('La leçon « '.$current->getTitle().' » a été modifiée dans le studio après le chargement. Rien n\'a été modifié, pour ne pas perdre ce travail.');

                return Command::FAILURE;
            }
            if ($current->getContentMarkdown() !== $new->getContentMarkdown()) {
                $changes[] = [$current, $new->getContentMarkdown()];
            }
        }
        foreach ($changes as [$lesson, $text]) {
            $lesson->setContentMarkdown($text);
        }
        $this->em->flush();
        $io->success(\count($changes).' leçon(s) mise(s) à jour. Progression, notes et exercices des membres sont intacts.');

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

    /**
     * Vignette indiquée par `cover:` : un fichier image **local**, à côté du fichier YAML (jamais une adresse internet).
     * Vérifiée ici ; elle est réenregistrée en WebP par AcademyImages au chargement.
     */
    public static function coverFile(array $d, string $dir): ?string
    {
        $cover = trim((string) ($d['cover'] ?? ''));
        if ('' === $cover) {
            return null;
        }
        if (str_contains($cover, '://') || str_starts_with($cover, '//') || str_starts_with($cover, '/') || str_contains($cover, '..')) {
            throw new \InvalidArgumentException('Vignette (cover) : indiquez un fichier image placé à côté du fichier du parcours, jamais une adresse internet.');
        }
        if (!preg_match('/\.(jpe?g|png|webp)$/i', $cover)) {
            throw new \InvalidArgumentException('Vignette (cover) : image JPEG, PNG ou WebP seulement.');
        }
        $file = $dir.'/'.$cover;
        if (!is_file($file)) {
            throw new \InvalidArgumentException('Vignette (cover) introuvable : '.$cover);
        }
        if (filesize($file) > \App\Academie\Service\AcademyImages::MAX_BYTES) {
            throw new \InvalidArgumentException('Vignette (cover) trop lourde : 10 Mo au plus.');
        }

        return $file;
    }

    /**
     * Schémas de l'atelier fournis avec le parcours (`diagrams:`), vérifiés comme dans l'atelier (DiagramModels).
     *
     * @return array<string, array{title: string, model: string, data: array<string, mixed>}> code => schéma
     */
    public static function diagrams(array $d): array
    {
        $out = [];
        foreach ($d['diagrams'] ?? [] as $raw) {
            $code = (string) ($raw['code'] ?? '');
            $title = trim((string) ($raw['title'] ?? ''));
            if (!preg_match('/^[a-z0-9-]{1,80}$/', $code) || '' === $title) {
                throw new \InvalidArgumentException('Schéma (diagrams) : chaque schéma a un code (minuscules, chiffres, tirets) et un titre.');
            }
            try {
                $data = \App\Academie\Service\DiagramModels::normalize((string) ($raw['model'] ?? ''), $raw);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('Schéma « '.$code.' » : '.$e->getMessage());
            }
            $out[$code] = ['title' => mb_substr($title, 0, 120), 'model' => (string) $raw['model'], 'data' => $data];
        }

        return $out;
    }

    /**
     * Illustrations placées dans le texte par un chemin de fichier local, à côté du YAML : `![description](illustrations/x.png)`.
     * Vérifiées comme la vignette (JPEG, PNG ou WebP, 10 Mo au plus, jamais une adresse internet).
     *
     * @return array<string, string> chemin écrit dans le texte => fichier sur le disque
     */
    public static function illustrationFiles(Course $course, string $dir): array
    {
        $text = (string) $course->getDescription();
        foreach ($course->getLessons() as $lesson) {
            $text .= "\n".$lesson->getContentMarkdown();
        }
        preg_match_all('/!\[[^\]]*\]\(([^)\s]+)\)/', $text, $m);
        $files = [];
        foreach (array_unique($m[1]) as $relative) {
            if (str_starts_with($relative, '/academie/images/')) {
                continue; // déjà une image du studio
            }
            try {
                $files[$relative] = (string) self::coverFile(['cover' => $relative], $dir);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('Illustration « '.$relative.' » : '.str_replace('Vignette (cover) ', '', $e->getMessage()));
            }
        }

        return $files;
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
            ->setPlace($place)->setAccessLevel($access)->setCoverWord(isset($d['cover_word']) ? (string) $d['cover_word'] : null);
        $kind = strtoupper((string) ($d['kind'] ?? Course::KIND_THEME));
        if (!isset(Course::KINDS[$kind]) || (Course::KIND_PLAN === $kind && !isset(PlanCatalog::PLANS[(string) $place]))) {
            throw new \InvalidArgumentException('Genre inconnu (kind) : THEME, ou PLAN avec un des 6 plans en place.');
        }
        $course->setKind($kind);
        $plans = $d['plans'] ?? [];
        if (!\is_array($plans) || [] !== array_diff($plans, array_keys(PlanCatalog::PLANS))) {
            throw new \InvalidArgumentException('Plans couverts inconnus (plans) : utilisez les clés VIE, CROISSANCE, DEVELOPPEMENT, HYGIENE, INTENDANCE, TRANSMISSION.');
        }
        $course->setExtraPlans(array_values($plans));

        foreach ($d['modules'] ?? [] as $m => $moduleData) {
            $module = (new CourseModule())->setTitle(trim((string) ($moduleData['title'] ?? 'Module '.($m + 1))));
            $course->addModule($module);
            foreach ($moduleData['lessons'] ?? [] as $lessonData) {
                $where = '« '.($lessonData['title'] ?? '?').' »';
                $lesson = (new Lesson())->setTitle(mb_substr(trim((string) ($lessonData['title'] ?? '')), 0, 160))
                    ->setContentMarkdown(trim((string) ($lessonData['content'] ?? '')))
                    ->setDurationMinutes(max(1, (int) ($lessonData['duration'] ?? 5)))->setVisible(true);
                // Fichier exporté du studio (CourseExporter) : vidéo et leçon masquée reviennent aussi
                if (isset($lessonData['video']) && '' !== trim((string) $lessonData['video'])) {
                    $video = \App\Academie\Service\VideoEmbed::parse((string) $lessonData['video']);
                    if (null === $video) {
                        throw new \InvalidArgumentException('Lien de vidéo non reconnu dans '.$where.' (YouTube ou Vimeo).');
                    }
                    $lesson->setVideo($video[0], $video[1]);
                }
                if (!empty($lessonData['hidden'])) {
                    $lesson->setVisible(false);
                }
                if ('' === $lesson->getTitle() || ('' === $lesson->getContentMarkdown() && !$lesson->hasVideo())) {
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
