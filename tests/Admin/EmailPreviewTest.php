<?php

namespace App\Tests\Admin;

use App\Compte\Repository\UserRepository;
use App\Notification\Service\EmailCatalog;
use App\Tests\Compte\AccountTestTrait;
use App\Tests\Support\FixedCsrfTokenManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Admin › Réglages › Aperçu des e-mails (27/11/2026) : chaque e-mail du catalogue s'affiche avec son vrai gabarit,
 * chaque gabarit d'e-mail de l'application figure au catalogue, et l'essai ne part que vers l'administrateur.
 */
class EmailPreviewTest extends WebTestCase
{
    use AccountTestTrait;

    public function testEveryEmailRendersAndTheTestGoesOnlyToTheAdmin(): void
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $all = static::getContainer()->get(EmailCatalog::class)->all();

        foreach ($all as $key => $email) {
            $client->request('GET', '/admin/emails/'.$key);
            $this->assertResponseIsSuccessful($key);
            $this->assertSelectorExists('iframe.em-frame', $key);
            $this->assertSelectorTextContains('.em-meta', $email['subject'], $key);
        }

        $client->request('POST', '/admin/emails/j0/essai', ['_csrf_token' => FixedCsrfTokenManager::VALUE]);
        $this->assertResponseRedirects('/admin/emails/j0');
        $this->assertCount(1, $this->getMailerMessages());
        $this->assertEmailAddressContains($this->getMailerMessages()[0], 'To', 'admin@kiinisawa.com');
    }

    /** Un nouvel e-mail dans l'application doit être ajouté au catalogue, sinon l'aperçu serait incomplet. */
    public function testEveryEmailTemplateIsInTheCatalog(): void
    {
        self::bootKernel();
        $used = array_column(static::getContainer()->get(EmailCatalog::class)->all(), 'template');
        $root = static::getContainer()->getParameter('kernel.project_dir').'/templates/';
        $missing = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = substr($file->getPathname(), \strlen($root));
            if (!str_contains($path, '/email/') || str_contains(basename($path), '_')) {
                continue; // gabarits partiels (_layout, _base…) : inclus par les autres
            }
            if (!\in_array($path, $used, true)) {
                $missing[] = $path;
            }
        }
        $this->assertSame([], $missing, 'E-mail absent de l\'aperçu (EmailCatalog).');
    }

    public function testMembersCannotSeeThePreview(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/admin/emails');
        $this->assertResponseStatusCodeSame(403);
    }
}
