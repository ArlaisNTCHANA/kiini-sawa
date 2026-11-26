<?php

namespace App\Tests\Methode;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Point 8 (26/11/2026) : un chapitre ouvert depuis un plan propose d'y revenir ; jamais vers un autre site. */
class ChapterReturnTest extends WebTestCase
{
    use AccountTestTrait;

    public function testChapterOffersToGoBackToThePlan(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/methode/vision?retour=/plan-de-vie/assistant?step=6');
        $this->assertSelectorExists('[data-chapter-return] a[href="/plan-de-vie/assistant?step=6"]');

        foreach (['//exemple.org', 'https://exemple.org', '/\\exemple.org', ''] as $bad) {
            $client->request('GET', '/methode/vision?retour='.rawurlencode($bad));
            $this->assertSelectorNotExists('[data-chapter-return]', $bad);
        }
    }
}
