<?php

namespace App\Tests\Mobile;

use App\Compte\Entity\User;
use App\Planner\Entity\PlannerTask;
use App\Social\Entity\Connection;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MobileLot5Test extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{status: int, json: array<string, mixed>}
     */
    private function api(string $method, string $path, ?array $payload = null, ?string $bearer = null): array
    {
        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $payload) {
            $headers['CONTENT_TYPE'] = 'application/json';
        }
        if (null !== $bearer) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
        }

        $this->client->request(
            $method,
            $path,
            server: $headers,
            content: null !== $payload ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
        );

        $response = $this->client->getResponse();
        $raw = (string) $response->getContent();
        $json = '' === $raw ? [] : (json_decode($raw, true) ?? []);

        return [
            'status' => $response->getStatusCode(),
            'json' => \is_array($json) ? $json : ['raw' => $raw],
        ];
    }

    private function login(string $email, string $password = 'mot-de-passe-solide'): string
    {
        $res = $this->api('POST', '/api/mobile/v1/connexion', [
            'email' => $email,
            'password' => $password,
            'appareil' => 'Test Phone',
        ]);
        $this->assertSame(200, $res['status']);

        return $res['json']['accessToken'];
    }

    public function testMethodeCatalogAndChapterReading(): void
    {
        $user = $this->createUser();
        $token = $this->login((string) $user->getEmail());

        // 1. Catalog
        $res = $this->api('GET', '/api/mobile/v1/methode/chapitres', bearer: $token);
        $this->assertSame(200, $res['status']);
        $this->assertArrayHasKey('chapters', $res['json']);
        $this->assertGreaterThanOrEqual(20, $res['json']['total']);
        $this->assertSame(1, $res['json']['chapters'][0]['order']);
        $this->assertTrue($res['json']['chapters'][0]['isPublic']);

        // 2. Public chapter (e.g. comment-lire)
        $chRes = $this->api('GET', '/api/mobile/v1/methode/chapitres/comment-lire', bearer: $token);
        $this->assertSame(200, $chRes['status']);
        $this->assertSame('comment-lire', $chRes['json']['slug']);
        $this->assertTrue($chRes['json']['isPublic']);
        $this->assertNotEmpty($chRes['json']['contentHtml']);

        // 3. Unknown chapter
        $bad = $this->api('GET', '/api/mobile/v1/methode/chapitres/inconnu-inexistant', bearer: $token);
        $this->assertSame(404, $bad['status']);
    }

    public function testPlannerTasksLifecycleAndIsolation(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $tokenA = $this->login((string) $userA->getEmail());
        $tokenB = $this->login((string) $userB->getEmail());

        // 1. Create task for A
        $create = $this->api('POST', '/api/mobile/v1/agenda', [
            'title' => 'Tâche de test A',
            'date' => (new \DateTime())->format('Y-m-d'),
            'description' => 'Description détaillée',
            'isAllDay' => true,
        ], bearer: $tokenA);
        $this->assertSame(201, $create['status']);
        $taskId = $create['json']['id'];
        $this->assertFalse($create['json']['isCompleted']);

        // 2. List tasks for A
        $listA = $this->api('GET', '/api/mobile/v1/agenda', bearer: $tokenA);
        $this->assertSame(200, $listA['status']);
        $this->assertCount(1, $listA['json']['tasks']);
        $this->assertSame('Tâche de test A', $listA['json']['tasks'][0]['title']);

        // 3. User B cannot see A's task
        $listB = $this->api('GET', '/api/mobile/v1/agenda', bearer: $tokenB);
        $this->assertSame(200, $listB['status']);
        $this->assertSame(0, $listB['json']['total']);

        // 4. User B cannot update or delete A's task
        $this->assertSame(404, $this->api('PATCH', '/api/mobile/v1/agenda/'.$taskId, ['isCompleted' => true], bearer: $tokenB)['status']);
        $this->assertSame(404, $this->api('DELETE', '/api/mobile/v1/agenda/'.$taskId, bearer: $tokenB)['status']);

        // 5. User A updates task
        $update = $this->api('PATCH', '/api/mobile/v1/agenda/'.$taskId, [
            'isCompleted' => true,
            'title' => 'Tâche de test A modifiée',
        ], bearer: $tokenA);
        $this->assertSame(200, $update['status']);
        $this->assertTrue($update['json']['isCompleted']);
        $this->assertSame('Tâche de test A modifiée', $update['json']['title']);

        // 6. User A deletes task
        $del = $this->api('DELETE', '/api/mobile/v1/agenda/'.$taskId, bearer: $tokenA);
        $this->assertSame(200, $del['status']);

        $afterDel = $this->api('GET', '/api/mobile/v1/agenda', bearer: $tokenA);
        $this->assertSame(0, $afterDel['json']['total']);
    }

    public function testWeeklyReviewGetAndPost(): void
    {
        $user = $this->createUser();
        $token = $this->login((string) $user->getEmail());

        // 1. Get weekly review info
        $res = $this->api('GET', '/api/mobile/v1/revues/hebdo', bearer: $token);
        $this->assertSame(200, $res['status']);
        $this->assertFalse($res['json']['isCompleted']);
        $this->assertArrayHasKey('plans', $res['json']);
        $this->assertArrayHasKey('itemsByPlan', $res['json']);

        // 2. Submit weekly review
        $post = $this->api('POST', '/api/mobile/v1/revues/hebdo', [
            'wins' => 'Belle avancée sur le projet',
            'struggles' => 'Un peu de fatigue le mardi',
            'lesson' => 'Garder le repos du sabbat',
            'priorities' => ['Finaliser le lot 5', 'Prier chaque matin'],
        ], bearer: $token);
        $this->assertSame(200, $post['status']);
        $this->assertTrue($post['json']['success']);
        $this->assertSame('Belle avancée sur le projet', $post['json']['review']['wins']);

        // 3. Now review is completed
        $resAfter = $this->api('GET', '/api/mobile/v1/revues/hebdo', bearer: $token);
        $this->assertSame(200, $resAfter['status']);
        $this->assertTrue($resAfter['json']['isCompleted']);
        $this->assertSame('Belle avancée sur le projet', $resAfter['json']['review']['wins']);
    }

    public function testMonthlyReviewAndRhythms(): void
    {
        $user = $this->createUser();
        $token = $this->login((string) $user->getEmail());

        // 1. Rhythms overview
        $rhythms = $this->api('GET', '/api/mobile/v1/revues/rythmes', bearer: $token);
        $this->assertSame(200, $rhythms['status']);
        $this->assertCount(5, $rhythms['json']['rhythms']); // day, week, month, season, year

        // 2. Monthly review
        $monthly = $this->api('GET', '/api/mobile/v1/revues/mensuelle', bearer: $token);
        $this->assertSame(200, $monthly['status']);
    }

    public function testCommunityPartnersInvitationAndRespond(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $tokenA = $this->login((string) $userA->getEmail());
        $tokenB = $this->login((string) $userB->getEmail());

        // 1. List initially empty
        $listA = $this->api('GET', '/api/mobile/v1/partenaires', bearer: $tokenA);
        $this->assertSame(200, $listA['status']);
        $this->assertSame(0, $listA['json']['totalActive']);

        // 2. Invite User B from User A
        $inv = $this->api('POST', '/api/mobile/v1/partenaires/invitation', [
            'email' => (string) $userB->getEmail(),
        ], bearer: $tokenA);
        $this->assertSame(200, $inv['status']);

        // 3. User B sees pending received invitation
        $listB = $this->api('GET', '/api/mobile/v1/partenaires', bearer: $tokenB);
        $this->assertSame(200, $listB['status']);
        $this->assertCount(1, $listB['json']['pendingReceived']);
        $connId = $listB['json']['pendingReceived'][0]['id'];

        // 4. User B accepts invitation
        $accept = $this->api('POST', '/api/mobile/v1/partenaires/'.$connId.'/repondre', [
            'action' => 'ACCEPT',
        ], bearer: $tokenB);
        $this->assertSame(200, $accept['status']);
        $this->assertSame('ACCEPTED', $accept['json']['status']);

        // 5. Both have 1 active partner now
        $listA2 = $this->api('GET', '/api/mobile/v1/partenaires', bearer: $tokenA);
        $this->assertSame(1, $listA2['json']['totalActive']);

        $listB2 = $this->api('GET', '/api/mobile/v1/partenaires', bearer: $tokenB);
        $this->assertSame(1, $listB2['json']['totalActive']);

        // 6. Challenges and Couple
        $defis = $this->api('GET', '/api/mobile/v1/defis', bearer: $tokenA);
        $this->assertSame(200, $defis['status']);
        $this->assertArrayHasKey('challenges', $defis['json']);

        $couple = $this->api('GET', '/api/mobile/v1/couple', bearer: $tokenA);
        $this->assertSame(200, $couple['status']);
        $this->assertFalse($couple['json']['hasCouple']);
    }
    public function testPartnerInvitationDoesNotRevealAccountsAndRejectAllowsReinvite(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $tokenA = $this->login((string) $userA->getEmail());
        $tokenB = $this->login((string) $userB->getEmail());

        // Même réponse, que l'adresse ait un compte ou non
        $unknown = $this->api('POST', '/api/mobile/v1/partenaires/invitation', ['email' => 'personne-'.uniqid().'@exemple.org'], bearer: $tokenA);
        $known = $this->api('POST', '/api/mobile/v1/partenaires/invitation', ['email' => (string) $userB->getEmail()], bearer: $tokenA);
        $this->assertSame(200, $unknown['status']);
        $this->assertSame($unknown['status'], $known['status']);
        $this->assertSame($unknown['json'], $known['json']);

        // Refus : la demande disparaît (comme sur le site)…
        $connId = $this->api('GET', '/api/mobile/v1/partenaires', bearer: $tokenB)['json']['pendingReceived'][0]['id'];
        $reject = $this->api('POST', '/api/mobile/v1/partenaires/'.$connId.'/repondre', ['action' => 'REJECT'], bearer: $tokenB);
        $this->assertSame(200, $reject['status']);
        $this->assertCount(0, $this->api('GET', '/api/mobile/v1/partenaires', bearer: $tokenB)['json']['pendingReceived']);

        // … et une nouvelle invitation reste possible
        $again = $this->api('POST', '/api/mobile/v1/partenaires/invitation', ['email' => (string) $userB->getEmail()], bearer: $tokenA);
        $this->assertSame(200, $again['status']);
        $this->assertCount(1, $this->api('GET', '/api/mobile/v1/partenaires', bearer: $tokenB)['json']['pendingReceived']);
    }
}
