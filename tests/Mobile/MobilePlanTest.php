<?php

namespace App\Tests\Mobile;

use App\Compte\Entity\User;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeCroissance\Entity\PlanDeCroissance;
use App\PlanDeCroissance\Enum\GrowthAxis;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\PlanDIntendance\Entity\StewardshipRhythm;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeVie\Entity\Purpose;
use App\PlanDeVie\Entity\Value;
use App\PlanDeVie\Entity\Vision;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MobilePlanTest extends WebTestCase
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

    public function testPlansOverviewReturnsAllSixPlansWithProgress(): void
    {
        $user = $this->createUser(pro: false);
        $token = $this->login((string) $user->getEmail());

        $res = $this->api('GET', '/api/mobile/v1/plans', null, $token);
        $this->assertSame(200, $res['status']);

        $json = $res['json'];
        $this->assertArrayHasKey('overallPercent', $json);
        $this->assertSame(6, $json['totalPlansCount']);
        $this->assertCount(6, $json['plans']);

        $keys = array_column($json['plans'], 'key');
        $this->assertSame(['VIE', 'CROISSANCE', 'DEVELOPPEMENT', 'HYGIENE', 'INTENDANCE', 'TRANSMISSION'], $keys);

        // Plan de Vie n'est jamais verrouillé
        $vie = $json['plans'][0];
        $this->assertSame('VIE', $vie['key']);
        $this->assertFalse($vie['locked']);
        $this->assertArrayHasKey('progress', $vie);
        $this->assertArrayHasKey('percent', $vie['progress']);
        $this->assertArrayHasKey('steps', $vie['progress']);

        // Pour un utilisateur gratuit, les plans PRO sont verrouillés
        $croissance = $json['plans'][1];
        $this->assertSame('CROISSANCE', $croissance['key']);
        $this->assertTrue($croissance['locked']);
    }

    public function testViePlanIsAccessibleAndReturnsFrenchLabels(): void
    {
        $user = $this->createUser(pro: false);
        $em = $this->em();

        $vie = new PlanDeVie();
        $vie->setUser($user);
        $purpose = (new Purpose())->setPlanDeVie($vie)->setStatement('Bâtir des hommes et des femmes intègres.');
        $vision = (new Vision())->setPlanDeVie($vie)
            ->setContent('Une jeunesse épanouie et responsable.')
            ->setHorizon('10 ans')
            ->setBeneficiaries('La jeunesse')
            ->setIndicators('100 leaders formés');
        $value1 = (new Value())->setPlanDeVie($vie)->setName('Intégrité')->setBehavior('Dire la vérité.');
        $goal = (new LifeGoal())->setPlanDeVie($vie)->setTitle('Écrire mon livre')->setCategory('DO')->setPriority('HIGH');

        $em->persist($vie);
        $em->persist($purpose);
        $em->persist($vision);
        $em->persist($value1);
        $em->persist($goal);
        $em->flush();

        $token = $this->login((string) $user->getEmail());
        $res = $this->api('GET', '/api/mobile/v1/plans/VIE', null, $token);
        $this->assertSame(200, $res['status']);

        $plan = $res['json']['plan'];
        $this->assertSame('VIE', $plan['key']);
        $this->assertFalse($plan['locked']);

        $content = $plan['content'];
        $this->assertSame('Bâtir des hommes et des femmes intègres.', $content['purpose']['statement']);
        $this->assertSame('Une jeunesse épanouie et responsable.', $content['vision']['content']);
        $this->assertCount(1, $content['values']);
        $this->assertSame('Intégrité', $content['values'][0]['name']);

        $this->assertCount(1, $content['goals']);
        $g = $content['goals'][0];
        $this->assertSame('Écrire mon livre', $g['title']);
        $this->assertSame('DO', $g['category']);
        $this->assertSame('Faire', $g['categoryLabel']); // Traduit
        $this->assertSame('HIGH', $g['priority']);
        $this->assertSame('Haute', $g['priorityLabel']); // Traduit
    }

    public function testProPlanBlocksFreeUserWithoutPaymentLinks(): void
    {
        $user = $this->createUser(pro: false);
        $token = $this->login((string) $user->getEmail());

        $res = $this->api('GET', '/api/mobile/v1/plans/INTENDANCE', null, $token);
        $this->assertSame(403, $res['status']);
        $this->assertSame('pro_required', $res['json']['code']);
        // Conformité Apple 3.1.1 : pas d'URL d'achat ni d'upgrade
        $this->assertArrayNotHasKey('upgradeUrl', $res['json']);
        $this->assertArrayNotHasKey('price', $res['json']);
    }

    public function testProPlanIsFullySerializedForProUser(): void
    {
        $user = $this->createUser(pro: true);
        $em = $this->em();

        $rhythm = (new StewardshipRhythm())->setUser($user)->setFrequency('MONTHLY')->setWithWhom('Mon conjoint');
        $item = (new StewardshipItem())->setUser($user)->setOrigin('FINANCES')->setLabel('Épargne de sécurité')
            ->setStatus('FRUCTIFIE')->setDecision('PROTEGER')->setPriority(true)->setActionText('Ouvrir un compte dédié');

        $em->persist($rhythm);
        $em->persist($item);
        $em->flush();

        $token = $this->login((string) $user->getEmail());
        $res = $this->api('GET', '/api/mobile/v1/plans/INTENDANCE', null, $token);
        $this->assertSame(200, $res['status']);

        $plan = $res['json']['plan'];
        $this->assertSame('INTENDANCE', $plan['key']);
        $this->assertFalse($plan['locked']);

        $content = $plan['content'];
        $this->assertSame('MONTHLY', $content['rhythm']['frequency']);
        $this->assertSame('Chaque mois', $content['rhythm']['frequencyLabel']);
        $this->assertSame('Mon conjoint', $content['rhythm']['withWhom']);

        $this->assertSame(1, $content['summary']['totalCount']);
        $this->assertSame(1, $content['summary']['priorityCount']);
        $this->assertSame(1, $content['summary']['fructifieCount']);

        $itemData = $content['items'][0];
        $this->assertSame('Épargne de sécurité', $itemData['label']);
        $this->assertSame('Finances et biens', $itemData['originLabel']);
        $this->assertSame('Il fructifie', $itemData['statusLabel']);
        $this->assertSame('Protéger', $itemData['decisionLabel']);
        $this->assertTrue($itemData['priority']);
    }

    public function testUnknownPlanReturns404(): void
    {
        $user = $this->createUser(pro: true);
        $token = $this->login((string) $user->getEmail());

        $res = $this->api('GET', '/api/mobile/v1/plans/INCONNU', null, $token);
        $this->assertSame(404, $res['status']);
        $this->assertSame('plan_not_found', $res['json']['code']);
    }
}
