<?php

namespace App\Tests\Notification;

use App\Abonnement\Service\Entitlements;
use App\Bilan\Entity\WeeklyReview;
use App\Compte\Entity\User;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Notification\Service\TipCatalog;
use App\Notification\Service\TipSender;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * « Saviez-vous que… » : après l'accueil, une fonction jamais utilisée, au plus toutes les deux semaines, jamais deux fois,
 * pas à côté d'un autre e-mail important, désactivable, et au plus un message PRO sur trois pour un membre gratuit.
 */
class TipSenderTest extends WebTestCase
{
    use AccountTestTrait;

    private function tips(): TipSender
    {
        return static::getContainer()->get(TipSender::class);
    }

    /** Premier jour (au moins $min jours après aujourd'hui) qui n'est pas du 1er au 3 du mois, ni 14 jours plus tard. */
    private function base(int $min = TipSender::START_DAY): \DateTimeImmutable
    {
        $d = (new \DateTimeImmutable('today'))->modify('+'.$min.' days');
        while ((int) $d->format('j') <= 3 || (int) $d->modify('+'.TipSender::INTERVAL_DAYS.' days')->format('j') <= 3) {
            $d = $d->modify('+1 day');
        }

        return $d;
    }

    /** Les notifications sont datées de l'instant réel : on les recale sur la date simulée. */
    private function dateNotifications(User $user, NotificationType $type, \DateTimeImmutable $when): void
    {
        $this->em()->createQuery('UPDATE '.Notification::class.' n SET n.createdAt = :d WHERE n.user = :u AND n.type = :t')
            ->setParameters(['d' => \DateTime::createFromImmutable($when->setTime(7, 0)), 'u' => $user, 't' => $type->value])->execute();
    }

    /** Ne garder que certains messages actifs (rend le test indépendant des parcours et défis présents dans la base). */
    private function onlyTips(array $codes): array
    {
        $config = static::getContainer()->get(Entitlements::class)->config();
        $before = $config->getDisabledTips();
        $config->setEnabledTips($codes);
        $this->em()->flush();

        return $before;
    }

    private function restoreTips(array $disabled): void
    {
        static::getContainer()->get(Entitlements::class)->config()->setDisabledTips($disabled);
        $this->em()->flush();
    }

    public function testOneTipAtATimeAfterTheWelcomeWeeksAndNeverTwice(): void
    {
        static::createClient();
        $before = $this->onlyTips(['revue', 'test', 'boussole', 'feedback']);
        try {
            $user = $this->createUser();
            $base = $this->base();

            $this->assertNull($this->tips()->nextTip($user, (new \DateTimeImmutable('today'))->modify('+10 days')), 'Rien pendant les deux semaines d\'accueil');
            $this->assertSame('revue', $this->tips()->nextTip($user, $base));
            $this->assertTrue($this->tips()->sendFor($user, $base));
            $email = $this->lastEmail();
            $this->assertSame(TipCatalog::defaults()['astuce.revue.titre'], $email->getSubject());
            $html = (string) $email->getHtmlBody();
            $this->assertStringContainsString('Faire ma revue de la semaine', $html);
            $this->assertStringContainsString('Ne plus recevoir ces messages', $html);
            $this->assertStringNotContainsString('formule PRO', $html);
            $this->assertTrue($email->getHeaders()->has('List-Unsubscribe'));

            $this->dateNotifications($user, NotificationType::TIP, $base);
            $this->assertFalse($this->tips()->sendFor($user, $base), 'Jamais deux fois le même jour');
            $this->assertNull($this->tips()->nextTip($user, $base->modify('+7 days')), 'Au plus un message toutes les deux semaines');
            $this->assertSame('test', $this->tips()->nextTip($user, $base->modify('+'.TipSender::INTERVAL_DAYS.' days')), 'Le suivant, jamais le même');
        } finally {
            $this->restoreTips($before);
        }
    }

    public function testFeatureAlreadyUsedIsSkipped(): void
    {
        static::createClient();
        $before = $this->onlyTips(['revue', 'test']);
        try {
            $user = $this->createUser();
            $this->em()->persist(new WeeklyReview($user, new \DateTimeImmutable('monday this week')));
            $this->em()->flush();

            $this->assertTrue($this->tips()->hasUsed($user, 'revue'));
            $this->assertSame('test', $this->tips()->nextTip($user, $this->base()));
        } finally {
            $this->restoreTips($before);
        }
    }

