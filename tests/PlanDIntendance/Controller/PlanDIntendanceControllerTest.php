<?php

namespace App\Tests\PlanDIntendance\Controller;

use App\Compte\Entity\User;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\PlanDIntendance\Entity\StewardshipRhythm;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Plan d'Intendance (livre, chapitre 18) : inventaire, état, décision, compte rendu.
 */
class PlanDIntendanceControllerTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function items(User $user): array
    {
        $this->em()->clear();

        return $this->em()->getRepository(StewardshipItem::class)->findBy(['user' => $user->getId()], ['id' => 'ASC']);
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/plan-d-intendance');
        $this->assertResponseRedirects('/login');
    }

    public function testFreeUserIsSentToSubscription(): void
    {
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/plan-d-intendance');
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/abonnement', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testPageFollowsTheBook(): void
    {
        $this->client->loginUser($this->createUser(pro: true));
        $this->client->request('GET', '/plan-d-intendance');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Ce que je gère');
        foreach (['Finances et biens', 'Temps', 'Foyer et enfants', 'Ressources intellectuelles', 'Influence et position', 'Responsabilités confiées', 'Opportunités et accès', 'Mon But de Dieu', 'Mes dons et talents', 'Mon compte rendu'] as $text) {
            $this->assertSelectorTextContains('main', $text);
        }
        $this->assertSelectorTextContains('main', 'Mon argent sert-il mon But, ou décide-t-il à ma place ?');
    }

    public function testCycleInventoryStateDecisionReview(): void
    {
        $user = $this->createUser(pro: true);
        $this->client->loginUser($user);

        $this->client->request('POST', '/plan-d-intendance/element', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'origin' => 'TEMPS', 'label' => 'Mes soirées']);
        $this->assertResponseRedirects();
        $item = $this->items($user)[0];
        $this->assertSame('TEMPS', $item->getOrigin());

        $this->client->request('POST', '/plan-d-intendance/element/'.$item->getId(), ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 
            'status' => 'FUIT', 'decision' => 'LIBERER', 'action' => 'Écrans éteints à 21 h, trois soirs par semaine', 'priority' => '1', 'note' => '',
        ]);
        $item = $this->items($user)[0];
        $this->assertSame('FUIT', $item->getStatus());
        $this->assertSame('LIBERER', $item->getDecision());
        $this->assertTrue($item->isPriority());

        $crawler = $this->client->request('GET', '/plan-d-intendance');
        $this->assertSelectorTextContains('#priorites table', 'Écrans éteints à 21 h');
        $this->assertSelectorTextContains('#priorites table', 'Il fuit');
        $this->assertSelectorTextContains('#priorites table', 'Libérer');

        $this->client->request('POST', '/plan-d-intendance/element/'.$item->getId().'/fait', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE]);
        $this->assertNotNull($this->items($user)[0]->getActionDoneAt());

        $this->client->request('POST', '/plan-d-intendance/rythme', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'frequency' => 'WEEKLY', 'with_whom' => 'Mon conjoint', 'review' => '1']);
        $this->em()->clear();
        $rhythm = $this->em()->getRepository(StewardshipRhythm::class)->findOneBy(['user' => $user->getId()]);
        $this->assertSame('WEEKLY', $rhythm->getFrequency());
        $this->assertNotNull($rhythm->getLastReviewAt());

        $this->client->request('GET', '/plan-d-intendance/visualiser');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Mes soirées');
        $this->assertSelectorTextContains('body', 'Chaque semaine, avec Mon conjoint');

        $this->client->request('POST', '/plan-d-intendance/element/'.$item->getId().'/supprimer', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE]);
        $this->assertCount(0, $this->items($user));
    }

    public function testAtMostFivePriorities(): void
    {
        $user = $this->createUser(pro: true);
        $this->client->loginUser($user);
        for ($i = 1; $i <= 6; ++$i) {
            $this->client->request('POST', '/plan-d-intendance/element', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'origin' => 'FINANCES', 'label' => 'Élément '.$i]);
        }
        foreach ($this->items($user) as $item) {
            $this->client->request('POST', '/plan-d-intendance/element/'.$item->getId(), ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'priority' => '1']);
        }
        $this->assertCount(5, array_filter($this->items($user), fn (StewardshipItem $i) => $i->isPriority()));
    }

    public function testCannotTouchSomeoneElsesItem(): void
    {
        $owner = $this->createUser(pro: true);
        $this->client->loginUser($owner);
        $this->client->request('POST', '/plan-d-intendance/element', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'origin' => 'FOYER', 'label' => 'Mes enfants']);
        $id = $this->items($owner)[0]->getId();

        $this->client->loginUser($this->createUser(pro: true));
        $this->client->request('POST', '/plan-d-intendance/element/'.$id, ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'status' => 'DORT']);
        $this->assertResponseStatusCodeSame(404);
        $this->assertNull($this->items($owner)[0]->getStatus());
    }

    public function testLifePlanAndGiftsAreProposed(): void
    {
        $user = $this->createUser(pro: true);
        $gifts = [];
        foreach (Instruments::items('dons-spirituels') as $i => $item) {
            $gifts[$item['key']] = 1 + ($i % 5);
        }
        static::getContainer()->get(AssessmentService::class)->record($user, 'dons-spirituels', $gifts, 400);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/plan-d-intendance');
        $chips = $crawler->filter('[data-stewardship-suggestions="VIE_DON"] button');
        $this->assertGreaterThan(0, $chips->count());

        // Un clic sur la puce ajoute le don à l'inventaire, et il n'est plus proposé
        $label = $crawler->filter('[data-stewardship-suggestions="VIE_DON"] input[name="label"]')->first()->attr('value');
        $this->client->request('POST', '/plan-d-intendance/element', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'origin' => 'VIE_DON', 'label' => $label]);
        $this->assertSame($label, $this->items($user)[0]->getLabel());
        $crawler = $this->client->request('GET', '/plan-d-intendance');
        $this->assertCount(0, $crawler->filter('[data-stewardship-suggestions="VIE_DON"] input[value="'.$label.'"]'));
    }

    public function testSixPlansInNavigationAndOverview(): void
    {
        $this->client->loginUser($this->createUser(pro: true));
        $crawler = $this->client->request('GET', '/mes-plans');
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('[data-plan-card="INTENDANCE"]'));
        $this->assertGreaterThan(0, $crawler->filter('a[href="/plan-d-intendance"]')->count());
        $this->assertSelectorTextContains('body', 'Ce que je gère');
    }

    /** La revue du mois sert de compte rendu : état et action faite des priorités, date du dernier point. */
    public function testMonthlyReviewIsTheStewardshipReport(): void
    {
        $user = $this->createUser(pro: true);
        $this->client->loginUser($user);
        $this->client->request('POST', '/plan-d-intendance/element', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'origin' => 'FINANCES', 'label' => 'Mon épargne']);
        $item = $this->items($user)[0];
        $this->client->request('POST', '/plan-d-intendance/element/'.$item->getId(), ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'status' => 'DORT', 'decision' => 'PROTEGER', 'action' => 'Compte séparé', 'priority' => '1']);

        $crawler = $this->client->request('GET', '/bilan/revue-mensuelle');
        $this->assertSelectorTextContains('[data-stewardship-review]', 'Mon épargne');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->client->request('POST', '/bilan/revue-mensuelle', [
            '_csrf_token' => $token, 'progress' => 'Un pas', 'decision' => 'Continuer',
            'stw' => [$item->getId() => ['status' => 'FRUCTIFIE', 'done' => '1']],
        ]);
        $this->assertResponseRedirects('/bilan/revue-mensuelle');

        $item = $this->items($user)[0];
        $this->assertSame('FRUCTIFIE', $item->getStatus());
        $this->assertNotNull($item->getActionDoneAt());
        $this->assertNotNull($this->em()->getRepository(StewardshipRhythm::class)->findOneBy(['user' => $user->getId()])?->getLastReviewAt());
    }

    /** Carte stratégique : un élément relié à un objectif y apparaît ; sinon, l'Intendance est signalée absente. */
    public function testStewardshipOnTheStrategicMap(): void
    {
        $user = $this->createUser(pro: true);
        $em = $this->em();
        $plan = new PlanDeVie();
        $em->find(User::class, $user->getId())->setPlanDeVie($plan);
        $em->persist($plan);
        $goal = (new LifeGoal())->setPlanDeVie($plan)->setTitle('Lancer un atelier pilote')->setCategory('DO')->setPriority('HIGH');
        $em->persist($goal);
        $em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/plan-de-vie');
        $this->assertSelectorTextContains('[data-map-missing-plans]', 'Intendance');

        $this->client->request('POST', '/plan-d-intendance/element', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'origin' => 'TEMPS', 'label' => 'Deux soirées par mois']);
        $item = $this->items($user)[0];
        $this->client->request('POST', '/plan-d-intendance/element/'.$item->getId(), ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'life_goal' => (string) $goal->getId()]);
        $this->assertSame($goal->getId(), $this->items($user)[0]->getLifeGoal()?->getId());

        $crawler = $this->client->request('GET', '/plan-de-vie');
        $this->assertStringContainsString('Stew'.$item->getId().'["Deux soirées par mois"]', $crawler->filter('#mermaid-code-src')->text());
        $this->assertSelectorTextNotContains('[data-map-missing-plans]', 'Intendance');
    }
}
