<?php

namespace App\Tests\Shared\Security;

use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use App\Journal\Entity\ReflectionNote;
use App\Planner\Entity\PlannerTask;
use App\Rituels\Entity\Habit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Isolation des données : un utilisateur ne peut ni lire, ni modifier,
 * ni supprimer les données d'un autre utilisateur, même en devinant les identifiants.
 */
class DataIsolationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private User $intruder;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->owner = static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com');

        $this->intruder = (new User())
            ->setEmail('intrus-'.uniqid().'@exemple.org')
            ->setPassword('non-utilise')
            ->setRoles([]);
        $this->em->persist($this->intruder);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $intruder = $this->em->find(User::class, $this->intruder->getId());
        if ($intruder) {
            $this->em->remove($intruder);
            $this->em->flush();
        }
        parent::tearDown();
    }

    private function json(string $method, string $uri, array $body = []): int
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body));

        return $this->client->getResponse()->getStatusCode();
    }

    /** Crée des données pour le propriétaire via l'API, puis renvoie leurs identifiants. */
    private function createOwnerData(): array
    {
        $this->client->loginUser($this->owner);
        $this->json('POST', '/api/journal/entries/create', ['content' => 'Note privée du propriétaire']);
        $this->json('POST', '/api/planner/task/create', ['title' => 'Tâche privée', 'date' => '2026-11-20']);

        $note = $this->em->getRepository(ReflectionNote::class)->findOneBy(['user' => $this->owner], ['id' => 'DESC']);
        $task = $this->em->getRepository(PlannerTask::class)->findOneBy(['user' => $this->owner], ['id' => 'DESC']);
        $habit = $this->em->getRepository(Habit::class)->findOneBy(['user' => $this->owner]);

        return [$note->getId(), $task->getId(), $habit?->getId()];
    }

    public function testIntruderCannotTouchOwnerData(): void
    {
        [$noteId, $taskId, $habitId] = $this->createOwnerData();
        $this->client->loginUser($this->intruder);

        $attempts = [
            ['DELETE', "/api/journal/entries/$noteId", []],
            ['DELETE', "/api/planner/task/$taskId/delete", []],
            ['POST', "/api/planner/task/$taskId/update", ['title' => 'Piraté']],
            ['POST', "/api/planner/toggle/TASK/$taskId", []],
        ];
        if ($habitId) {
            $attempts[] = ['POST', "/api/habits/$habitId/log", ['status' => true]];
            $attempts[] = ['POST', "/api/habits/$habitId/rooting", ['rootingIntention' => 'Piraté']];
            $attempts[] = ['DELETE', "/api/habits/$habitId", []];
        }

        foreach ($attempts as [$method, $uri, $body]) {
            $status = $this->json($method, $uri, $body);
            $this->assertContains($status, [403, 404], "$method $uri doit être refusé à un autre utilisateur (reçu $status)");
        }

        $this->em->clear();
        $this->assertNotNull($this->em->find(ReflectionNote::class, $noteId), 'La note du propriétaire existe toujours');
        $task = $this->em->find(PlannerTask::class, $taskId);
        $this->assertNotNull($task, 'La tâche du propriétaire existe toujours');
        $this->assertSame('Tâche privée', $task->getTitle());
        $this->assertFalse($task->isCompleted());
        if ($habitId) {
            $habit = $this->em->find(Habit::class, $habitId);
            $this->assertNotNull($habit, 'Le rituel du propriétaire existe toujours');
            $this->assertNotSame('Piraté', $habit->getRootingIntention());
        }

        // Nettoyage
        $this->em->remove($this->em->find(ReflectionNote::class, $noteId));
        $this->em->remove($task);
        $this->em->flush();
    }

    public function testIntruderCannotReadOwnerJournalOrSharedData(): void
    {
        [$noteId] = $this->createOwnerData();
        $this->client->loginUser($this->intruder);

        $this->client->request('GET', '/api/journal/entries');
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('Note privée du propriétaire', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/social/partner/'.$this->owner->getId().'/shared-data');
        $this->assertResponseStatusCodeSame(403);

        $this->em->remove($this->em->find(ReflectionNote::class, $noteId));
        $this->em->flush();
    }

    public function testNonAdminCannotAccessAdministration(): void
    {
        $this->client->loginUser($this->intruder);
        foreach (['/admin', '/admin/config', '/admin/tests', '/admin/doc', '/admin/plan-de-vie'] as $uri) {
            $this->client->request('GET', $uri);
            $this->assertResponseStatusCodeSame(403, "$uri doit être réservé aux administrateurs");
        }
    }
}
