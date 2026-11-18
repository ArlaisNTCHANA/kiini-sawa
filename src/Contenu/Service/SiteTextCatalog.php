<?php

namespace App\Contenu\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Le catalogue des textes modifiables : ce que le code contient aujourd'hui (le texte d'origine de chaque identifiant).
 *  - Textes courts : appels `{{ t('code', 'texte d\'origine') }}` trouvés dans `templates/` ;
 *  - Documents longs : fichiers Markdown `content/textes/<code avec / à la place des points>.md` (pages légales, emails…).
 * Le parcours des gabarits ne sert qu'à l'administration (liste, édition) : l'affichage d'une page n'y touche pas.
 */
final class SiteTextCatalog
{
    public const FORMATS = ['text' => 'Texte simple', 'inline' => 'Texte avec mise en forme (gras, italique, lien)', 'markdown' => 'Document (titres, paragraphes, listes, liens)'];

    /** @var ?array<string, array{default: string, format: string, source: string}> */
    private ?array $all = null;

    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $dir)
    {
    }

    /** @return array<string, array{default: string, format: string, source: string}> */
    public function all(): array
    {
        if (null !== $this->all) {
            return $this->all;
        }
        $all = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir.'/templates', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (!str_contains($source, 't(')) {
                continue;
            }
            preg_match_all('/\bt\(\s*([\'"])([a-z0-9_.\-]+)\1\s*,\s*([\'"])((?:\\\\.|(?!\3).)*)\3(?:\s*,\s*([\'"])(text|inline|markdown)\5)?/su', $source, $m, \PREG_SET_ORDER);
            foreach ($m as $hit) {
                $default = str_replace(['\\'.$hit[3], '\\\\'], [$hit[3], '\\'], $hit[4]);
                $all[$hit[2]] ??= ['default' => $default, 'format' => ($hit[6] ?? '') ?: 'text', 'source' => substr($file->getPathname(), \strlen($this->dir) + 1)];
            }
        }
        foreach (glob($this->dir.'/content/textes/*/*.md') ?: [] as $f) {
            $code = basename(\dirname($f)).'.'.basename($f, '.md');
            $all[$code] = ['default' => rtrim((string) file_get_contents($f)), 'format' => 'markdown', 'source' => substr($f, \strlen($this->dir) + 1)];
        }
        ksort($all);

        return $this->all = $all;
    }

    public function find(string $code): ?array
    {
        return $this->all()[$code] ?? null;
    }

    /** Texte d'origine d'un document long, sans parcourir les gabarits. */
    public function documentDefault(string $code): ?string
    {
        $file = $this->dir.'/content/textes/'.str_replace('.', '/', $code).'.md';

        return is_file($file) ? rtrim((string) file_get_contents($file)) : null;
    }

    public static function group(string $code): string
    {
        return explode('.', $code)[0];
    }
}
