<?php

namespace App\Methode\Command;

use App\Methode\Service\BookLibrary;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Découpe le manuscrit de l'auteur (un seul fichier Markdown) en chapitres affichés dans l'application
 * (content/livre, versionné et déployé avec le site).
 *
 * - Une section « ## » = un fichier (voir BookLibrary::CHAPTERS) ; la dédicace et la page auteur ne sont pas publiées.
 * - Le titre de partie (« # Partie … ») est repris en tête du premier chapitre de la partie.
 * - Les marqueurs de travail de l'auteur ([HISTOIRE PERSONNELLE], [À COMPLÉTER]) ne sont jamais montrés aux lecteurs.
 *
 * Le texte lui-même n'est pas modifié : c'est celui de l'auteur.
 */
#[AsCommand(name: 'app:livre:sync', description: 'Met à jour les chapitres du livre affichés dans l\'application')]
class SyncBookCommand extends Command
{
    private const SKIPPED = ['Dédicace', 'À propos de l\'auteur'];

    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'Manuscrit Markdown', $this->projectDir.'/../livre-et-ressources/sources/livre/manuscrit.md');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = (string) $input->getOption('source');
        if (!is_file($source)) {
            $io->error("Manuscrit introuvable : $source");

            return Command::FAILURE;
        }

        $sections = self::split((string) file_get_contents($source));
        $target = $this->projectDir.'/content/livre';
        $expected = array_keys(BookLibrary::CHAPTERS);
        $written = [];
        foreach ($sections as $file => $markdown) {
            if (!\in_array($file, $expected, true)) {
                $io->warning("Section sans place dans BookLibrary::CHAPTERS : $file");
                continue;
            }
            file_put_contents("$target/$file", $markdown);
            $written[] = $file;
        }
        foreach (glob("$target/*.md") as $old) {
            if ('README.md' !== basename($old) && !\in_array(basename($old), $written, true)) {
                unlink($old);
            }
        }
        if ($missing = array_diff($expected, $written)) {
            $io->warning('Chapitres absents du manuscrit : '.implode(', ', $missing));
        }
        $io->success(\count($written).' chapitre(s) mis à jour.');

        return Command::SUCCESS;
    }

    /** @return array<string, string> fichier => Markdown du chapitre */
    public static function split(string $manuscript): array
    {
        $out = [];
        $part = null;
        $current = null;
        $buffer = [];
        $flush = function () use (&$out, &$current, &$buffer): void {
            if (null !== $current) {
                $out[$current] = self::clean(implode("\n", $buffer));
            }
            $current = null;
            $buffer = [];
        };

        foreach (preg_split('/\R/', $manuscript) as $line) {
            if (preg_match('/^# (Partie .+)$/u', $line, $m)) {
                $flush();
                $part = trim($m[1]);
                continue;
            }
            if (preg_match('/^# /', $line)) { // titre du livre
                $flush();
                continue;
            }
            if (preg_match('/^## (.+)$/u', $line, $m)) {
                $flush();
                $title = trim($m[1]);
                $file = self::fileFor($title);
                if (null === $file) {
                    continue;
                }
                $current = $file;
                $buffer = ['# '.$title, ''];
                if ($part) {
                    $buffer[] = '**'.$part.'**';
                    $buffer[] = '';
                    $part = null;
                }
                continue;
            }
            if (null !== $current) {
                $buffer[] = $line;
            }
        }
        $flush();

        return $out;
    }

    private static function fileFor(string $title): ?string
    {
        foreach (self::SKIPPED as $skipped) {
            if (str_starts_with($title, $skipped)) {
                return null;
            }
        }

        return match (true) {
            str_starts_with($title, 'Comment lire') => '00_COMMENT_LIRE.md',
            str_starts_with($title, 'Introduction') => '01_INTRODUCTION.md',
            (bool) preg_match('/^Chapitre (\d+)/u', $title, $m) => sprintf('CHAP_%02d.md', (int) $m[1]),
            str_starts_with($title, 'Conclusion') => '90_CONCLUSION.md',
            str_starts_with($title, 'Annexes') => '91_ANNEXES.md',
            default => null,
        };
    }

    /** Retire les notes de travail de l'auteur, jamais destinées aux lecteurs. */
    private static function clean(string $markdown): string
    {
        // Encadrés entiers dont le titre est un marqueur en capitales : > **\[HISTOIRE PERSONNELLE\]** …,
        // > **\[À COMPLÉTER\]** …, > **\[TON ÉQUATION DU BUT, À CONFIRMER\]** …
        $markdown = preg_replace("/^>\\s*\\*\\*\\\\?\\[[\\p{Lu}\\s,'’:-]{6,}\\\\?\\]\\*\\*[^\\n]*\\n?/mu", '', $markdown);
        // Signature « Prénom NOM » en fin de citation : le nom de l'auteur n'est pas publié dans l'application
        $markdown = preg_replace('/(\*\*)[ \t]+\p{Lu}[\p{L}-]+ \p{Lu}{2,}[\p{Lu}-]*[ \t]*$/mu', '$1', (string) $markdown);
        // Marqueur dans une phrase : « \[À COMPLÉTER : question\] » => « question »
        $markdown = preg_replace('/\\\\?\[À COMPLÉTER\s*:\s*(.+?)\\\\?\]/u', '$1', (string) $markdown);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", (string) $markdown))."\n";
    }
}
