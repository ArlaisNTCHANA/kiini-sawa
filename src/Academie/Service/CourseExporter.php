<?php

namespace App\Academie\Service;

use App\Academie\Entity\Course;
use App\Academie\Entity\Lesson;
use App\Academie\Repository\LessonDiagramRepository;
use App\Shared\Service\PdfFactory;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

/**
 * Export d'un parcours de l'Académie depuis le studio (27/11/2026) :
 *
 * - **PDF** : le parcours mis en page, à imprimer ou envoyer ;
 * - **Word (.docx)** : à retravailler ou faire relire ;
 * - **fichier de parcours (.yaml)** : la sauvegarde complète, au format de content/academie/, rechargeable par
 *   `app:academie:import` (pour une copie, changer `slug` en tête du fichier).
 *
 * PDF et Word reprennent le texte tel que les membres le lisent (LessonMarkdown) : encadrés, citations, images du
 * parcours (converties en PNG), questions avec leurs bonnes réponses, exercice relié au plan, lien de la vidéo. Un schéma
 * n'est pas dessiné : son nom est indiqué.
 */
final class CourseExporter
{
    public const FORMATS = ['pdf' => 'PDF', 'docx' => 'Word', 'yaml' => 'Fichier de parcours'];

    public function __construct(
        private readonly AcademyImages $images,
        private readonly PdfFactory $pdf,
        private readonly Environment $twig,
        private readonly LessonDiagramRepository $diagrams,
    ) {
    }

    public function filename(Course $course, string $format): string
    {
        return 'parcours-'.$course->getSlug().'.'.$format;
    }

