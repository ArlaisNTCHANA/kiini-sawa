<?php

namespace App\Tests\Shared;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fous de design (docs/design.md, audits du 17/11/2026) : l'interface n'a qu'une famille d'icônes (Lucide) et la
 * palette du livre. Un émoji dans un gabarit ou un message de l'application fait échouer ce test.
 */
class DesignRulesTest extends TestCase
{
    /** Pictogrammes (émojis et symboles colorés) ; ✓ ✗ → ← restent permis comme signes typographiques. */
    private const PICTOGRAM = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{2712}\x{2714}-\x{2716}\x{2718}-\x{27BF}\x{2B50}\x{2B06}\x{23F0}-\x{23FF}]/u';

    private function files(): iterable
    {
        $root = \dirname(__DIR__, 2);
        foreach (['templates', 'src'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $path = $file->getPathname();
                // Console (sorties de commandes) et emails : hors de l'interface web
                if (!preg_match('/\.(twig|php)$/', $path) || str_contains($path, '/Command/') || str_contains($path, '/email/')) {
                    continue;
                }
                yield $path => $root;
            }
        }
    }

    public function testNoEmojiInTheInterface(): void
    {
        $found = [];
        foreach ($this->files() as $path => $root) {
            foreach (file($path) as $n => $line) {
                if (preg_match(self::PICTOGRAM, $line)) {
                    $found[] = str_replace($root.'/', '', $path).':'.($n + 1);
                }
            }
        }
        $this->assertSame([], $found, "Émojis interdits (utiliser une icône Lucide : <i data-lucide=\"…\" class=\"ks-ico\"></i>) :\n".implode("\n", \array_slice($found, 0, 20)));
    }

    public function testNoOldTurquoiseInTheInterface(): void
    {
        $found = [];
        foreach ($this->files() as $path => $root) {
            if (!str_ends_with($path, '.twig')) {
                continue;
            }
            if (preg_match('/#0F766E|#0D9488|#115E59|#14B8A6/i', (string) file_get_contents($path))) {
                $found[] = str_replace($root.'/', '', $path);
            }
        }
        $this->assertSame([], $found, 'Turquoise abandonné : utiliser var(--primary) (palette du livre).');
    }
}
