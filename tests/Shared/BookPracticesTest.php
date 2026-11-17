<?php

namespace App\Tests\Shared;

use App\Compte\Entity\User;
use App\PlanDeTransmission\Entity\PlanDeTransmission;
use App\Rituels\Entity\Habit;
use App\Rituels\Entity\HabitLog;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pratiques du livre reprises dans l'application : phrase d'éternité (ch. 19), Le Sanctuaire dans les encarts,
 * « ne manque jamais deux fois » (ch. 22).
 */
class BookPracticesTest extends WebTestCase
{
    use AccountTestTrait;

    public function testEternityPhraseInTransmissionStep(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);

        $client->request('GET', '/plan-de-vie/assistant?step=11&single=1&plan=TRANSMISSION');
        $this->assertSelectorTextContains('[data-eternity]', 'Ma phrase d\'éternité');
        $client->request('POST', '/plan-de-vie/assistant?step=11&single=1&plan=TRANSMISSION', [
            'eternity_phrase' => 'Que ceux que j\'ai formés en forment d\'autres', 'treasure_shift' => 'Moins d\'écrans, plus de temps avec mes enfants',
        ]);
        $this->em()->clear();
        $plan = $this->em()->find(User::class, $user->getId())->getPlanDeTransmission();
        $this->assertInstanceOf(PlanDeTransmission::class, $plan);
        $this->assertSame('Que ceux que j\'ai formés en forment d\'autres', $plan->getEternityPhrase());

        $client->request('GET', '/plan-de-transmission');
        $this->assertSelectorTextContains('[data-eternity-phrase]', 'Que ceux que j\'ai formés en forment d\'autres');
    }

    public function testBookPanelsCarryTheSanctuary(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/plan-de-vie/assistant?step=1&single=1');
        $this->assertSelectorTextContains('.book-panel [data-sanctuary] summary', 'Le Sanctuaire');
    }

    public function testNeverMissTwice(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $em = $this->em();
        $habit = (new Habit())->setUser($em->find(User::class, $user->getId()))->setTitle('Prière du matin');
        $em->persist($habit);
        $em->flush();
        $em->getConnection()->executeStatement('UPDATE habit SET created_at = ? WHERE id = ?', [(new \DateTime('-10 days'))->format('Y-m-d H:i:s'), $habit->getId()]);
        $habitId = $habit->getId();
        $em->clear();

        $client->loginUser($user);
        $client->request('GET', '/mes-plans/rituels');
        $this->assertSelectorTextContains('[data-never-miss-twice]', 'Prière du matin');

        $em->persist((new HabitLog())->setHabit($em->find(Habit::class, $habitId))->setDate(new \DateTime('yesterday'))->setStatus(true));
        $em->flush();
        $client->request('GET', '/mes-plans/rituels');
        $this->assertSelectorNotExists('[data-never-miss-twice]');
    }
}
