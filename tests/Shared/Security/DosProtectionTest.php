<?php

namespace App\Tests\Shared\Security;

use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use App\Journal\Controller\JournalController;
use App\Journal\Entity\ReflectionNote;
use App\Notification\Service\ReminderService;
use App\Planner\Controller\PlannerController;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Audit de sécurité, lot 3 : protection contre l'épuisement de la mémoire et du processeur. */
class DosProtectionTest extends WebTestCase
{
    use AccountTestTrait;

    // ── Agenda : fenêtre bornée ──────────────────────────────────────────────

    public function testPlannerRejectsReversedAndOversizedWindows(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/api/planner/events?start=2026-12-31&end=2026-01-01');
        $this->assertResponseStatusCodeSame(400, 'fin avant début');
        $client->request('GET', '/api/planner/events?start=1900-01-01&end=2999-12-31');
        $this->assertResponseStatusCodeSame(400, 'plusieurs siècles');
        $client->request('GET', '/api/planner/events?start=2026-01-01&end=2027-12-31');
        $this->assertResponseStatusCodeSame(400, 'deux ans');
        $client->request('GET', '/api/planner/events?start=nimporte&end=quoi');
        $this->assertResponseStatusCodeSame(400, 'dates illisibles');
    }

    public function testPlannerAcceptsNormalWindowsUpToAYear(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        foreach (['', '?start=2026-11-01&end=2026-11-30', '?start=2026-11-01&end=2026-11-01', '?start=2026-01-01&end=2026-12-31', '?start=2026-01-01&end=2027-01-01'] as $query) {
            $client->request('GET', '/api/planner/events'.$query);
            $this->assertResponseIsSuccessful($query);
        }
        $this->assertSame(366, PlannerController::MAX_EVENTS_WINDOW_DAYS);
        $client->request('GET', '/api/planner/events?start=2026-01-01&end=2027-02-01');
        $this->assertResponseStatusCodeSame(400);
    }

    // ── Administrateurs : filtrés en SQL ─────────────────────────────────────

    public function testFindAdminsFiltersInSql(): void
    {
        static::createClient();
        $admin = $this->createUser();
        $admin->setRoles(['ROLE_ADMIN']);
        $plain = $this->createUser();
        $lookalike = $this->createUser();
        $lookalike->setRoles(['ROLE_ADMINISTRATOR_FAKE']);
        $this->em()->flush();

        $ids = array_map(fn (User $u) => $u->getId(), static::getContainer()->get(UserRepository::class)->findAdmins());
        $this->assertContains($admin->getId(), $ids);
        $this->assertNotContains($plain->getId(), $ids);
        $this->assertNotContains($lookalike->getId(), $ids, 'un rôle voisin n\'est pas ROLE_ADMIN');
    }

    public function testNoCodeLoadsTheWholeUserTable(): void
    {
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 3).'/src', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (str_ends_with($f->getFilename(), '.php') && preg_match('/getRepository\(User::class\)->findAll\(\)/', (string) file_get_contents($f->getPathname()))) {
                $offenders[] = $f->getFilename();
            }
        }
        $this->assertSame([], $offenders);
    }

    // ── Journal : pagination en base ─────────────────────────────────────────

    private function journalWith(int $n): User
    {
        $user = $this->createUser();
        $em = $this->em();
        for ($i = 1; $i <= $n; ++$i) {
            $em->persist((new ReflectionNote())->setUser($user)->setContent('Note '.$i)->setDate(new \DateTime("2026-01-01 +$i minutes")));
        }
        $em->flush();
        $em->clear();

        return $em->find(User::class, $user->getId());
    }

    public function testJournalPagesAreBoundedAndCorrect(): void
    {
        $client = static::createClient();
        $client->loginUser($this->journalWith(130));

        $client->request('GET', '/api/journal/entries?limit=100000&offset=0');
        $first = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(JournalController::MAX_PAGE_SIZE, $first['entries'], 'la page est plafonnée à 50');
        $this->assertSame(130, $first['totalCount']);
        $this->assertSame('Note 130', $first['entries'][0]['content'], 'les plus récentes d\'abord');

        $client->request('GET', '/api/journal/entries?limit=50&offset=50');
        $second = json_decode($client->getResponse()->getContent(), true);
        $client->request('GET', '/api/journal/entries?limit=50&offset=100');
        $third = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(30, $third['entries']);
        $all = array_merge(array_column($first['entries'], 'content'), array_column($second['entries'], 'content'), array_column($third['entries'], 'content'));
        $this->assertCount(130, array_unique($all), 'pas de doublon ni de trou entre les pages');
        $this->assertSame('Note 1', end($all));

        $client->request('GET', '/api/journal/entries?limit=-5&offset=-9');
        $bad = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $bad['entries'], 'limite minimale de 1, décalage ramené à 0');
        $client->request('GET', '/api/journal/entries?limit=10&offset=99999999');
        $this->assertSame([], json_decode($client->getResponse()->getContent(), true)['entries']);
    }

    public function testJournalQueriesUseALimitInsteadOfLoadingEverything(): void
    {
        $client = static::createClient();
        $client->loginUser($this->journalWith(60));
        $client->enableProfiler();
        $client->request('GET', '/api/journal/entries?limit=20&offset=0');
        $this->assertResponseIsSuccessful();
        $queries = [];
        foreach ($client->getProfile()->getCollector('db')->getQueries() as $connection) {
            foreach ($connection as $q) {
                $queries[] = $q['sql'];
            }
        }
        $notes = array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'FROM reflection_note') && !str_contains($sql, 'COUNT(')));
        $this->assertNotEmpty($notes);
        foreach ($notes as $sql) {
            $this->assertStringContainsString('LIMIT', $sql, 'les notes sont lues avec une limite');
        }
    }

    public function testJournalPageStartsWithTheFirstTwentyEntries(): void
    {
        $client = static::createClient();
        $client->loginUser($this->journalWith(45));
        $client->request('GET', '/journal');
        $this->assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent();
        $this->assertStringContainsString('Note 45', $html);
        $this->assertStringContainsString('Note 26', $html);
        $this->assertStringNotContainsString('Note 25"', $html, 'seules 20 entrées sont envoyées d\'emblée');
    }

    // ── Rappels : par lots ───────────────────────────────────────────────────

    public function testRemindersAreProcessedInBatchesWithoutSkippingAnyone(): void
    {
        static::createClient();
        $users = [];
        for ($i = 0; $i < 5; ++$i) {
            $users[] = $this->createUser();
        }
        $sent = static::getContainer()->get(ReminderService::class)->sendAll(new \DateTimeImmutable('2026-11-22'), 2);
        $this->assertGreaterThanOrEqual(5, $sent['revue hebdomadaire'], 'chaque utilisateur des trois lots reçoit son rappel du dimanche');
        $this->assertSame(200, ReminderService::BATCH_SIZE);
    }
}
