<?php

namespace App\Tests\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Application installable : manifeste valide, icônes, service worker, pages d'installation et hors ligne.
 */
class InstallableAppTest extends WebTestCase
{
    public function testManifestAndAssetsAreValid(): void
    {
        $public = \dirname(__DIR__, 2).'/public';
        $manifest = json_decode((string) file_get_contents($public.'/manifest.webmanifest'), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame('KIINI SAWA', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists($public.$icon['src']);
            [$w, $h] = getimagesize($public.$icon['src']);
            $this->assertSame($icon['sizes'], $w.'x'.$h, $icon['src']);
        }
        $this->assertFileExists($public.'/icons/apple-touch-icon.png');
        $sw = (string) file_get_contents($public.'/sw.js');
        $this->assertStringContainsString("'/hors-ligne'", $sw);
        $this->assertStringContainsString("request.mode === 'navigate'", $sw, 'Les pages ne sont jamais mises en cache');
    }

    public function testInstallAndOfflinePagesArePublicAndLinked(): void
    {
        $client = static::createClient();
        $client->request('GET', '/installer');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Installer l\'application');
        $this->assertSelectorExists('link[rel="manifest"][href="/manifest.webmanifest"]');
        $this->assertSelectorExists('link[rel="apple-touch-icon"]');

        $client->request('GET', '/hors-ligne');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'hors connexion');

        foreach (['/', '/login'] as $url) {
            $client->request('GET', $url);
            $this->assertSelectorExists('link[rel="manifest"]', $url);
        }
    }
}
