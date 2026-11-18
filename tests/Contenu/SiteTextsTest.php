<?php

namespace App\Tests\Contenu;

use App\Compte\Repository\UserRepository;
use App\Contenu\Entity\SiteText;
use App\Contenu\Entity\SiteTextVersion;
use App\Contenu\Service\SiteTextCatalog;
use App\Contenu\Service\SiteTexts;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Textes du site modifiables : brouillon → aperçu → publication, historique, retour au texte d'origine. */
class SiteTextsTest extends WebTestCase
{
    use AccountTestTrait;

    private const CODE = 'vitrine.s2.01';

    protected function tearDown(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE '.SiteTextVersion::class.' v')->execute();
        $em->createQuery('DELETE '.SiteText::class.' t')->execute();
        parent::tearDown();
    }

    private function admin(KernelBrowser $client): void
    {
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
    }

    private function original(): string
    {
        return static::getContainer()->get(SiteTextCatalog::class)->find(self::CODE)['default'];
    }

    private function edit(KernelBrowser $client, string $action, string $content = '', array $extra = []): void
    {
        $crawler = $client->request('GET', '/admin/textes/modifier?code='.self::CODE);
        $this->assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
        $client->request('POST', '/admin/textes/modifier?code='.self::CODE, ['_csrf_token' => $token, 'action' => $action, 'content' => $content] + $extra);
    }

    public function testCatalogFindsTheLandingAndTheLegalDocuments(): void
    {
        static::createClient();
        $all = static::getContainer()->get(SiteTextCatalog::class)->all();
        $this->assertGreaterThan(90, \count(array_filter(array_keys($all), fn ($c) => str_starts_with($c, 'vitrine.'))));
        foreach (['mentions_legales', 'confidentialite', 'cgu', 'cgv'] as $p) {
            $this->assertSame('markdown', $all['legal.'.$p]['format']);
        }
    }

    public function testNothingChangesWithoutAnOverride(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $this->assertStringContainsString(htmlspecialchars($this->original(), \ENT_QUOTES), $client->getResponse()->getContent());
    }

    public function testDraftIsInvisibleUntilPublished(): void
    {
        $client = static::createClient();
        $this->admin($client);
        $this->edit($client, 'draft', 'Titre en brouillon');
        $this->assertResponseRedirects();

        // Le public (et même l'administrateur hors aperçu) ne voit pas le brouillon
        $client->restart();
        $anon = $client;
        $anon->request('GET', '/');
        $this->assertStringNotContainsString('Titre en brouillon', $anon->getResponse()->getContent());

        $this->admin($client);
        $this->edit($client, 'publish', 'Titre publié');
        $client->restart();
        $anon->request('GET', '/');
        $this->assertStringContainsString('Titre publié', $anon->getResponse()->getContent());
        $this->assertCount(1, static::getContainer()->get(SiteTexts::class)->history(self::CODE));
    }

    public function testPreviewShowsDraftsToTheAdminOnly(): void
    {
        $client = static::createClient();
        $this->admin($client);
        $this->edit($client, 'draft', 'Aperçu seulement');

        $client->request('GET', '/admin/textes');
        $token = $client->getCrawler()->filter('form[action$="/admin/textes/apercu"] input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/admin/textes/apercu', ['_csrf_token' => $token]);

        $client->request('GET', '/?vitrine=1');
        $html = $client->getResponse()->getContent();
        $this->assertStringContainsString('Aperçu seulement', $html);
        $this->assertStringContainsString('Aperçu des brouillons', $html, 'bandeau d\'aperçu');

        $client->restart();
        $anon = $client;
        $anon->request('GET', '/');
        $this->assertStringNotContainsString('Aperçu seulement', $anon->getResponse()->getContent());
    }

    public function testRevertToOriginalAndRestoreAnOlderVersion(): void
    {
        $client = static::createClient();
        $this->admin($client);
        $this->edit($client, 'publish', 'Première version');
        $this->edit($client, 'publish', 'Deuxième version');
        $texts = static::getContainer()->get(SiteTexts::class);
        $this->assertSame('Deuxième version', $texts->get(self::CODE, 'x'));

        $this->edit($client, 'revert');
        static::getContainer()->get('doctrine')->getManager()->clear();
        $this->assertSame('défaut', static::getContainer()->get(SiteTexts::class)->get(self::CODE, 'défaut'));

        $versions = static::getContainer()->get(SiteTexts::class)->history(self::CODE);
        $this->assertCount(3, $versions, 'deux publications et un retour à l\'origine');
        $first = array_values(array_filter($versions, fn ($v) => 'Première version' === $v->getContent()))[0];
        $this->edit($client, 'restore', '', ['version' => $first->getId()]);
        static::getContainer()->get('doctrine')->getManager()->clear();
        $this->assertSame('Première version', static::getContainer()->get(SiteTexts::class)->row(self::CODE)->getDraft());
    }

    public function testEnteredHtmlIsNeutralised(): void
    {
        $client = static::createClient();
        $this->admin($client);
        $this->edit($client, 'publish', '<script>alert(1)</script> Bonjour');
        $client->restart();
        $anon = $client;
        $anon->request('GET', '/');
        $html = $anon->getResponse()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testLegalDocumentIsEditableAndTheTodoNoticeFollowsIt(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mentions-legales');
        $this->assertStringContainsString('Version de travail', $client->getResponse()->getContent());

        $this->admin($client);
        $crawler = $client->request('GET', '/admin/textes/modifier?code=legal.mentions_legales');
        $token = $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
        $client->request('POST', '/admin/textes/modifier?code=legal.mentions_legales', ['_csrf_token' => $token, 'action' => 'publish', 'content' => "## Éditeur\n\nKIINI SAWA SAS, 1 rue Exemple."]);

        $client->restart();
        $anon = $client;
        $anon->request('GET', '/mentions-legales');
        $html = $anon->getResponse()->getContent();
        $this->assertStringContainsString('KIINI SAWA SAS, 1 rue Exemple.', $html);
        $this->assertStringNotContainsString('Version de travail', $html, 'plus de passage à compléter : l\'avertissement disparaît');
    }

    public function testAdminAccessRulesAndUnknownCodes(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/admin/textes');
        $this->assertResponseStatusCodeSame(403);

        $client->restart();
        $this->admin($client);
        $client->request('GET', '/admin/textes');
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/admin/textes/modifier?code=inconnu.code');
        $this->assertResponseStatusCodeSame(404);
        $client->request('POST', '/admin/textes/modifier?code='.self::CODE, ['_csrf_token' => 'mauvais', 'action' => 'publish', 'content' => 'Pirate']);
        $this->assertSame($this->original(), static::getContainer()->get(SiteTexts::class)->get(self::CODE, $this->original()), 'sans jeton valide, rien ne change');
    }
}
