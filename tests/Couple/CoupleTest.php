<?php

namespace App\Tests\Couple;

use App\Abonnement\Service\Entitlements;
use App\Abonnement\Service\SubscriptionManager;
use App\Couple\Entity\Couple;
use App\Couple\Entity\CoupleItem;
use App\Couple\Service\CoupleService;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Espace couple : invitation, accès (les deux PRO / Duo), éléments communs, revue, rapport croisé.
 */
class CoupleTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function couples(): CoupleService
    {
        return static::getContainer()->get(CoupleService::class);
    }

    private function activeCouple(bool $pro = true): array
    {
        $a = $this->createUser(pro: $pro);
        $b = $this->createUser(pro: $pro);
        $couple = $this->couples()->create($a, null);
        $this->couples()->join($b, $couple, true);

        return [$couple, $a, $b];
    }

    public function testInvitationFlowWithAnonymousSpouse(): void
    {
        $a = $this->createUser();
        $this->client->loginUser($a);
        $crawler = $this->client->request('GET', '/couple');
        $this->client->submit($crawler->selectButton('Créer notre espace couple')->form(), ['email' => 'conjoint-'.uniqid().'@exemple.org']);
        $this->assertSame('Votre conjoint vous invite à bâtir votre couple sur KIINI SAWA', str_replace($a->getDisplayName(), 'Votre conjoint', $this->lastEmail()->getSubject()));
        $couple = $this->couples()->coupleOf($a);
        $this->assertFalse($couple->isActive());

        // Le conjoint ouvre le lien sans compte, puis après connexion revient sur l'invitation
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/couple/rejoindre/'.$couple->getInviteCode());
        $this->assertSelectorTextContains('h1', 'vous invite');
        $b = $this->createUser();
        $this->client->loginUser($b);
        $crawler = $this->client->request('GET', '/couple/rejoindre/'.$couple->getInviteCode());
        $form = $crawler->selectButton('Rejoindre notre espace couple')->form();
        $form['shareTests']->untick();
        $this->client->submit($form);
        $this->assertResponseRedirects('/couple');

        $this->em()->clear();
        $couple = $this->em()->find(Couple::class, $couple->getId());
        $this->assertTrue($couple->isActive());
        $this->assertFalse($couple->sharesTests($couple->getPartnerB()), 'Consentement respecté');
    }

    public function testSpaceLockedUntilBothProThenDuoUnlocksBoth(): void
    {
        [$couple, $a, $b] = $this->activeCouple(pro: false);
        $this->client->loginUser($a);
        $this->client->request('GET', '/couple');
        $this->assertSelectorTextContains('main', 'Choisir Duo');

        $manager = static::getContainer()->get(SubscriptionManager::class);
        $t = $manager->declare($a, 'DUO', 'XAF', 'MOBILE_MONEY', 'DUO-'.uniqid(), null, null);
        $this->assertSame(39000.0, $t->getAmount());
        $manager->validate($t);

        $entitlements = static::getContainer()->get(Entitlements::class);
        $this->assertTrue($entitlements->isPro($a));
        $this->assertTrue($entitlements->isPro($b), 'Un seul paiement Duo rend les deux conjoints PRO');
        $this->client->request('GET', '/couple');
        $this->assertSelectorTextContains('main', 'Notre vision commune');
    }

    public function testDuoRequiresACouple(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/abonnement');
        $token = $crawler->filter('input[name=_csrf_token]')->count() ? $crawler->filter('input[name=_csrf_token]')->first()->attr('value') : '';
        $this->client->request('POST', '/abonnement/declarer', ['period' => 'DUO', 'currency' => 'EUR', 'method' => 'BANK_TRANSFER', 'reference' => 'X', '_csrf_token' => $token]);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('main', 'nécessite un espace couple');
    }

    public function testSharedItemsVisionReviewAndIsolation(): void
    {
        [$couple, $a, $b] = $this->activeCouple();
        $this->client->loginUser($a);
        $crawler = $this->client->request('GET', '/couple');
        $token = $crawler->filter('form[action="/couple/vision"] input[name=_csrf_token]')->attr('value');

        $this->client->request('POST', '/couple/vision', ['vision' => 'Un foyer ouvert', 'verse' => 'Josué 24:15', '_csrf_token' => $token]);
        $this->client->request('POST', '/couple/elements', ['kind' => 'PROJECT', 'title' => 'Acheter notre maison', 'category' => 'HAVE', 'deadline' => '2028-06-01', '_csrf_token' => $token]);
        $this->client->request('POST', '/couple/elements', ['kind' => 'THEME', 'category' => 'FINANCES', '_csrf_token' => $token]);
        $this->client->request('POST', '/couple/revue', ['answers' => ['decision' => 'Un rendez-vous par mois'], '_csrf_token' => $token]);

        // Le conjoint voit et modifie les mêmes éléments
        $this->client->loginUser($b);
        $crawler = $this->client->request('GET', '/couple');
        $this->assertSelectorTextContains('main', 'Un foyer ouvert');
        $this->assertSelectorTextContains('main', 'Acheter notre maison');
        $this->assertSelectorTextContains('main', 'Finances');
        $project = $this->em()->getRepository(CoupleItem::class)->findOneBy(['couple' => $couple, 'kind' => 'PROJECT']);
        $tokenB = $crawler->filter('form[action="/couple/vision"] input[name=_csrf_token]')->attr('value');
        $this->client->request('POST', '/couple/elements/'.$project->getId(), ['progress' => 40, '_csrf_token' => $tokenB]);
        $this->em()->clear();
        $this->assertSame(40, $this->em()->find(CoupleItem::class, $project->getId())->getProgress());

        // Un autre couple ne peut pas y toucher
        [, $other] = $this->activeCouple();
        $this->client->loginUser($other);
        $crawler = $this->client->request('GET', '/couple');
        $tokenO = $crawler->filter('form[action="/couple/vision"] input[name=_csrf_token]')->attr('value');
        $this->client->request('POST', '/couple/elements/'.$project->getId(), ['action' => 'delete', '_csrf_token' => $tokenO]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCrossReportRespectsConsent(): void
    {
        [$couple, $a, $b] = $this->activeCouple();
        $service = static::getContainer()->get(AssessmentService::class);
        foreach ([$a, $b] as $i => $u) {
            $answers = [];
            foreach (Instruments::items('profil-complet') as $k => $item) {
                $answers[$item['key']] = 0 === $i ? 4 : (($k % 5) + 1);
            }
            $service->record($u, 'profil-complet', $answers, 900);
        }

        $this->client->loginUser($a);
        $this->client->request('GET', '/couple/rapport');
        $this->assertSelectorTextContains('main', 'Nos cinq grandes dimensions');
        $this->assertSelectorTextContains('main', 'Comment nous parler');

        $couple = $this->em()->find(Couple::class, $couple->getId());
        $couple->setSharesTests($couple->getPartnerB(), false);
        $this->em()->flush();
        $this->client->request('GET', '/couple/rapport');
        $this->assertSelectorTextContains('main', 'ne partage pas ses résultats');
        $this->assertSelectorTextNotContains('main', 'Nos cinq grandes dimensions');
    }

    public function testDissolveKeepsPersonalSpaces(): void
    {
        [$couple, $a] = $this->activeCouple();
        $this->client->loginUser($a);
        $this->client->request('POST', '/plan-de-vie/assistant?step=1&single=1', ['purpose_statement' => 'Mon but personnel']);
        $crawler = $this->client->request('GET', '/couple');
        $this->client->submit($crawler->selectButton('Fermer l\'espace couple')->form());
        $this->assertNull($this->couples()->coupleOf($this->findUser($a->getEmail())));
        $this->assertSame('Mon but personnel', $this->findUser($a->getEmail())->getPlanDeVie()->getPurpose()->getStatement());
    }
}