    public function testNothingNextToOtherImportantEmailsOrForAbsentMembers(): void
    {
        static::createClient();
        $before = $this->onlyTips(['revue']);
        try {
            $user = $this->createUser();
            $base = $this->base();

            $firstOfMonth = $base->modify('first day of next month')->modify('+1 day');
            $this->assertNull($this->tips()->nextTip($user, $firstOfMonth), 'Pas du 1er au 3 du mois (revue mensuelle)');

            $birthday = (new Notification())->setUser($user)->setType(NotificationType::BIRTHDAY)->setTitle('Joyeux anniversaire')->setMessage('…');
            $this->em()->persist($birthday);
            $this->em()->flush();
            $this->dateNotifications($user, NotificationType::BIRTHDAY, $base->modify('-3 days'));
            $this->assertNull($this->tips()->nextTip($user, $base), 'Pas dans la semaine d\'un anniversaire');
            $this->dateNotifications($user, NotificationType::BIRTHDAY, $base->modify('-8 days'));
            $this->assertSame('revue', $this->tips()->nextTip($user, $base));

            $user->setLastActiveAt($base->modify('-'.(TipSender::INACTIVE_DAYS + 1).' days'));
            $this->em()->flush();
            $this->assertNull($this->tips()->nextTip($user, $base), 'Plus rien après deux mois sans connexion');
        } finally {
            $this->restoreTips($before);
        }
    }

    public function testMembersCanTurnTipsOff(): void
    {
        $client = static::createClient();
        $before = $this->onlyTips(['revue']);
        try {
            $user = $this->createUser();
            $this->assertTrue($this->tips()->sendFor($user, $this->base()));
            preg_match('/href="([^"]*desabonnement[^"]*|[^"]*unsubscribe[^"]*)"/', (string) $this->lastEmail()->getHtmlBody(), $m);
            $this->assertNotEmpty($m, 'Lien de désabonnement dans l\'e-mail');
            $url = html_entity_decode($m[1]);
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful();
            $client->request('POST', $url);

            $user = $this->findUser($user->getEmail());
            $this->assertFalse(static::getContainer()->get(Notifier::class)->preferences($user)->isNotifyTips());
            $this->assertNull($this->tips()->nextTip($user, $this->base(40)));
        } finally {
            $this->restoreTips($before);
        }
    }

    public function testFreeMembersGetAtMostOneProTipInARow(): void
    {
        static::createClient();
        $config = static::getContainer()->get(Entitlements::class)->config();
        $social = $config->isEnableSocialFree();
        $journal = $config->isEnableJournalFree();
        $before = $this->onlyTips(['partenaire', 'journal']);
        $config->setEnableSocialFree(false)->setEnableJournalFree(false);
        $this->em()->flush();
        try {
            $user = $this->createUser();
            $base = $this->base();
            $this->assertTrue($this->tips()->sendFor($user, $base), 'Plus rien de gratuit à présenter : un message PRO reste possible');
            $html = (string) $this->lastEmail()->getHtmlBody();
            $this->assertStringContainsString('Partenaire de Redevabilité', $html);
            $this->assertStringContainsString('formule PRO', $html);

            $this->dateNotifications($user, NotificationType::TIP, $base);
            $this->assertNull($this->tips()->nextTip($user, $base->modify('+'.TipSender::INTERVAL_DAYS.' days')), 'Jamais deux messages PRO de suite');

            $pro = $this->createUser(pro: true);
            $this->assertTrue($this->tips()->sendFor($pro, $base));
            $this->assertStringNotContainsString('formule PRO', (string) $this->lastEmail()->getHtmlBody(), 'Membre PRO : pas de mention de l\'offre');
        } finally {
            $config = static::getContainer()->get(Entitlements::class)->config();
            $config->setEnableSocialFree($social)->setEnableJournalFree($journal);
            $this->restoreTips($before);
        }
    }

    public function testAdminChoosesWhichTipsAreSent(): void
    {
        static::createClient();
        $config = static::getContainer()->get(Entitlements::class)->config();
        $before = $config->getDisabledTips();
        try {
            $config->setEnabledTips(['revue', 'feedback']);
            $this->assertSame(['revue', 'feedback'], $config->getEnabledTips());
            $this->assertNotContains('revue', $config->getDisabledTips());
            $this->assertContains('journal', $config->getDisabledTips());
            $config->setEnabledTips(array_keys(TipCatalog::TIPS));
            $this->assertSame([], $config->getDisabledTips());
        } finally {
            $config->setDisabledTips($before);
            $this->em()->flush();
        }
    }
}
