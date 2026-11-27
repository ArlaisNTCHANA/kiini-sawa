<?php

namespace App\Tests\Admin;

use App\Academie\Entity\CourseEnrollment;
use App\Academie\Entity\LessonCompletion;
use App\Academie\Service\AcademyStats;
use App\Compte\Repository\UserRepository;
use App\Tests\Academie\AcademyFixturesTrait;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Suivi de l'Académie (27/11/2026) : séance par séance, combien l'ont faite, et la plus forte perte. Des totaux, jamais
 * un nom.
 */
class AcademyStatsTest extends WebTestCase
{
    use AcademyFixturesTrait;
    use AccountTestTrait;

    public function testShowsWhereMembersStopWithoutNamingThem(): void
    {
        $client = static::createClient();
        $course = $this->makeCourse(lessons: [['Une', 'VIE', 'add_value'], ['Deux', 'VIE', 'add_value'], ['Trois', null, null]]);
        [$l1, $l2, $l3] = $course->getLessons();
        // Trois inscrits : 3 font la séance 1, 2 la séance 2, personne la 3 → c'est avant la 3 que l'on s'arrête le plus
        $members = [];
        foreach ([[$l1, $l2], [$l1, $l2], [$l1]] as $done) {
            $member = $this->createUser();
            $members[] = $member;
            $e = new CourseEnrollment($member, $course);
            $this->em()->persist($e);
            foreach ($done as $lesson) {
                $this->em()->persist(new LessonCompletion($e, $lesson));
            }
        }
        $this->em()->flush();

        $stats = static::getContainer()->get(AcademyStats::class)->course($course);
        $this->assertSame(3, $stats['enrolled']);
        $this->assertSame([3, 2, 0], array_column($stats['lessons'], 'done'));
        $this->assertSame(2, $stats['worst']);
        $row = array_values(array_filter(static::getContainer()->get(AcademyStats::class)->overview(), fn ($r) => $r['course']->getId() === $course->getId()))[0];
        $this->assertSame([3, 0], [$row['enrolled'], $row['completed']]);

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/academie/suivi/'.$course->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Là où l\'on s\'arrête le plus');
        $this->assertSelectorTextContains('.st-list .is-worst', 'Trois');
        foreach ($members as $m) {
            $this->assertStringNotContainsString((string) $m->getEmail(), (string) $client->getResponse()->getContent());
        }

        $client->request('GET', '/admin/academie/suivi');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/admin/academie/suivi/'.$course->getId().'"]');
    }

    public function testMembersCannotSeeTheTracking(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/admin/academie/suivi');
        $this->assertResponseStatusCodeSame(403);
    }
}
