<?php

namespace App\Tests\Dashboard;

use App\Dashboard\Entity\Quote;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Accueil réel : citation du jour issue de la base, raison d'être, prochaines échéances ;
 * pas d'impasse « Bientôt disponible ».
 */
class DashboardTest extends WebTestCase
{
    use AccountTestTrait;

    public function testDashboardShowsScheduledQuotePurposeAndDeadlines(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $quote = (new Quote())->setContent('Citation programmée pour aujourd\'hui '.uniqid())->setAuthor('Test')->setDisplayDate(new \DateTime('today'));
        $em->persist($quote);
        $em->flush();

        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $em->flush();
        $client->loginUser($user);
        $client->request('POST', '/plan-de-vie/assistant?step=1&single=1', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'purpose_statement' => 'Servir ma ville avec mes talents.']);
        $client->request('POST', '/plan-de-vie/assistant?step=7&single=1', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'life_goals_give' => ['Créer une école de leadership'], 'life_goals_give_deadlines' => [(new \DateTime('+6 months'))->format('Y-m-d')]]);

        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', $quote->getContent());
        $this->assertSelectorTextContains('main', 'Servir ma ville avec mes talents.');
        $this->assertSelectorTextContains('main', 'Créer une école de leadership');

        $em->remove($em->find(Quote::class, $quote->getId()));
        $em->flush();
    }

    public function testNoComingSoonDeadEnds(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $client->request('GET', '/social');
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('activeTab = \'CHALLENGES\'', $client->getResponse()->getContent());
    }

    /** Le prénom (jamais l'email) dans l'accueil et le menu, et les premiers pas guident la suite. */
    public function testGreetsByFirstNameAndShowsFirstSteps(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $profile = $user->getUserProfile() ?? (new \App\Compte\Entity\UserProfile())->setUser($user);
        $profile->setFirstName('Esther');
        $em->persist($profile);
        $em->flush();
        $client->loginUser($user);
        $client->request('POST', '/plan-de-vie/assistant?step=1&single=1', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE, 'purpose_statement' => 'Servir ma ville.']);

        $client->request('GET', '/dashboard');
        $this->assertSelectorTextContains('main h1', 'Bonjour, Esther');
        $this->assertSelectorTextContains('.topnav-user-name', 'Esther');
        $this->assertStringNotContainsString(ucfirst(explode('@', $user->getEmail())[0]), $client->getResponse()->getContent());
        $this->assertSelectorTextContains('main', 'Vos premiers pas');
        $this->assertSelectorTextContains('main', 'Écrire votre vision');
    }

    /**
     * Accueil réorganisé (27/11/2026, accord du fondateur) : plus de Note rapide (un lien ouvre l'écriture dans le Journal),
     * un seul bloc pour la prochaine étape et les premiers pas, rien de vide dans le bandeau, la date de naissance en bas.
     */
    public function testHomeIsFocusedOnTheDay(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/dashboard');
        $html = (string) $client->getResponse()->getContent();

        $this->assertStringNotContainsString('Note rapide', $html);
        $this->assertStringNotContainsString('/api/journal/entries/create', $html);
        $this->assertSelectorExists('.dash-hero a[href="/journal#ecrire"]');
        $this->assertStringNotContainsString('Aucun rituel aujourd', $html, 'Pas de tiret seul quand aucun rituel n\'est prévu');
        $this->assertCount(1, $crawler->filter('main')->reduce(fn ($n) => str_contains($n->text(), 'Vos premiers pas')));
        $this->assertLessThanOrEqual(1, substr_count($crawler->filter('main')->text(), 'Vos premiers pas'), 'Premiers pas affichés une seule fois');
        // Ordre : prochaine étape, puis rituels, puis la boussole ; la date de naissance tout en bas
        $positions = array_map(fn (string $needle) => strpos($html, $needle), ['data-next-step', 'Vos Rituels', 'Ma raison d\'être']);
        $this->assertSame($positions, array_values(array_filter($positions)), 'Blocs présents');
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        if (str_contains($html, 'data-birth-date-prompt')) {
            $this->assertGreaterThan(strpos($html, 'Ma raison d\'être'), strpos($html, 'data-birth-date-prompt'));
        }

        $client->request('GET', '/journal');
        $this->assertSelectorExists('textarea#ecrire');
    }
}
