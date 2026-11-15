<?php

namespace App\Tests\Journal\Controller;

use App\Journal\Entity\ReflectionNote;
use App\Journal\Entity\JournalCollection;
use App\Compte\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class JournalControllerTest extends WebTestCase
{
    public function testJournalRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/journal');

        $this->assertResponseRedirects('/login');
    }

    public function testJournalPageRendersSuccessfully(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        $client->request('GET', '/journal');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.journal-wrap');
    }

    public function testJournalApiLifecycle(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $userRepository = $container->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        // 1. Create a collection
        $client->request('POST', '/api/journal/collections/create', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'name' => 'Ma Collection Test',
            'color' => '#ef4444',
            'icon' => 'Book'
        ]));

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        // Find the created collection
        $collection = $em->getRepository(JournalCollection::class)->findOneBy([
            'name' => 'Ma Collection Test',
            'user' => $testUser
        ]);
        $this->assertNotNull($collection);

        // 2. Create a reflection note inside that collection
        $client->request('POST', '/api/journal/entries/create', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'content' => 'Ceci est une note de test incroyable',
            'mood' => 'HAPPY',
            'energy' => 8,
            'type' => 'REFLECTION',
            'location' => 'Paris',
            'collectionId' => $collection->getId()
        ]));

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        // Find the created note
        $note = $em->getRepository(ReflectionNote::class)->findOneBy([
            'content' => 'Ceci est une note de test incroyable',
            'user' => $testUser
        ]);
        $this->assertNotNull($note);
        $this->assertEquals($collection->getId(), $note->getCollection()->getId());

        // 3. Fetch entries and check if the note and collection are listed
        $client->request('GET', '/api/journal/entries?limit=10&offset=0');
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('entries', $data);
        $this->assertArrayHasKey('totalCount', $data);
        $this->assertGreaterThanOrEqual(1, $data['totalCount']);

        // Filter by collection
        $client->request('GET', '/api/journal/entries?collectionId=' . $collection->getId());
        $this->assertResponseIsSuccessful();
        $dataFiltered = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(1, $dataFiltered['totalCount']);

        // 4. Delete the note
        $client->request('DELETE', '/api/journal/entries/' . $note->getId());
        $this->assertResponseIsSuccessful();
        $responseDelete = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseDelete['success']);

        // Verify note is deleted
        $em->clear();
        $deletedNote = $em->getRepository(ReflectionNote::class)->find($note->getId());
        $this->assertNull($deletedNote);

        // 5. Delete the collection
        $client->request('DELETE', '/api/journal/collections/' . $collection->getId());
        $this->assertResponseIsSuccessful();
        $responseDeleteCol = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseDeleteCol['success']);

        // Verify collection is deleted
        $deletedCollection = $em->getRepository(JournalCollection::class)->find($collection->getId());
        $this->assertNull($deletedCollection);
    }
}
