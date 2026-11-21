<?php

namespace App\Tests\Mobile;

use App\Abonnement\Service\SubscriptionManager;
use App\Couple\Service\CoupleService;
use App\Notification\Entity\Notification;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MobileCoupleSanctuaryTest extends WebTestCase
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

        return (string) $res['json']['accessToken'];
    }

    public function testHomeRequiresActiveProCouple(): void
    {
        $userA = $this->createUser();
        $tokenA = $this->login((string) $userA->getEmail());

        $res = $this->api('GET', '/api/mobile/v1/couple/home', null, $tokenA);
        $this->assertSame(403, $res['status']);
        $this->assertSame('no_couple', $res['json']['code'] ?? null);
    }

    public function testFullSanctuaryWorkflow(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $container = static::getContainer();
        /** @var CoupleService $coupleService */
        $coupleService = $container->get(CoupleService::class);
        /** @var SubscriptionManager $subs */
        $subs = $container->get(SubscriptionManager::class);

        $couple = $coupleService->create($userA, $userB->getEmail());
        $coupleService->join($userB, $couple, true);
        $tx = $subs->declare($userA, 'DUO', 'XAF', 'MOBILE_MONEY', 'DUO-'.uniqid(), null, null);
        $subs->validate($tx);

        $tokenA = $this->login((string) $userA->getEmail());
        $tokenB = $this->login((string) $userB->getEmail());

        // 1. Vue 360 Home
        $home = $this->api('GET', '/api/mobile/v1/couple/home', null, $tokenA);
        $this->assertSame(200, $home['status']);
        $this->assertSame($userB->getDisplayName(), $home['json']['couple']['partnerName']);
        $this->assertSame(0, $home['json']['couple']['streakDays']);
        $this->assertNotEmpty($home['json']['couple']['dailyVerse']['reference']);
        $this->assertNotEmpty($home['json']['pingPresets']);

        // 2. Projets communs : création, mise à jour, suppression
        $createProj = $this->api('POST', '/api/mobile/v1/couple/projects', [
            'title' => 'Acheter notre maison',
            'category' => 'HAVE',
            'deadline' => '2027-12-31',
            'details' => 'Épargne mensuelle dédiée',
        ], $tokenA);
        $this->assertSame(201, $createProj['status']);
        $projectId = $createProj['json']['project']['id'];

        $patchProj = $this->api('PATCH', '/api/mobile/v1/couple/projects/'.$projectId, [
            'progress' => 35,
        ], $tokenB);
        $this->assertSame(200, $patchProj['status']);
        $this->assertSame(35, $patchProj['json']['project']['progress']);

        // 3. Intentions de prière
        $createInt = $this->api('POST', '/api/mobile/v1/couple/intentions', [
            'title' => 'Paix et sagesse pour notre projet familial',
        ], $tokenB);
        $this->assertSame(201, $createInt['status']);
        $intentionId = $createInt['json']['intention']['id'];

        $listInt = $this->api('GET', '/api/mobile/v1/couple/intentions', null, $tokenA);
        $this->assertSame(200, $listInt['status']);
        $this->assertCount(1, $listInt['json']['intentions']);

        // 4. Défis de couple : catalogue, démarrage, validation croisée A + B -> jour 2
        $catalog = $this->api('GET', '/api/mobile/v1/couple/challenges/catalog', null, $tokenA);
        $this->assertSame(200, $catalog['status']);
        $this->assertGreaterThanOrEqual(4, \count($catalog['json']['catalog']));

        $startCh = $this->api('POST', '/api/mobile/v1/couple/challenges/start', [
            'code' => 'ecoute_7j',
        ], $tokenA);
        $this->assertSame(201, $startCh['status']);
        $this->assertSame(1, $startCh['json']['challenge']['currentDay']);

        $checkA = $this->api('POST', '/api/mobile/v1/couple/challenges/check-day', [
            'note' => 'Merci pour ce beau moment d\'écoute !',
        ], $tokenA);
        $this->assertSame(200, $checkA['status']);
        $this->assertTrue($checkA['json']['challenge']['todayDoneByMe']);
        $this->assertFalse($checkA['json']['challenge']['todayDoneByPartner']);
        $this->assertSame(1, $checkA['json']['challenge']['currentDay']);
        $this->assertSame(1, $checkA['json']['streakDays']);

        $checkB = $this->api('POST', '/api/mobile/v1/couple/challenges/check-day', [
            'note' => 'Ça m\'a fait beaucoup de bien.',
        ], $tokenB);
        $this->assertSame(200, $checkB['status']);
        $this->assertSame(2, $checkB['json']['challenge']['currentDay']);

        // 5. Méditation à deux : démarrage & validation d'une étape
        $startMed = $this->api('POST', '/api/mobile/v1/couple/meditations/start', [
            'code' => 'fondations_solides',
        ], $tokenB);
        $this->assertSame(201, $startMed['status']);
        $this->assertSame(1, $startMed['json']['meditation']['currentStep']);
        $this->assertNotEmpty($startMed['json']['meditation']['step']['question']);

        $stepMed = $this->api('POST', '/api/mobile/v1/couple/meditations/complete-step', [], $tokenA);
        $this->assertSame(200, $stepMed['status']);
        $this->assertSame(2, $stepMed['json']['meditation']['currentStep']);

        // 6. Ping de connexion -> notification enregistrée pour le conjoint
        $ping = $this->api('POST', '/api/mobile/v1/couple/ping', [
            'preset' => 'meditation',
        ], $tokenA);
        $this->assertSame(200, $ping['status']);
        $this->assertTrue($ping['json']['sent']);

        $em = static::getContainer()->get('doctrine')->getManager();
        $notifsB = $em->getRepository(Notification::class)->findBy(['user' => $userB]);
        $this->assertNotEmpty($notifsB);

        // Nettoyage projet & intention
        $delInt = $this->api('DELETE', '/api/mobile/v1/couple/intentions/'.$intentionId, null, $tokenA);
        $this->assertSame(200, $delInt['status']);
        $delProj = $this->api('DELETE', '/api/mobile/v1/couple/projects/'.$projectId, null, $tokenA);
        $this->assertSame(200, $delProj['status']);
    }
}
