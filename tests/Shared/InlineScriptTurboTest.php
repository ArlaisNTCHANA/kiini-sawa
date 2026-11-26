<?php

namespace App\Tests\Shared;

use PHPUnit\Framework\TestCase;

/**
 * Avec Turbo, passer d'une page à l'autre sans recharger rejoue les <script> en ligne dans la même fenêtre.
 * Un `const` ou un `let` au premier niveau d'un script provoque alors « Identifier 'x' has already been declared »
 * et tout le script s'arrête (vu en production entre deux assistants des plans, 25/11/2026).
 * Au premier niveau d'un script en ligne : `var`, `window.x = …` ou une fonction anonyme `(() => { … })()`.
 */
class InlineScriptTurboTest extends TestCase
{
    public function testNoTopLevelConstOrLetInInlineScripts(): void
    {
        $root = \dirname(__DIR__, 2).'/templates';
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (!preg_match_all('#<script(?![^>]*\bsrc=)(?![^>]*type="(?:module|application/(?:ld\+)?json)")[^>]*>(.*?)</script>#s', $source, $blocks)) {
                continue;
            }
            foreach ($blocks[1] as $script) {
                foreach ($this->topLevelDeclarations($script) as $name) {
                    $offenders[] = substr($file->getPathname(), \strlen($root) + 1).' : '.$name;
                }
            }
        }

        $this->assertSame([], $offenders, 'Déclaration const/let au premier niveau d\'un script en ligne (casse la navigation Turbo).');
    }

    /** @return list<string> */
    private function topLevelDeclarations(string $script): array
    {
        // Retire les balises Twig, les commentaires et les chaînes, pour ne compter que les accolades du code.
        $code = preg_replace(['#\{\#.*?\#\}#s', '#\{\{.*?\}\}#s', '#\{%.*?%\}#s'], ['', '0', ''], $script);
        $code = preg_replace(['#/\*.*?\*/#s', '#(?<![:\'"])//[^\n]*#'], '', (string) $code);
        $code = preg_replace(['#`(?:\\\\.|[^`\\\\])*`#s', '#"(?:\\\\.|[^"\\\\\n])*"#', "#'(?:\\\\.|[^'\\\\\n])*'#"], '""', (string) $code);

        $found = [];
        $depth = 0;
        foreach (explode("\n", (string) $code) as $line) {
            if (0 === $depth && preg_match('#^\s*(?:const|let)\s+([A-Za-z_$][\w$]*)#', $line, $m)) {
                $found[] = $m[1];
            }
            $depth += substr_count($line, '{') + substr_count($line, '(') + substr_count($line, '[')
                - substr_count($line, '}') - substr_count($line, ')') - substr_count($line, ']');
            $depth = max(0, $depth);
        }

        return $found;
    }
}
