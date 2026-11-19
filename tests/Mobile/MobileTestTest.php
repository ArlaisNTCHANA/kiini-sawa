<?php

namespace App\Tests\Mobile;

use App\Compte\Entity\User;
use App\Personnalite\Entity\Assessment;
use App\Personnalite\Psychometrie\Instruments;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MobileTestTest extends WebTestCase
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
            content: null !== $payload ? json_encode($payload, \JSON_THROW_ON_ERROR) : null
        );

        $response = $this->client->getResponse();
        $decoded = json_decode((string) $response->getContent(), true);

        return [
            'status' => $response->getStatusCode(),
            'json' => \is_array($decoded) ? $decoded : [],
        ];
    }

    private function login(string $email, string $password = 'mot-de-passe-solide'): string
    {
        $res = $this->api('POST', '/api/mobile/v1/connexion', [
            'email' => $email,
            'password' => $password,
            'appareil' => 'iPhone Test',
        ]);
        $this->assertSame(200, $res['status']);

        return $res['json']['accessToken'];
    }

    public function testCatalogListsAllTestGroups(): void
    {
        $user = $this->createUser(pro: false);
        $token = $this->login((string) $user->getEmail());

        $res = $this->api('GET', '/api/mobile/v1/tests', null, $token);
        $this->assertSame(200, $res['status']);

        $json = $res['json'];
        $this->assertArrayHasKey('groups', $json);
        $this->assertArrayHasKey('synthesisSummary', $json);
        $this->assertCount(3, $json['groups']);

        $groupKeys = array_column($json['groups'], 'key');
        $this->assertSame(['personnalite', 'motivations_et_dons', 'auto_evaluations'], $groupKeys);

        // DISC est disponible gratuitement par défaut
        $disc = $json['groups'][0]['tests'][0];
        $this->assertSame('disc', $disc['slug']);
        $this->assertFalse($disc['locked']);
        $this->assertSame(24, $disc['questionCount']);
    }

    public function testGetQuestionsForDiscReturnsBlocks(): void
    {
        $user = $this->createUser(pro: false);
        $token = $this->login((string) $user->getEmail());

        $res = $this->api('GET', '/api/mobile/v1/tests/disc/questions', null, $token);
        $this->assertSame(200, $res['status']);

        $json = $res['json'];
        $this->assertSame('disc', $json['slug']);
        $this->assertSame('disc', $json['bank']);
        $this->assertCount(24, $json['items']);

        $block1 = $json['items'][0];
        $this->assertSame('disc_block', $block1['type']);
        $this->assertCount(4, $block1['words']);
        $letters = array_column($block1['words'], 'letter');
        sort($letters);
        $this->assertSame(['C', 'D', 'I', 'S'], $letters);
    }

    public function testNonFreeTestRequiresProForFreeUser(): void
    {
        $user = $this->createUser(pro: false);
        $token = $this->login((string) $user->getEmail());

        // Par défaut 'enneagramme' nécessite PRO pour un utilisateur non PRO
        $res = $this->api('GET', '/api/mobile/v1/tests/enneagramme/questions', null, $token);
        $this->assertSame(403, $res['status']);
        $this->assertSame('pro_required', $res['json']['code']);
        $this->assertArrayNotHasKey('upgradeUrl', $res['json']);
    }

    public function testProUserCanAccessAndSubmitTest(): void
    {
        $user = $this->createUser(pro: true);
        $token = $this->login((string) $user->getEmail());

        // 1. Lire les questions Ennéagramme
        $qRes = $this->api('GET', '/api/mobile/v1/tests/enneagramme/questions', null, $token);
        $this->assertSame(200, $qRes['status']);
        $items = $qRes['json']['items'];
        $this->assertNotEmpty($items);

        // 2. Préparer les réponses
        $answers = [];
        foreach ($items as $item) {
            $answers[$item['key']] = 4; // note de 1 à 5
        }

        // 3. Soumettre
        $subRes = $this->api('POST', '/api/mobile/v1/tests/enneagramme/reponses', [
            'answers' => $answers,
            'durationSeconds' => 180,
        ], $token);

        $this->assertSame(201, $subRes['status']);
        $this->assertTrue($subRes['json']['ok']);
        $this->assertArrayHasKey('id', $subRes['json']);
        $this->assertArrayHasKey('report', $subRes['json']);

        $assessmentId = $subRes['json']['id'];

        // 4. Consulter le rapport généré
        $repRes = $this->api('GET', '/api/mobile/v1/tests/enneagramme/rapport?id='.$assessmentId, null, $token);
        $this->assertSame(200, $repRes['status']);
        $this->assertSame('enneagramme', $repRes['json']['slug']);
        $this->assertArrayHasKey('scores', $repRes['json']);
        $this->assertArrayHasKey('details', $repRes['json']);
    }

    public function testSynthesisLockedForFreeUserAndAccessibleForPro(): void
    {
        $freeUser = $this->createUser(pro: false);
        $freeToken = $this->login((string) $freeUser->getEmail());

        $resFree = $this->api('GET', '/api/mobile/v1/tests/synthese', null, $freeToken);
        $this->assertSame(200, $resFree['status']);
        $this->assertTrue($resFree['json']['locked']);
        $this->assertArrayHasKey('previewNotice', $resFree['json']);

        $proUser = $this->createUser(pro: true);
        $proToken = $this->login((string) $proUser->getEmail());

        $resPro = $this->api('GET', '/api/mobile/v1/tests/synthese', null, $proToken);
        $this->assertSame(200, $resPro['status']);
        $this->assertFalse($resPro['json']['locked']);
    }

    public function testDataIsolationBetweenUsers(): void
    {
        $userA = $this->createUser(pro: true);
        $tokenA = $this->login((string) $userA->getEmail());

        $userB = $this->createUser(pro: true);
        $tokenB = $this->login((string) $userB->getEmail());

        // Créer un assessment pour userA
        $items = Instruments::items('dons-spirituels');
        $answers = [];
        foreach ($items as $it) {
            $answers[$it['key']] = 5;
        }

        $res = $this->api('POST', '/api/mobile/v1/tests/dons-spirituels/reponses', [
            'answers' => $answers,
        ], $tokenA);
        $this->assertSame(201, $res['status']);
        $assessmentIdA = $res['json']['id'];

        // userB essaie d'accéder au rapport de userA
        $resB = $this->api('GET', '/api/mobile/v1/tests/dons-spirituels/rapport?id='.$assessmentIdA, null, $tokenB);
        $this->assertSame(404, $resB['status']);
    }
}
