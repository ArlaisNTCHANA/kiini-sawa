<?php

namespace App\Tests\Media;

use App\Compte\Repository\UserRepository;
use App\Media\Entity\SignupOrigin;
use App\Media\Service\RetentionReport;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RetentionReportTest extends WebTestCase
{
    use AccountTestTrait;

    public function testRatesByChannelAndAdminPage(): void
    {
        $client = static::createClient();
        $db = $this->em()->getConnection();
        $source = 'canal-test-'.substr(uniqid(), -6);
        $now = new \DateTimeImmutable();
        // Deux inscrits il y a 40 jours : l'un parti après 5 jours, l'autre encore actif
        foreach ([[-35, 'parti'], [-2, 'fidele']] as [$lastOffset, $tag]) {
            $u = $this->createUser();
            $this->em()->persist(new SignupOrigin($u, $source));
            $this->em()->flush();
            $db->executeStatement('UPDATE `user` SET created_at = :c, last_active_at = :l WHERE id = :id', [
                'c' => $now->modify('-40 days')->format('Y-m-d H:i:s'), 'l' => $now->modify($lastOffset.' days')->format('Y-m-d H:i:s'), 'id' => $u->getId(),
            ]);
        }

        $rows = array_column(static::getContainer()->get(RetentionReport::class)->byChannel($now), null, 'label');
        $this->assertSame(2, $rows[$source]['signups']);
        $this->assertEquals(50, $rows[$source]['rates'][7], 'Un sur deux revenu après 7 jours');
        $this->assertEquals(50, $rows[$source]['rates'][30]);
        $this->assertNull($rows[$source]['rates'][90], 'Trop récent pour J90');

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/retention');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Par semaine d\'inscription');
        $this->assertSelectorTextContains('body', ucfirst($source));
    }
}
