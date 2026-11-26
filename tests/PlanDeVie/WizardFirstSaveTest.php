<?php

namespace App\Tests\PlanDeVie;

use App\Compte\Entity\User;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Premier enregistrement d'un plan qui n'existe pas encore : plusieurs blocs du même envoi
 * demandent le plan, il ne doit être créé qu'une fois (sinon doublon refusé par la base, erreur 500 :
 * Plan de Transmission, parcours du 25/11/2026).
 */
class WizardFirstSaveTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->user = $this->createUser(pro: true);
        $this->client->loginUser($this->user);
    }

    private function post(string $query, array $data): void
    {
        $this->client->request('POST', '/plan-de-vie/assistant?'.$query, $data + ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE]);
    }

    public function testFirstTransmissionSaveCreatesOnePlan(): void
    {
        $this->assertNull($this->findUser($this->user->getEmail())->getPlanDeTransmission());

        $this->post('step=11&single=0&plan=TRANSMISSION', [
            'eternity_phrase' => 'Que des familles se souviennent qu\'on a pris soin d\'elles.',
            'treasure_shift' => 'Moins d\'écrans, plus de temps à former deux jeunes.',
            'legacy_title' => ['Former une équipe de bénévoles'],
            'legacy_status' => ['build'],
        ]);
        $this->assertResponseRedirects();

        $plan = $this->findUser($this->user->getEmail())->getPlanDeTransmission();
        $this->assertNotNull($plan);
        $this->assertSame('Que des familles se souviennent qu\'on a pris soin d\'elles.', $plan->getEternityPhrase());
        $this->assertCount(1, $plan->getLegacyGoals());
        $this->assertSame('Former une équipe de bénévoles', $plan->getLegacyGoals()->first()->getTitle());
    }

    public function testFirstCroissanceSaveWithSeveralStepsCreatesOnePlan(): void
    {
        $this->post('single=0&plan=CROISSANCE', [
            'growth_character_title' => ['Patience à la maison'],
            'growth_relation_title' => ['Un mentor'],
            'growth_relation_type' => ['orientation'],
        ]);
        $this->assertResponseRedirects();

        $plan = $this->findUser($this->user->getEmail())->getPlanDeCroissance();
        $this->assertNotNull($plan);
        $this->assertCount(2, $plan->getGrowthGoals());
    }
}
