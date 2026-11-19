<?php

namespace App\Tests\Shared\Security;

use App\Compte\Entity\User;
use App\PlanDeCroissance\Entity\Principle;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\Tests\Compte\AccountTestTrait;
use App\Tests\Support\FixedCsrfTokenManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Audit de sécurité, lot 4 : jeton CSRF sur les formulaires qui n'en avaient pas (en plus du contrôle d'origine). */
class CsrfTokensTest extends WebTestCase
{
    use AccountTestTrait;

    private const TOKEN = ['_csrf_token' => FixedCsrfTokenManager::VALUE];

    private function pro(): array
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);

        return [$client, $user];
    }

    private function flash(KernelBrowser $client): string
    {
        return implode(' ', $client->getRequest()->getSession()->getFlashBag()->peekAll()['error'] ?? []);
    }

    /** @return iterable<string, array{array}> */
    public static function badTokens(): iterable
    {
        yield 'absent' => [[]];
        yield 'invalide' => [['_csrf_token' => 'faux-jeton']];
        yield 'vide' => [['_csrf_token' => '']];
    }

    // ── Plan d'Intendance ────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('badTokens')]
    public function testStewardshipActionsRefuseABadToken(array $token): void
    {
        [$client, $user] = $this->pro();
        $client->request('POST', '/plan-d-intendance/element', self::TOKEN + ['origin' => 'TEMPS', 'label' => 'Mes soirées']);
        $item = $this->em()->getRepository(StewardshipItem::class)->findOneBy(['user' => $user->getId()]);
        $this->assertNotNull($item, 'avec le bon jeton, l\'élément est créé');
        $id = $item->getId();

        $client->request('POST', '/plan-d-intendance/element', $token + ['origin' => 'FOYER', 'label' => 'Refusé']);
        $this->assertSame(1, $this->em()->getRepository(StewardshipItem::class)->count(['user' => $user->getId()]), 'ajout refusé');
        $this->assertStringContainsString('La page a expiré', $this->flash($client));

        $client->request('POST', "/plan-d-intendance/element/$id", $token + ['label' => 'Modifié', 'status' => 'DORT']);
        $this->em()->clear();
        $fresh = $this->em()->find(StewardshipItem::class, $id);
        $this->assertSame('Mes soirées', $fresh->getLabel(), 'modification refusée');
        $this->assertNull($fresh->getStatus());

        $client->request('POST', "/plan-d-intendance/element/$id/fait", $token);
        $this->em()->clear();
        $this->assertNull($this->em()->find(StewardshipItem::class, $id)->getActionDoneAt(), '« fait » refusé');

        $client->request('POST', '/plan-d-intendance/rythme', $token + ['frequency' => 'WEEKLY', 'review' => '1']);
        $this->assertNull($this->em()->getRepository(\App\PlanDIntendance\Entity\StewardshipRhythm::class)->findOneBy(['user' => $user->getId()]), 'rythme refusé');

        $client->request('POST', "/plan-d-intendance/element/$id/supprimer", $token);
        $this->em()->clear();
        $this->assertNotNull($this->em()->find(StewardshipItem::class, $id), 'suppression refusée');

        // Et avec le bon jeton, la suppression fonctionne
        $client->request('POST', "/plan-d-intendance/element/$id/supprimer", self::TOKEN);
        $this->em()->clear();
        $this->assertNull($this->em()->find(StewardshipItem::class, $id));
    }

    public function testStewardshipFormsCarryTheToken(): void
    {
        [$client] = $this->pro();
        $client->request('POST', '/plan-d-intendance/element', self::TOKEN + ['origin' => 'TEMPS', 'label' => 'Mes soirées']);
        $crawler = $client->request('GET', '/plan-d-intendance');
        $this->assertResponseIsSuccessful();
        $forms = $crawler->filter('form[action*="/plan-d-intendance/"]');
        $this->assertGreaterThanOrEqual(4, $forms->count());
        $this->assertSame($forms->count(), $crawler->filter('form[action*="/plan-d-intendance/"] input[name="_csrf_token"]')->count(), 'chaque formulaire porte son jeton');
    }

    // ── Principes et axes de croissance ──────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('badTokens')]
    public function testPrinciplesRefuseABadToken(array $token): void
    {
        [$client, $user] = $this->pro();
        $client->request('POST', '/principes/CROISSANCE', $token + ['custom_principle_titles' => ['Intégrité'], 'custom_principle_descriptions' => ['x']]);
        $this->assertResponseRedirects();
        $this->assertSame(0, $this->em()->getRepository(Principle::class)->count(['user' => $user->getId()]));
        $this->assertStringContainsString('La page a expiré', $this->flash($client));

        $client->request('POST', '/principes/CROISSANCE', self::TOKEN + ['custom_principle_titles' => ['Intégrité'], 'custom_principle_descriptions' => ['x']]);
        $this->assertSame(1, $this->em()->getRepository(Principle::class)->count(['user' => $user->getId()]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badTokens')]
    public function testGrowthAxisRefusesABadToken(array $token): void
    {
        [$client] = $this->pro();
        $client->request('POST', '/plan-de-croissance/axe/CONVICTION/1', $token + ['strategy' => 'x']);
        $this->assertResponseRedirects();
        $this->assertStringContainsString('La page a expiré', $this->flash($client));
    }

    // ── Assistant du Plan de Vie ─────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('badTokens')]
    public function testWizardRefusesABadToken(array $token): void
    {
        [$client, $user] = $this->pro();
        $client->request('POST', '/plan-de-vie/assistant?step=1&single=1', $token + ['purpose_statement' => 'Je suis créé pour former.']);
        $this->assertResponseRedirects();
        $this->assertStringContainsString('La page a expiré', $this->flash($client));
        $this->em()->clear();
        $this->assertNull($this->findUser($user->getEmail())->getPlanDeVie()?->getPurpose()?->getStatement(), 'rien n\'est enregistré');

        $client->request('POST', '/plan-de-vie/assistant?step=1&single=1', $token + ['autosave' => '1', 'autosave_step' => '1', 'purpose_statement' => 'Autosave refusé']);
        $this->assertResponseStatusCodeSame(400);
        $this->assertFalse(json_decode($client->getResponse()->getContent(), true)['success']);

        $client->request('POST', '/plan-de-vie/assistant?step=1&single=1', self::TOKEN + ['purpose_statement' => 'Je suis créé pour former afin d\'apporter la lumière chez les jeunes.']);
        $this->em()->clear();
        $this->assertNotNull($this->findUser($user->getEmail())->getPlanDeVie()->getPurpose()->getStatement());
    }

    public function testWizardAutosaveStillWorksWithTheToken(): void
    {
        [$client] = $this->pro();
        $client->request('POST', '/plan-de-vie/assistant?step=1&single=1', self::TOKEN + ['autosave' => '1', 'autosave_step' => '1', 'purpose_statement' => 'Je suis créé pour former afin d\'apporter la lumière chez les jeunes.']);
        $this->assertResponseIsSuccessful();
        $this->assertTrue(json_decode($client->getResponse()->getContent(), true)['success']);
    }

    public function testWizardFormsAndTheAutosaveCarryTheToken(): void
    {
        [$client] = $this->pro();
        $crawler = $client->request('GET', '/plan-de-vie/assistant?step=1&single=1');
        $this->assertResponseIsSuccessful();
        $this->assertGreaterThanOrEqual(1, $crawler->filter('form[action*="/plan-de-vie/assistant"] input[name="_csrf_token"]')->count());
        // L'enregistrement automatique envoie FormData(form) : le jeton caché part avec
        $js = (string) file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/wizard_controller.js');
        $this->assertStringContainsString('new FormData(form)', $js);
    }

    // ── Auto-évaluations (anciens tests courts) ─────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('badTokens')]
    public function testSelfAssessmentSubmissionRefusesABadToken(array $token): void
    {
        [$client, $user] = $this->pro();
        $before = $this->em()->getRepository(\App\Personnalite\Entity\ProfileTest::class)->count(['user' => $user->getId()]);
        $client->request('POST', '/mes-tests/equilibre/passer', $token + ['answers' => ['x' => 5]]);
        $this->assertResponseRedirects('/mes-tests/equilibre/passer');
        $this->assertSame($before, $this->em()->getRepository(\App\Personnalite\Entity\ProfileTest::class)->count(['user' => $user->getId()]));
        $this->assertStringContainsString('La page a expiré', $this->flash($client));
    }

    public function testTheSelfAssessmentFormCarriesItsToken(): void
    {
        // Les questions des auto-évaluations viennent de la base (absentes en test) : on contrôle le gabarit
        $src = (string) file_get_contents(\dirname(__DIR__, 3).'/templates/personnalite/test.html.twig');
        $this->assertMatchesRegularExpression('/<form id="test-form"[^>]*>\s*<input type="hidden" name="_csrf_token" value="\{\{ csrf_token\(\'personality_test_\' ~ type\|lower\) \}\}">/', $src);
    }

    public function testPlansPageFormsCarryTheirTokens(): void
    {
        $src = (string) file_get_contents(\dirname(__DIR__, 3).'/templates/plan_de_vie/plans.html.twig');
        $this->assertStringContainsString("csrf_token('growth_axis')", $src);
        $this->assertStringContainsString("csrf_token('principles')", $src);
    }
}
