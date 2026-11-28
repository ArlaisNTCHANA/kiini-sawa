<?php

namespace App\Tests\Admin;

use App\Abonnement\Entity\Transaction;
use App\Abonnement\Service\AccountingExport;
use App\Admin\Entity\AdminAction;
use App\Compte\Repository\UserRepository;
use App\Tests\Compte\AccountTestTrait;
use App\Tests\Support\FixedCsrfTokenManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Exports pour la comptabilité (28/11/2026) : un tableau lisible par Excel en français (point-virgule, accents,
 * montants à virgule), sans formule involontaire, sans les administrateurs dans la liste des membres, et chaque
 * téléchargement noté au journal.
 */
class AccountingExportTest extends WebTestCase
{
    use AccountTestTrait;

    public function testPaymentsAndMembersExportForAccounting(): void
    {
        $client = static::createClient();
        $member = $this->createUser(pro: true);
        $t = (new Transaction())->setSubscription($member->getSubscription())->setAmount(79.99)->setCurrency('EUR')
            ->setStatus(Transaction::STATUS_VALIDATED)->setMethod('MOBILE_MONEY')->setReference('=1+1 ; « réf »');
        $this->em()->persist($t);
        $this->em()->flush();

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/exports');
        $this->assertResponseIsSuccessful();

        $client->request('POST', '/admin/exports/paiements', ['_csrf_token' => FixedCsrfTokenManager::VALUE, 'annee' => (int) date('Y'), 'mois' => (int) date('n')]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $csv = (string) $client->getResponse()->getContent();
        $this->assertStringStartsWith("\u{FEFF}Date de déclaration;", $csv, 'Marque UTF-8 pour Excel, point-virgule');
        $this->assertStringContainsString((string) $member->getEmail(), $csv);
        $this->assertStringContainsString(';79,99;EUR;Mobile Money;', $csv);
        $this->assertStringContainsString('"\'=1+1 ; « réf »"', $csv, 'Pas de formule, point-virgule protégé');
        $action = $this->em()->getRepository(AdminAction::class)->findOneBy([], ['id' => 'DESC']);
        $this->assertStringContainsString('Export téléchargé : paiements des membres', $action->getMessage());

        $client->request('POST', '/admin/exports/membres', ['_csrf_token' => FixedCsrfTokenManager::VALUE]);
        $members = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString((string) $member->getEmail(), $members);
        $this->assertStringNotContainsString('admin@kiinisawa.com', $members, 'Les administrateurs ne sont pas des membres');
    }

    public function testCsvEscaping(): void
    {
        $this->assertSame("\u{FEFF}a;\"b;c\";\"d\"\"e\"\r\n", AccountingExport::csv([['a', 'b;c', 'd"e']]));
    }

    public function testMembersCannotExport(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/admin/exports');
        $this->assertResponseStatusCodeSame(403);
    }
}
