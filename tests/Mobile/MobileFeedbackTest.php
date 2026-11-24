<?php

namespace App\Tests\Mobile;

use App\Feedback\Service\FeedbackService;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * API mobile du feedback nourricier : prénom demandé, lien d'une semaine, messages prêts à partager, prolongation,
 * fermeture (résultats gardés), demandes passées privées.
 */
final class MobileFeedbackTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** @return array{status: int, json: array<string, mixed>} */
    private function api(string $method, string $path, ?array $payload = null, ?string $bearer = null): array
    {
        $headers = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
        if (null !== $bearer) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
        }
        $this->client->request($method, $path, server: $headers, content: null !== $payload ? json_encode($payload, \JSON_THROW_ON_ERROR) : null);
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);

        return ['status' => $this->client->getResponse()->getStatusCode(), 'json' => \is_array($json) ? $json : []];
    }

    private function login(string $email): string
    {
        $res = $this->api('POST', '/api/mobile/v1/connexion', ['email' => $email, 'password' => 'mot-de-passe-solide', 'appareil' => 'Test']);
        $this->assertSame(200, $res['status']);

        return $res['json']['accessToken'];
    }

    public function testTheWholeCircuitFromThePhone(): void
    {
        $user = $this->createUser();
        $token = $this->login($user->getEmail());

        $empty = $this->api('GET', '/api/mobile/v1/feedback', bearer: $token);
        $this->assertSame(200, $empty['status']);
        $this->assertNull($empty['json']['request']);
        $this->assertTrue($empty['json']['needsFirstName']);

        $refused = $this->api('POST', '/api/mobile/v1/feedback', ['forceRealName' => false], $token);
        $this->assertSame(422, $refused['status']);
        $this->assertSame('first_name_required', $refused['json']['code']);

        $created = $this->api('POST', '/api/mobile/v1/feedback', ['forceRealName' => false, 'firstName' => 'Déborah'], $token);
        $this->assertSame(201, $created['status']);
        $req = $created['json']['request'];
        $this->assertTrue($req['open']);
        $this->assertMatchesRegularExpression('#/avis/[a-f0-9]{24}$#', $req['url']);
        $this->assertStringContainsString($req['url'], $created['json']['messages']['tu']);
        $this->assertStringContainsString('votre regard', $created['json']['messages']['vous']);
        $this->assertFalse($created['json']['needsFirstName']);
        $this->assertSame(0, $created['json']['summary']['count']);

        // Une réponse d'un proche, puis fermeture : les résultats restent
        $service = static::getContainer()->get(FeedbackService::class);
        $this->assertSame([], $service->submit($service->activeRequest($this->findUser($user->getEmail())), ['qualities' => ['Courage'], 'strengths' => 'Sa ténacité']));
        $closed = $this->api('POST', '/api/mobile/v1/feedback/fermer', [], $token);
        $this->assertFalse($closed['json']['request']['open']);
        $this->assertTrue($closed['json']['request']['closed']);
        $this->assertNull($closed['json']['messages']);
        $this->assertSame(1, $closed['json']['summary']['count']);
        $this->assertSame(409, $this->api('POST', '/api/mobile/v1/feedback/prolonger', [], $token)['status'], 'une demande fermée ne se prolonge pas');

        // Nouveau lien : l'ancien passe dans l'historique, consultable seulement par son auteur
        $renewed = $this->api('POST', '/api/mobile/v1/feedback', ['forceRealName' => true], $token);
        $this->assertSame(201, $renewed['status']);
        $this->assertCount(1, $renewed['json']['past']);
        $pastId = $renewed['json']['past'][0]['id'];
        $this->assertSame(1, $this->api('GET', '/api/mobile/v1/feedback/demandes/'.$pastId, bearer: $token)['json']['summary']['count']);
        $this->assertStringNotContainsString('anonyme', $renewed['json']['messages']['tu']);

        $this->assertSame(200, $this->api('POST', '/api/mobile/v1/feedback/prolonger', [], $token)['status']);

        $other = $this->createUser();
        $this->assertSame(404, $this->api('GET', '/api/mobile/v1/feedback/demandes/'.$pastId, bearer: $this->login($other->getEmail()))['status']);
        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/feedback')['status']);
    }
}
