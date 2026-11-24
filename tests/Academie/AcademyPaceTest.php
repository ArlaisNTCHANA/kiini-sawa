<?php

namespace App\Tests\Academie;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseEnrollment;
use App\Academie\Service\AcademyPaceMailer;
use App\Compte\Entity\User;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\UriSigner;

/**
 * Rythme de l'Académie par e-mail (Lot 3) : leçon du jour selon le rythme choisi, jamais deux fois le même jour, pause
 * automatique et bienveillante après 3 e-mails sans avancer, reprise en un clic, mise en pause depuis l'e-mail.
 */
class AcademyPaceTest extends WebTestCase
{
    use AccountTestTrait;
    use AcademyFixturesTrait;

    private const MONDAY = '2026-11-23';

    private function enroll(User $user, Course $course, string $pace): CourseEnrollment
    {
        $enrollment = (new CourseEnrollment($user, $course))->setPace($pace);
        $this->em()->persist($enrollment);
        $this->em()->flush();

        return $enrollment;
    }

    private function mailer(): AcademyPaceMailer
    {
        return static::getContainer()->get(AcademyPaceMailer::class);
    }

    private function day(int $offset): \DateTimeImmutable
    {
        return (new \DateTimeImmutable(self::MONDAY))->modify('+'.$offset.' days');
    }

    private function twoLessons(bool $free = true): Course
    {
        return $this->makeCourse('VIE', free: $free, lessons: [['Ce qui compte', 'VIE', 'add_value'], ['Passer à l\'action', null, null]]);
    }

    public function testDailyPaceSendsOncePerDayThenPausesAfterThreeEmailsWithoutProgress(): void
    {
        static::createClient();
        $enrollment = $this->enroll($this->createUser(), $this->twoLessons(), CourseEnrollment::PACE_DAILY);

        $this->assertSame('lecons', $this->mailer()->sendFor($enrollment, $this->day(0)));
        $this->assertCount(1, $this->getMailerMessages());
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'Subject', 'Votre leçon du jour : Ce qui compte');
        $this->assertEmailHtmlBodyContains($email, 'Lire la leçon');
        $this->assertEmailHtmlBodyContains($email, '/academie/'.$enrollment->getCourse()->getSlug().'/lecon-1');
        $this->assertEmailHtmlBodyContains($email, 'Mettre en pause les e-mails de l&#039;Académie');

        $this->assertNull($this->mailer()->sendFor($enrollment, $this->day(0)), 'Pas de doublon le même jour.');
        $this->assertSame('lecons', $this->mailer()->sendFor($enrollment, $this->day(1)));
        $this->assertSame('lecons', $this->mailer()->sendFor($enrollment, $this->day(2)));
        $this->assertSame(3, $enrollment->getEmailsWithoutProgress());

