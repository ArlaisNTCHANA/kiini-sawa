<?php

namespace App\Tests\Mobile;

use App\Compte\Entity\User;
use App\Boussole\Service\BoussoleContent;
use App\Boussole\Service\BoussoleEngine;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * API mobile de la Boussole 360 : mêmes 24 affirmations et même calcul que le site, historique, isolement.
 */
final class MobileBoussoleTest extends WebTestCase
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

    private const ANSWERS = [5, 4, 4, 5, 4, 4, 1, 2, 2, 1, 2, 2, 5, 4, 4, 1, 4, 4, 1, 2, 2, 5, 2, 2];

    public function testQuestionsAreTheSameAsOnTheWebsite(): void
    {
        $token = $this->login((string) $this->createUser()->getEmail());
        $res = $this->api('GET', '/api/mobile/v1/boussole/questions', bearer: $token);

        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->assertCount(24, $res['json']['questions']);
        $this->assertSame(BoussoleContent::ITEMS[0]['text'], $res['json']['questions'][0]['texte']);
        $this->assertSame(1, $res['json']['questions'][0]['numero']);
        $this->assertCount(5, $res['json']['echelle']);
        $this->assertSame('Tout à fait vrai', $res['json']['echelle'][4]['libelle']);
        $this->assertSame('Plan d\'Hygiène de vie', $res['json']['plans'][3]['nom']);
    }

    public function testSubmitComputesOnTheServerAndKeepsHistory(): void
    {
        $token = $this->login((string) $this->createUser()->getEmail());

        $empty = $this->api('GET', '/api/mobile/v1/boussole/dernier', bearer: $token);
        $this->assertSame(200, $empty['status']);
        $this->assertNull($empty['json']['resultat']);

        $res = $this->api('POST', '/api/mobile/v1/boussole/reponses', ['reponses' => self::ANSWERS], $token);
        $this->assertSame(201, $res['status']);
        $r = $res['json']['resultat'];
        $expected = (new BoussoleEngine())->evaluate(self::ANSWERS);
        $this->assertSame($expected['coherence'], $r['coherence']);
        $this->assertSame($expected['scores'], $r['scores']);
        $this->assertSame(['cle' => 'HYGIENE', 'nom' => 'Plan d\'Hygiène de vie'], $r['planPrioritaire']);
        $this->assertSame(['cle' => 'VIE', 'nom' => 'Plan de Vie'], $r['planFort']);
        $this->assertSame($expected['readingMain'], $r['lecture']['principale']);
        $this->assertSame($expected['steps'], $r['pistes']['etapes']);
        $this->assertSame('mobile', $r['source']);

        $this->api('POST', '/api/mobile/v1/boussole/reponses', ['reponses' => array_fill(0, 24, 3)], $token);
        $latest = $this->api('GET', '/api/mobile/v1/boussole/dernier', bearer: $token);
        $this->assertSame(50, $latest['json']['resultat']['coherence']);
        $this->assertNull($latest['json']['resultat']['appui'], 'Profil plat : pas de carte « point d\'appui »');

        $history = $this->api('GET', '/api/mobile/v1/boussole/historique', bearer: $token);
        $this->assertCount(2, $history['json']['passations']);
        $this->assertArrayNotHasKey('lecture', $history['json']['passations'][0]);

        $one = $this->api('GET', '/api/mobile/v1/boussole/passations/'.$r['id'], bearer: $token);
        $this->assertSame(200, $one['status']);
        $this->assertSame(self::ANSWERS, $one['json']['resultat']['reponses']);
    }

    public function testInvalidAnswersAreRefused(): void
    {
        $token = $this->login((string) $this->createUser()->getEmail());
        foreach ([[], ['reponses' => array_fill(0, 6, 7)], ['reponses' => [...array_fill(0, 23, 3), 0]]] as $payload) {
            $res = $this->api('POST', '/api/mobile/v1/boussole/reponses', $payload, $token);
            $this->assertSame(422, $res['status']);
            $this->assertSame('reponses_invalides', $res['json']['code']);
        }
    }

    public function testAccountsAreIsolated(): void
    {
        $tokenA = $this->login((string) $this->createUser()->getEmail());
        $tokenB = $this->login((string) $this->createUser()->getEmail());

        $id = $this->api('POST', '/api/mobile/v1/boussole/reponses', ['reponses' => self::ANSWERS], $tokenA)['json']['resultat']['id'];

        $this->assertSame(404, $this->api('GET', '/api/mobile/v1/boussole/passations/'.$id, bearer: $tokenB)['status']);
        $this->assertNull($this->api('GET', '/api/mobile/v1/boussole/dernier', bearer: $tokenB)['json']['resultat']);
        $this->assertSame([], $this->api('GET', '/api/mobile/v1/boussole/historique', bearer: $tokenB)['json']['passations']);
        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/boussole/dernier')['status']);
    }
}
