<?php

namespace App\Tests\Admin;

use App\Admin\Service\SiteHealth;
use App\Compte\Repository\UserRepository;
use App\Tests\Compte\AccountTestTrait;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Admin › Réglages › Santé du site (27/11/2026) : la tournée des rappels arrêtée est signalée, l'état général suit le
 * pire point, la page montre aussi le contrôle avant ouverture, et la commande app:launch:check fonctionne toujours.
 */
class SiteHealthTest extends WebTestCase
{
    use AccountTestTrait;

    private function reminders(?int $lastRun): array
    {
        $cache = static::getContainer()->get('cache.app');
        \assert($cache instanceof CacheItemPoolInterface);
        $item = $cache->getItem('reminders_last_run');
        null === $lastRun ? $cache->deleteItem('reminders_last_run') : $cache->save($item->set($lastRun));
        $checks = static::getContainer()->get(SiteHealth::class)->checks();

        return array_values(array_filter($checks, fn ($c) => str_starts_with($c['label'], 'Rappels')))[0];
    }

    public function testStoppedRemindersAreFlaggedAndThePageShowsBothLists(): void
    {
        $client = static::createClient();
        $this->assertSame('problem', $this->reminders(time() - 4 * 86400)['state']);
        $this->assertSame('ok', $this->reminders(time() - 3600)['state']);
        $health = static::getContainer()->get(SiteHealth::class);
        $this->assertSame('problem', $health->overall([['state' => 'ok'], ['state' => 'problem'], ['state' => 'warning']]));
        $this->assertSame('warning', $health->overall([['state' => 'ok'], ['state' => 'warning']]));

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/sante');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Sauvegarde de la base');
        $this->assertSelectorTextContains('main', 'Contrôle avant ouverture');
        $this->assertSelectorTextContains('main', 'Base de données joignable');

        $tester = new CommandTester((new Application(static::$kernel))->find('app:launch:check'));
        $tester->execute([]);
        $this->assertStringContainsString('Base de données joignable', $tester->getDisplay());
    }

    public function testMembersCannotSeeTheHealthPage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/admin/sante');
        $this->assertResponseStatusCodeSame(403);
    }
}