        // 4e jour sans avancer : pas de 4e leçon, un message de pause bienveillant
        $this->assertSame('pauses', $this->mailer()->sendFor($enrollment, $this->day(3)));
        $this->assertTrue($enrollment->isPacePaused());
        $this->assertNull($this->mailer()->sendFor($enrollment, $this->day(4)), 'Plus rien pendant la pause.');
        $this->assertNull($this->mailer()->sendFor($enrollment, $this->day(5)));
    }

    public function testFinishingALessonResetsTheCounterAndMovesToTheNextLesson(): void
    {
        static::createClient();
        $course = $this->twoLessons();
        $enrollment = $this->enroll($this->createUser(), $course, CourseEnrollment::PACE_DAILY);
        $this->mailer()->sendFor($enrollment, $this->day(0));
        $this->mailer()->sendFor($enrollment, $this->day(1));
        $enrollment->complete($course->getLessons()[0]);
        $this->assertSame(0, $enrollment->getEmailsWithoutProgress());

        $this->assertSame('lecons', $this->mailer()->sendFor($enrollment, $this->day(2)));
        $messages = $this->getMailerMessages();
        $this->assertEmailHeaderSame(end($messages), 'Subject', 'Votre leçon du jour : Passer à l\'action');

        $enrollment->complete($course->getLessons()[1]);
        $this->assertTrue($enrollment->isCompleted());
        $this->assertNull($this->mailer()->sendFor($enrollment, $this->day(3)), 'Parcours terminé : plus d\'e-mail.');
    }

    public function testThreePerWeekFreeModeAndProCourses(): void
    {
        static::createClient();
        $user = $this->createUser();
        $three = $this->enroll($user, $this->twoLessons(), CourseEnrollment::PACE_THREE_PER_WEEK);
        $this->assertNull($this->mailer()->sendFor($three, $this->day(1)), 'Mardi : pas de leçon.');
        $this->assertSame('lecons', $this->mailer()->sendFor($three, $this->day(2)), 'Mercredi : une leçon.');

        $free = $this->enroll($user, $this->twoLessons(), CourseEnrollment::PACE_FREE);
        $this->assertNull($this->mailer()->sendFor($free, $this->day(0)), 'Rythme libre : aucun e-mail.');

        $pro = $this->enroll($user, $this->twoLessons(free: false), CourseEnrollment::PACE_DAILY);
        $this->assertNull($this->mailer()->sendFor($pro, $this->day(0)), 'Parcours PRO et membre gratuit : rien.');
    }

    public function testDailyRunSendsEachPacedEnrollmentOnce(): void
    {
        static::createClient();
        $enrollment = $this->enroll($this->createUser(), $this->twoLessons(), CourseEnrollment::PACE_DAILY);
        $day = new \DateTimeImmutable('2031-01-06');
        $this->assertGreaterThanOrEqual(1, $this->mailer()->sendAll($day)['lecons']);
        $this->mailer()->sendAll($day);
        $this->em()->clear();
        $saved = $this->em()->find(CourseEnrollment::class, $enrollment->getId());
        $this->assertSame('2031-01-06', $saved->getLastEmailSentOn()?->format('Y-m-d'));
        $this->assertSame(1, $saved->getEmailsWithoutProgress(), 'Relancer le même jour n\'envoie rien de plus.');

        // Branché dans la tâche quotidienne des rappels (app:reminders:send)
        $command = (new \ReflectionClass(\App\Notification\Command\SendRemindersCommand::class))->getConstructor()->getParameters();
        $this->assertContains(AcademyPaceMailer::class, array_map(fn ($p) => (string) $p->getType(), $command));
    }

    public function testMemberChoosesPausesFromTheEmailAndResumesInOneClick(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $course = $this->twoLessons();

        $crawler = $client->request('GET', '/academie/'.$course->getSlug());
        $this->assertSelectorTextContains('#rythme', 'Une leçon par jour');
        $client->submit($crawler->filter('#rythme form.academie-pace')->form(), ['rythme' => 'DAILY']);
        $this->assertResponseRedirects();
        $this->em()->clear();
        $enrollment = $this->em()->getRepository(CourseEnrollment::class)->findOneBy(['user' => $user->getId(), 'course' => $course->getId()]);
        $this->assertSame(CourseEnrollment::PACE_DAILY, $enrollment->getPaceMode(), 'Choisir un rythme inscrit au parcours.');

        // Lien « Mettre en pause » de l'e-mail : signé, sans connexion
        $url = static::getContainer()->get(UriSigner::class)->sign('http://localhost/notifications/desabonnement/'.$user->getId().'/ACADEMY_LESSON');
        $client->request('POST', $url);
        $this->assertResponseIsSuccessful();
        $this->em()->clear();
        $this->assertTrue($this->em()->find(CourseEnrollment::class, $enrollment->getId())->isPacePaused());

        // Reprise en un clic sur la page du parcours
        $client->loginUser($user);
        $crawler = $client->request('GET', '/academie/'.$course->getSlug());
        $this->assertSelectorTextContains('#rythme .ks-alert', 'Vos e-mails sont en pause');
        $client->submit($crawler->filter('#rythme .ks-alert form')->selectButton('Reprendre mon rythme')->form());
        $this->em()->clear();
        $resumed = $this->em()->find(CourseEnrollment::class, $enrollment->getId());
        $this->assertFalse($resumed->isPacePaused());
        $this->assertSame(CourseEnrollment::PACE_DAILY, $resumed->getPaceMode());
        $this->assertSame(0, $resumed->getEmailsWithoutProgress());

        // Rythme inconnu : refusé
        $client->request('GET', '/academie/'.$course->getSlug());
        $token = $client->getCrawler()->filter('#rythme input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/academie/'.$course->getSlug().'/rythme', ['_csrf_token' => $token, 'rythme' => 'HOURLY']);
        $this->assertResponseStatusCodeSame(403);
    }
}
