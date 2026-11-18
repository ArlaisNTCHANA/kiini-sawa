<?php

namespace App\Tests\Rapide;

use App\Compte\Entity\User;
use App\Journal\Entity\ReflectionNote;
use App\PlanDHygiene\Entity\DailyLog;
use App\Rapide\Service\QuickDay;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** « Aujourd'hui en un geste » : l'état du jour et la réception des saisies faites hors ligne (sans doublon, sans faux jour). */
class QuickDayTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;
    private User $user;
    private int $habitId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->user = $this->createUser(pro: true);
        $em = $this->em();
        $habit = (new Habit())->setUser($this->user)->setTitle('Prière du matin')->setFrequency(HabitFrequency::DAILY);
        $em->persist($habit);
        $em->flush();
        $this->habitId = $habit->getId();
        $em->clear(); // l'utilisateur est relu comme dans une vraie requête (ses rituels compris)
        $this->client->loginUser($em->find(User::class, $this->user->getId()));
    }

    private function sync(array $ops): array
    {
        $this->client->request('POST', '/api/rapide/sync', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode(['ops' => $ops]));

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function op(string $type, array $fields = [], ?string $uid = null, ?string $date = null): array
    {
        return ['uid' => $uid ?? bin2hex(random_bytes(8)), 'type' => $type, 'date' => $date ?? date('Y-m-d'), 'at' => date(\DATE_ATOM)] + $fields;
    }

    public function testThePageIsTheSameForEveryoneAndContainsNoPersonalData(): void
    {
        $this->client->request('GET', '/rapide');
        $this->assertResponseIsSuccessful();
        $loggedIn = $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString($this->user->getDisplayName(), $loggedIn);
        $this->assertStringNotContainsString($this->user->getEmail(), $loggedIn);

        $this->client->restart();
        $this->client->request('GET', '/rapide');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('indexedDB', $this->client->getResponse()->getContent());
    }

    public function testTheApiNeedsALoggedInUser(): void
    {
        $this->client->restart();
        $this->client->request('GET', '/api/rapide/aujourdhui');
        $this->assertResponseStatusCodeSame(401);
        $this->client->request('POST', '/api/rapide/sync', server: ['CONTENT_TYPE' => 'application/json'], content: '{"ops":[]}');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testSnapshotListsTheDay(): void
    {
        $this->client->request('GET', '/api/rapide/aujourdhui');
        $this->assertResponseIsSuccessful();
        $snap = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(date('Y-m-d'), $snap['date']);
        $this->assertSame([['id' => $this->habitId, 'title' => 'Prière du matin', 'frequency' => 'DAILY', 'done' => false]], $snap['habits']);
        $this->assertNull($snap['review']);
        $this->assertArrayHasKey('title', $snap['next']);
        $this->assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testHabitLogIsAppliedOnceEvenIfSentTwice(): void
    {
        $op = $this->op('habit.log', ['habit' => $this->habitId, 'status' => true], 'fixed-uid-0001');
        $first = $this->sync([$op]);
        $this->assertSame(['fixed-uid-0001' => 'ok'], $first['results']);
        $this->assertTrue($first['snapshot']['habits'][0]['done']);

        $again = $this->sync([$op]);
        $this->assertSame(['fixed-uid-0001' => 'deja'], $again['results'], 'réseau coupé après l\'envoi : le renvoi ne double rien');

        $off = $this->sync([$this->op('habit.log', ['habit' => $this->habitId, 'status' => false])]);
        $this->assertFalse($off['snapshot']['habits'][0]['done']);
        $this->assertCount(1, $this->em()->getRepository(Habit::class)->find($this->habitId)->getLogs(), 'un seul enregistrement par jour');
    }

    public function testAnotherUsersHabitAndBadDatesAreRefused(): void
    {
        $other = $this->createUser();
        $habit = (new Habit())->setUser($other)->setTitle('Autre')->setFrequency(HabitFrequency::DAILY);
        $this->em()->persist($habit);
        $this->em()->flush();

        $r = $this->sync([
            $this->op('habit.log', ['habit' => $habit->getId(), 'status' => true], 'uid-other-0001'),
            $this->op('habit.log', ['habit' => $this->habitId, 'status' => true], 'uid-old-000001', date('Y-m-d', strtotime('-30 days'))),
            $this->op('habit.log', ['habit' => $this->habitId, 'status' => true], 'uid-future-001', date('Y-m-d', strtotime('+10 days'))),
            $this->op('habit.log', ['habit' => $this->habitId, 'status' => true], 'uid-baddate-01', '2026-13-45'),
            $this->op('inconnu', [], 'uid-unknown-01'),
            ['uid' => 'x', 'type' => 'habit.log'],
        ])['results'];
        $this->assertSame(['uid-other-0001' => 'refusee', 'uid-old-000001' => 'refusee', 'uid-future-001' => 'refusee', 'uid-baddate-01' => 'refusee', 'uid-unknown-01' => 'refusee'], $r);
        $this->assertCount(0, $this->em()->getRepository(Habit::class)->find($habit->getId())->getLogs());
    }

    public function testAYesterdayEntryKeepsItsDay(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->sync([$this->op('habit.log', ['habit' => $this->habitId, 'status' => true], null, $yesterday)]);
        $log = $this->em()->getRepository(Habit::class)->find($this->habitId)->getLogs()->first();
        $this->assertSame($yesterday, $log->getDate()->format('Y-m-d'));
    }

    public function testReviewIsSavedOnceAndUpdatedNotDuplicated(): void
    {
        $this->sync([$this->op('review.save', ['intention' => 'Écouter davantage', 'kept' => true, 'energy' => 4, 'mood' => 5, 'stress' => 9, 'gratitude' => 'Ma famille'])]);
        $this->sync([$this->op('review.save', ['intention' => '', 'kept' => false, 'energy' => 2, 'mood' => 3, 'stress' => 1, 'gratitude' => 'Un ami'])]);
        $logs = $this->em()->getRepository(DailyLog::class)->findAll();
        $mine = array_values(array_filter($logs, fn (DailyLog $l) => $l->getPlanDHygiene()->getUser()->getId() === $this->user->getId()));
        $this->assertCount(1, $mine);
        $this->assertSame('Écouter davantage', $mine[0]->getIntention(), 'une intention vide n\'efface pas celle déjà saisie');
        $this->assertFalse($mine[0]->isIntentionKept());
        $this->assertSame(2, $mine[0]->getEnergyLevel());
        $this->assertSame(1, $mine[0]->getStressLevel());
        $this->assertSame('Un ami', $mine[0]->getGratitude());
        $snap = $this->sync([])['snapshot'];
        $this->assertSame('Un ami', $snap['review']['gratitude']);
    }

    public function testJournalEntryIsCreatedWithItsTypeAndIgnoredWhenEmpty(): void
    {
        $r = $this->sync([
            $this->op('journal.add', ['content' => '  Une idée pour la saison  ', 'entryType' => 'IDEA'], 'uid-journal-001'),
            $this->op('journal.add', ['content' => '   '], 'uid-journal-002'),
        ])['results'];
        $this->assertSame(['uid-journal-001' => 'ok', 'uid-journal-002' => 'refusee'], $r);
        $notes = $this->em()->getRepository(ReflectionNote::class)->findBy(['user' => $this->user->getId()]);
        $this->assertCount(1, $notes);
        $this->assertSame('Une idée pour la saison', $notes[0]->getContent());
        $this->assertSame('IDEA', $notes[0]->getType()->value);
        $this->assertSame('Une idée pour la saison', $this->sync([])['snapshot']['journal'][0]['text']);
    }

    public function testOnlyTheFirstHundredOperationsAreHandled(): void
    {
        $ops = [];
        for ($i = 0; $i < QuickDay::MAX_OPERATIONS + 5; ++$i) {
            $ops[] = $this->op('journal.add', ['content' => 'n'.$i], sprintf('uid-bulk-%05d', $i));
        }
        $this->assertCount(QuickDay::MAX_OPERATIONS, $this->sync($ops)['results']);
    }

    public function testTheInstalledAppKeepsTheQuickShellForOfflineUse(): void
    {
        $sw = file_get_contents(\dirname(__DIR__, 2).'/public/sw.js');
        $this->assertStringContainsString("'/rapide'", $sw);
        $this->assertStringContainsString('QUICK_FALLBACK', $sw);
        $manifest = json_decode(file_get_contents(\dirname(__DIR__, 2).'/public/manifest.webmanifest'), true);
        $this->assertContains('/rapide', array_column($manifest['shortcuts'], 'url'));
    }
}
