<?php

namespace App\Tests\Shared;

use PHPUnit\Framework\TestCase;

/** Depuis le 18/11/2026 l'application vit sur kiinisawa.com : plus aucune référence au sous-domaine « app. ». */
class CanonicalDomainTest extends TestCase
{
    public function testNoReferenceToOldSubdomain(): void
    {
        $root = dirname(__DIR__, 2);
        $found = [];
        foreach (['templates', 'src', 'config'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && str_contains((string) file_get_contents($file->getPathname()), 'app.kiinisawa.com')) {
                    $found[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }
        $this->assertSame([], $found, 'Utiliser kiinisawa.com, pas app.kiinisawa.com.');
    }
}
