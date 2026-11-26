<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Service\TestActionService;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Point 7 (26/11/2026) : un don du rapport Dons spirituels rejoint « Dons et talents » du Plan de Vie, une seule fois. */
class GiftToLifePlanTest extends WebTestCase
{
    use AccountTestTrait;

    public function testGiftBecomesATalentOnce(): void
    {
        static::createClient();
        $user = $this->createUser();
        $service = static::getContainer()->get(TestActionService::class);

        $service->add($user, 'Encouragement', 'Dons spirituels', 'talent');
        $talents = array_filter($this->findUser((string) $user->getEmail())->getInternalBalances()->toArray(), fn ($ib) => 'TALENT' === $ib->getType());
        $this->assertSame(['Encouragement'], array_values(array_map(fn ($ib) => $ib->getText(), $talents)));

        $this->expectException(\InvalidArgumentException::class);
        $service->add($user, 'encouragement', 'Dons spirituels', 'talent');
    }
}
