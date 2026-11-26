<?php

namespace App\Tests\Academie;

use App\Academie\Entity\CourseEnrollment;
use App\Academie\Exception\PracticeRefused;
use App\Academie\Service\AcademyPlanBridge;
use App\Academie\Service\PracticeCatalog;
use App\Admin\Entity\AppConfig;
use App\PlanDeTransmission\Entity\LegacyGoal;
use App\PlanDeTransmission\Entity\PlanDeTransmission;
use App\Rituels\Entity\Habit;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le pont entre les leçons et les plans : chaque exercice du catalogue crée un vrai élément, le refaire le met à jour
 * sans doublon, un élément d'un autre membre n'est jamais touché, et les droits de l'offre sont respectés.
 */
class AcademyPlanBridgeTest extends KernelTestCase
{
    use AccountTestTrait;
    use AcademyFixturesTrait;

    private function bridge(): AcademyPlanBridge
    {
        return static::getContainer()->get(AcademyPlanBridge::class);
    }

    public function testEveryExerciseCreatesThenUpdatesTheSameEntry(): void
    {
        self::bootKernel();
        $user = $this->createUser(pro: true);
        // L'action de transmission se rattache à un legs existant
        $plan = (new PlanDeTransmission())->setUser($user);
        $user->setPlanDeTransmission($plan);
        $legacy = (new LegacyGoal())->setTitle('Transmettre la foi');
        $plan->addLegacyGoal($legacy);
        $this->em()->persist($plan);
        $this->em()->persist($legacy);
        $this->em()->flush();

        $course = $this->makeCourse('VIE');
        $lesson = $course->getLessons()[0];
        $enrollment = new CourseEnrollment($user, $course);
        $this->em()->persist($enrollment);

        foreach (PracticeCatalog::TARGETS as $target => $def) {
            if ('COUPLE' === $target) {
                continue; // demande un couple relié : voir testCoupleAndJournalRules
            }
            foreach (array_keys($def['actions']) as $action) {
                if (\App\Academie\Service\PlanRubrics::isRubric($action)) {
                    continue; // séance d'un parcours de plan (PlanRubrics), voir PlanRubricsTest
                }
                $choice = 'add_transmission_action' === $action ? (string) $legacy->getId() : null;
                $first = $this->bridge()->save($user, $lesson, $target, $action, 'Réponse '.$action, 'Détail', $choice);
                $entity = $this->bridge()->find($user, $first['type'], $first['id']);
                $this->assertNotNull($entity, $action.' crée un élément qui appartient au membre');

                $completion = $enrollment->complete($lesson)->recordPractice([], $first['type'], $first['id']);
                $second = $this->bridge()->save($user, $lesson, $target, $action, 'Réponse modifiée', null, $choice, $completion);
                $this->assertSame($first, $second, $action.' : refaire l\'exercice met à jour le même élément');
            }
        }
        $this->em()->flush();
    }

    public function testAnotherMembersEntryIsNeverTouched(): void
    {
        self::bootKernel();
        $owner = $this->createUser(pro: true);
        $intruder = $this->createUser(pro: true);
        $course = $this->makeCourse('VIE');
        $lesson = $course->getLessons()[0];
        $saved = $this->bridge()->save($owner, $lesson, 'VIE', 'add_value', 'Fidélité', null, null);

        $this->assertNull($this->bridge()->find($intruder, $saved['type'], $saved['id']));
        $enrollment = new CourseEnrollment($intruder, $course);
        $forged = $enrollment->complete($lesson)->recordPractice([], $saved['type'], $saved['id']);
        $other = $this->bridge()->save($intruder, $lesson, 'VIE', 'add_value', 'Pirate', null, null, $forged);
        $this->assertNotSame($saved['id'], $other['id'], 'Un identifiant forgé crée un nouvel élément chez soi.');
        $this->em()->clear();
        $this->assertSame('Fidélité', $this->em()->find(\App\PlanDeVie\Entity\Value::class, $saved['id'])->getName());
    }

    public function testHabitLimitOfTheFreeOffer(): void
    {
        self::bootKernel();
        $config = $this->em()->getRepository(AppConfig::class)->find('default');
        [$habitsFree, $max] = [$config->isEnableHabitsFree(), $config->getMaxHabitsFree()];
        $config->setEnableHabitsFree(true)->setMaxHabitsFree(1);
        $this->em()->flush();
        try {
            $user = $this->createUser();
            $habit = (new Habit())->setUser($user)->setTitle('Prier le matin');
            $user->addHabit($habit);
            $this->em()->persist($habit);
            $this->em()->flush();
            $lesson = $this->makeCourse('VIE')->getLessons()[0];

            $this->assertStringContainsString('limite de rituels', (string) $this->bridge()->blocker($user, 'RITUEL', 'add_habit'));
            $this->expectException(PracticeRefused::class);
            $this->bridge()->save($user, $lesson, 'RITUEL', 'add_habit', 'Lire la Bible', null, null);
        } finally {
            $config = $this->em()->getRepository(AppConfig::class)->find('default');
            $config->setEnableHabitsFree($habitsFree)->setMaxHabitsFree($max);
            $this->em()->flush();
        }
    }

    public function testCoupleAndJournalRules(): void
    {
        self::bootKernel();
        $config = $this->em()->getRepository(AppConfig::class)->find('default');
        $journalFree = $config->isEnableJournalFree();
        $config->setEnableJournalFree(false);
        $this->em()->flush();
        try {
            $user = $this->createUser();
            $this->assertStringContainsString('Sanctuaire du Couple', (string) $this->bridge()->blocker($user, 'COUPLE', 'add_couple_item'));
            $this->assertNull($this->bridge()->blocker($user, 'JOURNAL', 'add_note'), 'Écrire dans le Journal depuis une leçon est permis à tous.');
            $this->assertNotNull($this->bridge()->blocker($user, 'VIE', 'update_purpose'), 'L\'Académie ne remplace jamais la raison d\'être.');
            $this->assertStringContainsString('pas encore de legs', (string) $this->createUserAndAskTransmission());
        } finally {
            $config = $this->em()->getRepository(AppConfig::class)->find('default');
            $config->setEnableJournalFree($journalFree);
            $this->em()->flush();
        }
    }

    private function createUserAndAskTransmission(): ?string
    {
        return $this->bridge()->blocker($this->createUser(pro: true), 'TRANSMISSION', 'add_transmission_action');
    }
}
