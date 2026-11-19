<?php

namespace App\Tests\PlanDeVie;

use App\Compte\Entity\User;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\Value;
use App\Rituels\Entity\Habit;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'assistant met à jour les éléments existants au lieu de tout effacer puis recréer :
 * les identifiants, les liens (rituels, axes de croissance, objectifs soutenus)
 * et la progression survivent à un nouvel enregistrement.
 */
class WizardIncrementalSaveTest extends WebTestCase
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

    private function goals(): array
    {
        return $this->findUser($this->user->getEmail())->getPlanDeVie()->getLifeGoals()->toArray();
    }

    public function testResavingKeepsIdsLinksAndProgress(): void
    {
        $this->post('step=7&single=1', ['life_goals_do' => ['Écrire un livre'], 'life_goals_do_deadlines' => ['2028-01-01']]);
        $goal = $this->goals()[0];
        $goalId = $goal->getId();

        // Données liées à l'objectif : progression, rituel rattaché
        $em = $this->em();
        $goal = $em->find(LifeGoal::class, $goalId);
        $goal->setProgress(40);
        $habit = (new Habit())->setUser($em->find(User::class, $this->user->getId()))->setTitle('Écrire 30 minutes')->setLifeGoal($goal);
        $em->persist($habit);
        $em->flush();
        $habitId = $habit->getId();

        // Nouvel enregistrement de l'étape, formulaire avec identifiant et titre modifié
        $this->post('step=7&single=1', [
            'life_goals_do' => ['Écrire et publier un livre', 'Courir un marathon'],
            'life_goals_do_ids' => [$goalId, ''],
            'life_goals_do_deadlines' => ['2028-06-01', ''],
        ]);
        $this->assertResponseRedirects('/plan-de-vie');

        $goals = $this->goals();
        $this->assertCount(2, $goals);
        $kept = $this->em()->find(LifeGoal::class, $goalId);
        $this->assertNotNull($kept, 'L\'objectif existant est mis à jour, pas recréé');
        $this->assertSame('Écrire et publier un livre', $kept->getTitle());
        $this->assertSame(40, $kept->getProgress());
        $this->assertSame('2028-06-01', $kept->getDeadline()->format('Y-m-d'));
        $this->assertSame($goalId, $this->em()->find(Habit::class, $habitId)->getLifeGoal()?->getId(), 'Le rituel reste rattaché');

        // Ancien formulaire sans identifiants : rapprochement par le titre
        $this->post('step=7&single=1', ['life_goals_do' => ['Écrire et publier un livre'], 'life_goals_do_deadlines' => ['2028-06-01']]);
        $this->assertNotNull($this->em()->find(LifeGoal::class, $goalId));
        $this->assertCount(1, $this->goals(), 'L\'objectif retiré du formulaire est supprimé');
    }

    public function testValueKeepsGrowthAxisBuiltOnIt(): void
    {
        $this->post('step=2&single=1', ['values_declared' => ['Humilité']]);
        $value = $this->findUser($this->user->getEmail())->getPlanDeVie()->getValues()->first();
        $valueId = $value->getId();

        // Axe de croissance né de cette valeur (page Plan de Croissance)
        $this->client->request('POST', "/plan-de-croissance/axe/DECLARED_VALUE/$valueId", ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'strategy' => 'Écouter avant de parler', 'status' => 'build', 'habit_titles' => ['Demander un retour chaque semaine'], 'habit_frequencies' => ['WEEKLY']]);
        $this->assertResponseRedirects('/plan-de-croissance?tab=SYSTEM');
        $axis = $this->em()->getRepository(GrowthGoal::class)->findOneBy(['sourceType' => 'DECLARED_VALUE', 'sourceId' => $valueId]);
        $habitId = $axis->getHabits()->first()->getId();

        // L'assistant de la Croissance est réenregistré (étape 2 complète)
        $this->post('plan=CROISSANCE&step=2&single=1', ['values_declared' => ['Humilité', 'Service'], 'values_declared_ids' => [$valueId, '']]);
        $this->assertNotNull($this->em()->find(Value::class, $valueId), 'La valeur garde son identifiant : l\'axe qui en dépend reste valide');

        // Réenregistrer l'axe met à jour le rituel au lieu d'effacer son historique
        $this->client->request('POST', "/plan-de-croissance/axe/DECLARED_VALUE/$valueId", ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'strategy' => 'Écouter avant de parler', 'status' => 'consolidate', 'habit_ids' => [$habitId], 'habit_titles' => ['Demander un retour chaque semaine'], 'habit_frequencies' => ['WEEKLY']]);
        $this->assertNotNull($this->em()->find(Habit::class, $habitId), 'Le rituel (et son historique) est conservé');
    }

    public function testCannotLinkAnotherUsersGoal(): void
    {
        $other = $this->createUser(pro: true);
        $this->client->loginUser($other);
        $this->post('step=7&single=1', ['life_goals_do' => ['Objectif privé de quelqu\'un d\'autre']]);
        $foreignGoalId = $this->findUser($other->getEmail())->getPlanDeVie()->getLifeGoals()->first()->getId();

        $this->client->loginUser($this->user);
        $this->post('plan=CROISSANCE&step=9&single=1', ['growth_character_title' => ['Patience'], 'growth_character_goals' => [[$foreignGoalId]]]);

        $goal = $this->em()->getRepository(GrowthGoal::class)->findOneBy(['title' => 'Patience', 'planDeCroissance' => $this->findUser($this->user->getEmail())->getPlanDeCroissance()]);
        $this->assertNotNull($goal);
        $this->assertCount(0, $goal->getSupportedLifeGoals(), 'Un objectif d\'un autre utilisateur ne peut pas être lié');
    }

    public function testStepAutosaveReturnsIdsForNewRows(): void
    {
        $this->client->request('POST', '/plan-de-vie/assistant', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 
            'autosave' => '1',
            'autosave_step' => '3',
            'passions' => ['Musique', '', 'Lecture'],
            'passions_ids' => ['', '', ''],
        ]);
        $this->assertResponseIsSuccessful();
        $ids = json_decode($this->client->getResponse()->getContent(), true)['ids']['passions'];
        $this->assertIsInt($ids[0]);
        $this->assertNull($ids[1], 'Ligne vide ignorée');
        $this->assertIsInt($ids[2]);

        // Second enregistrement avec les identifiants reçus : pas de doublon
        $this->client->request('POST', '/plan-de-vie/assistant', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'autosave' => '1', 'autosave_step' => '3', 'passions' => ['Musique', 'Lecture'], 'passions_ids' => [$ids[0], $ids[2]]]);
        $this->assertCount(2, $this->findUser($this->user->getEmail())->getPlanDeVie()->getPassions());
    }
}