    public function contentType(string $format): string
    {
        return match ($format) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/x-yaml; charset=UTF-8',
        };
    }

    public function export(Course $course, string $format): string
    {
        return match ($format) {
            'pdf' => $this->pdf($course),
            'docx' => $this->docx($course),
            'yaml' => $this->yaml($course),
            default => throw new \InvalidArgumentException('Format inconnu : '.$format),
        };
    }

    // ---------- Fichier de parcours ----------

    /** @return array<string, mixed> les mêmes clés que les fichiers de content/academie/ (ImportCourseCommand::build) */
    public function data(Course $course): array
    {
        $d = ['slug' => $course->getSlug(), 'title' => $course->getTitle()];
        if (null !== $course->getSubtitle() && '' !== $course->getSubtitle()) {
            $d['subtitle'] = $course->getSubtitle();
        }
        if (null !== $course->getPlace()) {
            $d['place'] = $course->getPlace();
        }
        $d['kind'] = $course->getKind();
        if ([] !== $course->getExtraPlans()) {
            $d['plans'] = $course->getExtraPlans();
        }
        $d['access'] = $course->getAccessLevel();
        if (null !== $course->getCoverWord() && '' !== $course->getCoverWord()) {
            $d['cover_word'] = $course->getCoverWord();
        }
        if (null !== $course->getDescription() && '' !== trim($course->getDescription())) {
            $d['description'] = $this->block($course->getDescription());
        }
        foreach ($course->getModules() as $module) {
            $lessons = [];
            foreach ($module->getLessons() as $lesson) {
                $l = ['title' => $lesson->getTitle(), 'duration' => $lesson->getDurationMinutes()];
                if (!$lesson->isVisible()) {
                    $l['hidden'] = true;
                }
                if ($lesson->hasVideo()) {
                    $l['video'] = VideoEmbed::watchUrl($lesson->getMediaProvider(), $lesson->getMediaRef());
                }
                $l['content'] = $this->block($lesson->getContentMarkdown());
                if ($lesson->hasPractice()) {
                    $l['practice'] = array_filter(['target' => $lesson->getTargetPlan(), 'action' => $lesson->getTargetAction(), 'prompt' => $lesson->getPracticePrompt()], fn ($v) => null !== $v && '' !== $v);
                }
                foreach ($lesson->getQuestions() as $q) {
                    $ids = array_column($q->getOptions(), 'id');
                    $question = ['question' => $q->getQuestion(), 'answers' => array_column($q->getOptions(), 'label'),
                        'correct' => array_values(array_keys(array_filter($ids, fn (string $id) => $q->isCorrect($id))))];
                    if ('' !== trim($q->getExplanation())) {
                        $question['explanation'] = $q->getExplanation();
                    }
                    $l['questions'][] = $question;
                }
                $lessons[] = $l;
            }
            $d['modules'][] = ['title' => $module->getTitle(), 'lessons' => $lessons];
        }

        return $d;
    }

    public function yaml(Course $course): string
    {
        return '# Parcours « '.$course->getTitle().' », exporté de KIINI SAWA le '.(new \DateTimeImmutable())->format('d/m/Y')."\n"
            ."# Rechargeable dans le studio : php bin/console app:academie:import <ce fichier>\n"
            ."# Pour en faire une copie, changez d'abord la ligne « slug » (l'adresse du parcours).\n\n"
            .Yaml::dump($this->data($course), 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    /** Texte sur plusieurs lignes : fins de ligne propres, pour que le fichier garde des blocs lisibles. */
    private function block(string $text): string
    {
        return trim(preg_replace('/[ \t]+$/m', '', str_replace("\r\n", "\n", $text)) ?? $text)."\n";
    }

    // ---------- PDF ----------

    public function pdf(Course $course): string
    {
        $html = $practice = $video = [];
        foreach ($course->getModules() as $module) {
            foreach ($module->getLessons() as $lesson) {
                $id = $lesson->getId();
                $html[$id] = $this->lessonHtml($lesson, fn (string $png) => 'data:image/png;base64,'.base64_encode($png));
                $practice[$id] = PracticeCatalog::label($lesson->getTargetPlan(), $lesson->getTargetAction());
                $video[$id] = $lesson->hasVideo() ? VideoEmbed::watchUrl($lesson->getMediaProvider(), $lesson->getMediaRef()) : null;
            }
        }

        return $this->pdf->render($this->twig->render('academie/admin/export/course.pdf.twig', [
            'course' => $course, 'html' => $html, 'practice' => $practice, 'video' => $video,
        ]));
    }

    // ---------- Word ----------

    public function docx(Course $course): string
    {
        $tmp = [];
        // PhpWord échappe lui-même le texte (et celui des leçons lu par Html::addHtml)
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
        try {
            $word = new PhpWord();
            $word->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('fr-FR'));
            $word->setDefaultFontName('Calibri');
            $word->setDefaultFontSize(11);
            $word->setDefaultParagraphStyle(['spaceAfter' => 140, 'spacing' => 40]);
            $word->addTitleStyle(1, ['size' => 22, 'bold' => true, 'color' => '2E6B53'], ['spaceAfter' => 120]);
            $word->addTitleStyle(2, ['size' => 16, 'bold' => true, 'color' => '2E6B53'], ['spaceBefore' => 360, 'spaceAfter' => 120]);
            $word->addTitleStyle(3, ['size' => 13, 'bold' => true, 'color' => '1E293B'], ['spaceBefore' => 240, 'spaceAfter' => 80]);
            $word->getDocInfo()->setTitle($course->getTitle())->setCreator('KIINI SAWA');

            $section = $word->addSection(['marginTop' => 1200, 'marginBottom' => 1100, 'marginLeft' => 1150, 'marginRight' => 1150]);
            $section->addFooter()->addText($course->getTitle().' · KIINI SAWA', ['size' => 8, 'color' => '94A3B8']);
            $section->addTitle($this->xmlText($course->getTitle()), 1);
            if ($course->getSubtitle()) {
                $section->addText($this->xmlText($course->getSubtitle()), ['italic' => true, 'size' => 13, 'color' => '64748B']);
            }
            if ($course->getDescription()) {
                Html::addHtml($section, $this->wordHtml(LessonMarkdown::toHtml($course->getDescription())));
            }

            foreach ($course->getModules() as $m => $module) {
                $section->addTitle($this->xmlText('Module '.($m + 1).' · '.$module->getTitle()), 2);
                foreach ($module->getLessons() as $lesson) {
                    $section->addTitle($this->xmlText($lesson->getTitle()), 3);
                    $meta = $lesson->getDurationMinutes().' min'.($lesson->isVisible() ? '' : ' · leçon masquée aux membres');
                    $section->addText($this->xmlText($meta), ['size' => 9, 'color' => '64748B']);
                    if ($lesson->hasVideo()) {
                        $url = VideoEmbed::watchUrl($lesson->getMediaProvider(), $lesson->getMediaRef());
                        $section->addLink($url, $this->xmlText('Vidéo : '.$url), ['color' => '2E6B53', 'underline' => 'single']);
                    }
                    $html = $this->lessonHtml($lesson, function (string $png) use (&$tmp): string {
                        $file = tempnam(sys_get_temp_dir(), 'ks-img-');
                        file_put_contents($file, $png);
                        $tmp[] = $file;

                        return $file;
                    });
                    Html::addHtml($section, $this->wordHtml($html));
                    if ($lesson->hasPractice()) {
                        $box = $section->addTable(['borderSize' => 6, 'borderColor' => '2E6B53', 'cellMargin' => 120, 'width' => 100 * 50, 'unit' => 'pct']);
                        $cell = $box->addRow()->addCell(null, ['bgColor' => 'EEF3EF']);
                        $cell->addText($this->xmlText('Exercice · '.(PracticeCatalog::label($lesson->getTargetPlan(), $lesson->getTargetAction()) ?? '')), ['bold' => true, 'color' => '2E6B53']);
                        if ($lesson->getPracticePrompt()) {
                            $cell->addText($this->xmlText($lesson->getPracticePrompt()));
                        }
                        $section->addTextBreak();
                    }
                    if (\count($lesson->getQuestions()) > 0) {
                        $section->addText('Questions', ['bold' => true, 'size' => 12]);
                        foreach ($lesson->getQuestions() as $k => $q) {
                            $section->addText($this->xmlText(($k + 1).'. '.$q->getQuestion()), ['bold' => true]);
                            foreach ($q->getOptions() as $o) {
                                $section->addText($this->xmlText(($q->isCorrect($o['id']) ? '✓ ' : '○ ').$o['label'].($q->isCorrect($o['id']) ? ' (bonne réponse)' : '')), [], ['indentation' => ['left' => 360]]);
                            }
                            if ('' !== trim($q->getExplanation())) {
                                $section->addText($this->xmlText($q->getExplanation()), ['italic' => true, 'color' => '64748B'], ['indentation' => ['left' => 360]]);
                            }
                        }
                    }
                }
            }

            $out = tempnam(sys_get_temp_dir(), 'ks-docx-');
            $tmp[] = $out;
            IOFactory::createWriter($word, 'Word2007')->save($out);

            return (string) file_get_contents($out);
        } finally {
            foreach ($tmp as $file) {
                @unlink($file);
            }
        }
    }

    /** Texte simple pour PhpWord (qui l'échappe : voir setOutputEscapingEnabled). */
    private function xmlText(string $text): string
    {
        return $text;
    }

    /**
     * HTML des leçons → HTML que PhpWord sait lire : encadrés en cadre coloré, citations en retrait, sans icônes ni
     * case « J'ai pris ce temps ». Le tout en XHTML (PhpWord le lit comme du XML).
     */
    private function wordHtml(string $html): string
    {
        $html = preg_replace('#<i data-lucide="[^"]*"[^>]*></i>\s*#', '', $html) ?? $html;
        $html = preg_replace('#<label class="academie-mediter-fait">.*?</label>#s', '', $html) ?? $html;
        $html = preg_replace_callback('#<aside class="academie-encadre[^"]*">\s*<p class="academie-encadre__titre">(.*?)</p>(.*?)</aside>#s', fn (array $m) => '<table style="width: 480pt; border: 1px solid #2E6B53;"><tr><td style="width: 480pt; background-color: #EEF3EF;"><p><strong>'.trim($m[1]).'</strong></p>'.$m[2].'</td></tr></table><p></p>', $html) ?? $html;
        $html = preg_replace_callback('#<blockquote>(.*?)</blockquote>#s', function (array $m): string {
            $inner = preg_replace('#</?p>#', '', trim($m[1])) ?? $m[1];

            return '<p style="margin-left: 40px; font-style: italic; color: #475569;">'.trim($inner).'</p>';
        }, $html) ?? $html;
        $html = str_replace(['<br>', '<hr>'], ['<br />', '<hr />'], $html);
        // Entités HTML inconnues du XML (Parsedown n'en produit presque pas) : en caractères
        $html = preg_replace_callback('/&(?!amp;|lt;|gt;|quot;|apos;|#\d+;|#x[0-9a-f]+;)(\w+);/i', fn (array $m) => html_entity_decode($m[0], \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), $html) ?? $html;

        return $html;
    }

    // ---------- Commun ----------

    /**
     * Texte d'une leçon tel que les membres le lisent, prêt pour un export : chaque image du parcours devient un PNG
     * (`$image` reçoit le PNG et renvoie l'adresse à mettre dans la page), chaque schéma son nom.
     *
     * @param callable(string): string $image
     */
    private function lessonHtml(Lesson $lesson, callable $image): string
    {
        $html = LessonMarkdown::toHtml($lesson->getContentMarkdown(), fn (string $code) => '<p class="export-schema"><em>Schéma : '.htmlspecialchars($this->diagramLabel($code), \ENT_QUOTES).' (à voir dans l\'application)</em></p>');

        return preg_replace_callback('#<img src="/academie/images/(\d+)/([a-f0-9]{16})\.webp" alt="([^"]*)"[^>]*>#', function (array $m) use ($image): string {
            $png = $this->png((int) $m[1], $m[2]);

            if (null === $png) {
                return '';
            }
            // Largeur fixe (Word ignore le style) : 420 px, hauteur à l'échelle
            [$w, $h] = getimagesizefromstring($png) ?: [420, 280];
            $height = (int) round(420 * $h / max(1, $w));

            return '<img src="'.htmlspecialchars($image($png), \ENT_QUOTES).'" alt="'.$m[3].'" width="420" height="'.$height.'" style="width: 420px; height: '.$height.'px;" />';
        }, $html) ?? $html;
    }

    private function diagramLabel(string $code): string
    {
        return LessonRenderer::DIAGRAMS[$code] ?? $this->diagrams->findOneBy(['code' => $code])?->getTitle() ?? $code;
    }

    /** Une image du parcours (WebP) en PNG, ou null si elle n'existe plus. */
    private function png(int $courseId, string $name): ?string
    {
        $file = $this->images->file($courseId, $name);
        if (null === $file || !\function_exists('imagecreatefromwebp')) {
            return null;
        }
        $img = @imagecreatefromwebp($file);
        if (false === $img) {
            return null;
        }
        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
