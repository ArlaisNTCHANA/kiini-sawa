<?php

namespace App\Tests\Abonnement;

use App\Abonnement\Entity\Transaction;
use App\Abonnement\Service\Entitlements;
use App\Abonnement\Service\SubscriptionManager;
use App\Admin\Entity\AppConfig;
use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use App\Notification\Entity\Notification;
use App\Notification\Service\ReminderService;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Offres gratuite et PRO, essai, paiement manuel validé par l'administration, reçus, rappels.
 */
class SubscriptionTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Moyens de paiement configurés, comme le fera l'administrateur
        $config = $this->em()->getRepository(AppConfig::class)->find('default') ?? (new AppConfig());
        $config->setBankTransferInstructions("Banque Exemple\nIBAN FR76 0000 0000 0000")->setMobileMoneyInstructions('Orange Money : 600 00 00 00');
        $this->em()->persist($config);
        $this->em()->flush();
    }

    private function entitlements(): Entitlements
    {
        return static::getContainer()->get(Entitlements::class);
    }

    public function testFreeUserIsSentToUpgradePageForProFeatures(): void
    {
        $this->client->loginUser($this->createUser());

        foreach (['/plan-de-croissance' => 'croissance', '/plan-de-transmission' => 'transmission', '/plan-de-vie/pdf' => 'pdf', '/bilan/revue-mensuelle' => 'monthly_review', '/plan-de-vie/historique' => 'plan_history', '/plan-de-vie/assistant?plan=HYGIENE' => 'hygiene', '/plan-de-vie/assistant?step=9&single=1' => 'croissance'] as $url => $feature) {
            $this->client->request('GET', $url);
            $this->assertResponseRedirects('/abonnement?fonction='.$feature, null, "$url doit être réservé à PRO");
        }

        // Le cœur reste gratuit
        foreach (['/plan-de-vie', '/plan-de-vie/assistant', '/mes-plans/rituels', '/journal', '/bilan/revue-hebdomadaire', '/methode', '/mes-strategies'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful("$url doit rester gratuit");
        }

        $crawler = $this->client->request('GET', '/abonnement?fonction=croissance');
        $this->assertSelectorTextContains('main', '« Plan de Croissance » fait partie de KIINI SAWA PRO');
        $this->assertSelectorTextContains('main', 'FCFA');
    }

    public function testFreeLimitsOnPartners(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/social/connection/add', ['email' => $this->createUser()->getEmail()]);
        $this->assertResponseIsSuccessful();
        $this->client->jsonRequest('POST', '/api/social/connection/add', ['email' => $this->createUser()->getEmail()]);
        $this->assertResponseStatusCodeSame(402, 'Deuxième partenaire refusé en gratuit');
        $this->assertStringContainsString('/abonnement?limite=connections', $this->client->getResponse()->getContent());
    }

    public function testSignupStartsTrial(): void
    {
        $crawler = $this->client->request('GET', '/inscription');
        $email = $this->uniqueEmail('essai');
        $form = $crawler->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $this->client->submit($form, ['firstName' => 'Ruth', 'email' => $email, 'password' => 'une phrase assez longue']);

        $user = $this->findUser($email);
        $this->assertTrue($this->entitlements()->isTrialing($user));
        $days = $this->entitlements()->config()->getTrialDurationDays();
        $this->assertEqualsWithDelta((new \DateTime("+$days days"))->getTimestamp(), $this->entitlements()->proUntil($user)->getTimestamp(), 120);
        $this->assertTrue($this->entitlements()->can($user, 'croissance'));
    }

    public function testManualPaymentValidatedByAdmin(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        // 1. Le client déclare un virement annuel en FCFA
        $ref = 'MP'.uniqid();
        $crawler = $this->client->request('GET', '/abonnement?devise=XAF');
        $this->assertSelectorTextContains('#payer', 'IBAN FR76');
        $token = $crawler->filter('form[action="/abonnement/declarer"] input[name=_csrf_token]')->attr('value');
        $this->client->request('POST', '/abonnement/declarer', ['period' => 'YEARLY', 'currency' => 'XAF', 'method' => 'MOBILE_MONEY', 'reference' => $ref, 'paidAt' => date('Y-m-d'), '_csrf_token' => $token]);
        $this->assertResponseRedirects('/abonnement');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('main', 'Paiement en cours de vérification');

        $t = $this->em()->getRepository(Transaction::class)->findOneBy(['reference' => $ref]);
        $this->assertSame(25000.0, $t->getAmount());
        $this->assertSame('XAF', $t->getCurrency());
        $this->assertFalse($this->entitlements()->isPro($this->findUser($user->getEmail())), 'Pas de PRO avant validation');

        // Les administrateurs sont prévenus
        $admin = static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com');
        $this->assertNotNull($this->em()->getRepository(Notification::class)->findOneBy(['user' => $admin, 'metadata' => 'paiement '.$t->getId()]));

        // 2. L'administrateur valide
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/paiements');
        $this->assertSelectorTextContains('body', $ref);
        $this->client->submit($crawler->filter('form[action="/admin/paiements/'.$t->getId().'/valider"]')->form());
        $this->assertResponseRedirects('/admin/paiements');

        $this->em()->clear();
        $u = $this->findUser($user->getEmail());
        $t = $this->em()->find(Transaction::class, $t->getId());
        $this->assertSame('VALIDATED', $t->getStatus());
        $this->assertMatchesRegularExpression('/^KS-\d{4}-\d{5}$/', $t->getReceiptNumber());
        $this->assertTrue($this->entitlements()->isPro($u));
        $this->assertEqualsWithDelta((new \DateTime('+1 year'))->getTimestamp(), $this->entitlements()->proUntil($u)->getTimestamp(), 120);
        $this->assertNotNull($this->em()->getRepository(Notification::class)->findOneBy(['user' => $u, 'title' => 'Paiement reçu : bienvenue en PRO !']));

        // 3. Le client télécharge son reçu ; personne d'autre ne le peut
        $this->client->loginUser($u);
        $this->client->request('GET', '/abonnement/recu/'.$t->getId());
        $this->assertResponseHeaderSame('content-type', 'application/pdf');
        $this->client->request('GET', '/plan-de-croissance');
        $this->assertResponseIsSuccessful('PRO débloqué');

        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/abonnement/recu/'.$t->getId());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testPaymentDuringTrialStartsAfterTrial(): void
    {
        $user = $this->createUser();
        $this->entitlements()->startTrial($user);
        $this->em()->flush();
        $manager = static::getContainer()->get(SubscriptionManager::class);
        $t = $manager->declare($user, 'MONTHLY', 'EUR', 'BANK_TRANSFER', 'VIR-1', null, null);
        $manager->validate($t);

        $days = $this->entitlements()->config()->getTrialDurationDays();
        $this->assertEqualsWithDelta((new \DateTime("+$days days"))->modify("+1 month")->getTimestamp(), $this->entitlements()->proUntil($user)->getTimestamp(), 120, 'Aucun jour d\'essai perdu');
    }

    public function testRejectedPaymentNotifiesCustomer(): void
    {
        $user = $this->createUser();
        $manager = static::getContainer()->get(SubscriptionManager::class);
        $t = $manager->declare($user, 'MONTHLY', 'EUR', 'BANK_TRANSFER', 'VIR-INCONNU', null, null);
        $manager->reject($t, 'Aucun virement reçu avec cette référence.');

        $this->assertSame('REJECTED', $t->getStatus());
        $this->assertFalse($this->entitlements()->isPro($user));
        $n = $this->em()->getRepository(Notification::class)->findOneBy(['user' => $user, 'metadata' => 'paiement '.$t->getId()]);
        $this->assertStringContainsString('Aucun virement reçu', $n->getMessage());
    }

    public function testAdminGiftAndEndingReminder(): void
    {
        $user = $this->createUser();
        static::getContainer()->get(SubscriptionManager::class)->gift($user, 1, 'Église pilote');
        $this->assertTrue($this->entitlements()->isPro($user));

        $end = \DateTimeImmutable::createFromInterface($this->entitlements()->proUntil($user));
        $reminders = static::getContainer()->get(ReminderService::class);
        $this->assertFalse($reminders->subscriptionEnding($user, $end->modify('-10 days')));
        $this->assertTrue($reminders->subscriptionEnding($user, $end->modify('-7 days')), 'Rappel 7 jours avant la fin (pas de renouvellement automatique)');
    }

    public function testPublicLandingShowsBothCurrencies(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Bâtissez une vie alignée');
        $this->assertStringContainsString('25 000 FCFA', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('79,99 €', $this->client->getResponse()->getContent());
    }
}
