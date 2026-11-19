<?php

namespace App\Tests\Shared;

use PHPUnit\Framework\TestCase;

/** Audit de sécurité, lot 4 : les scripts d'exploitation ne laissent ni sauvegarde ni secrets lisibles par tous. */
class OpsScriptsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 2);
    }

    public function testBackupScriptIsPrivateByConstruction(): void
    {
        $src = (string) file_get_contents($this->root.'/bin/ops/sauvegarde.sh');
        $this->assertMatchesRegularExpression('/^umask 077$/m', $src);
        $this->assertLessThan(strpos($src, 'mkdir -p'), strpos($src, 'umask 077'), 'umask avant toute création de fichier');
        $this->assertStringContainsString('chmod 600 "$file"', $src);
        $this->assertStringContainsString('chmod 700 "$DEST"', $src);
    }

    public function testBackupScriptReallyProducesPrivateFiles(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR || !is_executable('/bin/bash')) {
            $this->markTestSkipped('bash requis');
        }
        $tmp = sys_get_temp_dir().'/kiini-backup-'.bin2hex(random_bytes(4));
        mkdir($tmp.'/bin', 0777, true);
        file_put_contents($tmp.'/bin/mysqldump', "#!/usr/bin/env bash\necho 'CREATE TABLE t (id int);'\n");
        chmod($tmp.'/bin/mysqldump', 0755);
        $dest = $tmp.'/sauvegardes';
        try {
            // Un masque permissif volontaire : c'est le script qui doit restreindre les droits
            $cmd = sprintf('umask 022; PATH=%s:$PATH DATABASE_URL=%s /bin/bash %s %s 2>&1', escapeshellarg($tmp.'/bin'), escapeshellarg('mysql://u:p@127.0.0.1:3306/base'), escapeshellarg($this->root.'/bin/ops/sauvegarde.sh'), escapeshellarg($dest));
            exec($cmd, $out, $code);
            $this->assertSame(0, $code, implode("\n", $out));
            $files = glob($dest.'/kiini-sawa-*.sql.gz') ?: [];
            $this->assertCount(1, $files);
            $this->assertSame('600', substr(sprintf('%o', fileperms($files[0])), -3), 'sauvegarde lisible par son propriétaire seulement');
            $this->assertSame('700', substr(sprintf('%o', fileperms($dest)), -3), 'dossier fermé aux autres');
        } finally {
            array_map('unlink', glob($dest.'/*') ?: []);
            @rmdir($dest);
            @unlink($tmp.'/bin/mysqldump');
            @rmdir($tmp.'/bin');
            @rmdir($tmp);
        }
    }

    public function testCompiledEnvironmentFileIsRestrictedRightAfterDumpEnv(): void
    {
        foreach (['mise-a-jour.sh' => 'run "$(command -v "$COMPOSER")" dump-env prod', 'configurer-emails.sh' => 'run "$(command -v composer)" dump-env prod >/dev/null'] as $script => $dump) {
            $src = (string) file_get_contents($this->root.'/bin/ops/'.$script);
            $lines = array_map('trim', explode("\n", $src));
            $i = array_search($dump, $lines, true);
            $this->assertNotFalse($i, $script.' : ligne dump-env introuvable');
            $following = \array_slice($lines, $i + 1, 3);
            $this->assertContains('chmod 600 .env.local.php', $following, $script.' : chmod juste après dump-env');
        }
    }

    public function testScriptsAreValidBash(): void
    {
        foreach (['sauvegarde.sh', 'mise-a-jour.sh', 'configurer-emails.sh'] as $script) {
            exec('bash -n '.escapeshellarg($this->root.'/bin/ops/'.$script).' 2>&1', $out, $code);
            $this->assertSame(0, $code, $script.' : '.implode("\n", $out));
        }
    }
}
