<?php

namespace App\Tests\PlanDeVie;

use App\Compte\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Export PDF du Plan de Vie et du Plan de Croissance.
 */
class PlanPdfTest extends WebTestCase
{
    public function testPlansDownloadAsPdf(): void
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        foreach (['/plan-de-vie/pdf' => 'plan-de-vie', '/plan-de-croissance/pdf' => 'plan-de-croissance'] as $url => $name) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful();
            $this->assertResponseHeaderSame('content-type', 'application/pdf');
            $this->assertStringContainsString("kiini-sawa-$name-", $client->getResponse()->headers->get('content-disposition'));
            $this->assertStringStartsWith('%PDF-', $client->getResponse()->getContent());
        }
    }

    public function testPdfRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/plan-de-vie/pdf');
        $this->assertResponseRedirects('/login');
    }
}
