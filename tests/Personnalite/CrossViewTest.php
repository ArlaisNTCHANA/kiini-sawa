<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\DiscBank;
use App\Personnalite\Psychometrie\Instruments;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Regard croisé : le DISC est confronté au style déduit du Big Five, sans jamais mélanger les scores. */
class CrossViewTest extends WebTestCase
{
    use AccountTestTrait;

    private function disc($user): int
    {
        $answers = array_fill_keys(array_map(fn ($b) => 'disc:'.$b, array_keys(DiscBank::BLOCKS)), 'SD');

        return static::getContainer()->get(AssessmentService::class)->record($user, 'disc', $answers, 400)->getId();
    }

    public function testInvitationWithoutBigFive(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $id = $this->disc($user);
        $client->loginUser($user);
        $client->request('GET', '/mes-tests/rapport/'.$id);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Regard croisé');
        $this->assertSelectorTextContains('body', 'Passez le Big Five essentiel');
    }

    public function testCrossViewAppearsWithBigFive(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $answers = [];
        foreach (Instruments::items('big-five') as $i => $item) {
            $answers[$item['key']] = 1 + ($i * 7 % 5);
        }
        static::getContainer()->get(AssessmentService::class)->record($user, 'big-five', $answers, 600);
        $id = $this->disc($user);
        $client->loginUser($user);
        $client->request('GET', '/mes-tests/rapport/'.$id);
        $this->assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent();
        $this->assertStringContainsString('Regard croisé ·', $html);
        $this->assertStringContainsString('D\'après votre Big Five essentiel', $html);
        $this->assertStringNotContainsString('Passez le Big Five essentiel', $html);
    }
}
