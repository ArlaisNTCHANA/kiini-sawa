<?php

namespace App\Tests\Compte;

use App\Compte\Entity\User;
use App\Compte\Entity\UserProfile;
use App\Compte\Service\BirthDate;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\BirthdayGreeter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Date de naissance : demandée à l'inscription (âge minimum des CGU, accord des parents entre 15 et 17 ans), proposée une
 * seule fois sur l'accueil aux comptes existants, modifiable dans « Mon profil » et sur mobile, message d'anniversaire
 * sans doublon et désactivable, date jamais montrée à quelqu'un d'autre.
 */
final class BirthDateTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function yearsAgo(int $years, string $shift = '+0 day'): string
    {
        return (new \DateTimeImmutable('today'))->modify('-'.$years.' years')->modify($shift)->format('Y-m-d');
    }

    private function register(string $email, string $birth, bool $parentalConsent = false): void
    {
        $form = $this->client->request('GET', '/inscription')->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $values = ['firstName' => 'Noé', 'email' => $email, 'password' => 'une phrase assez longue', 'dateOfBirth' => $birth];
        if ($parentalConsent) {
            // la case n'existe dans le formulaire qu'après un premier envoi : on envoie directement les champs
            $this->client->request('POST', '/inscription', $values + [
                'acceptTerms' => '1', 'parentalConsent' => '1',
                '_csrf_token' => $form['_csrf_token']->getValue(),
            ]);

            return;
        }
        $this->client->submit($form, $values);
    }

    private function member(?string $birth = null, string $firstName = 'Anne'): User
    {
        $user = $this->createUser();
        $profile = (new UserProfile())->setFirstName($firstName);
        if ($birth) {
            $profile->setDateOfBirth(new \DateTime($birth));
        }
        $user->setUserProfile($profile)->setIsOnboardingCompleted(true);
        $this->em()->persist($profile);
        $this->em()->flush();

        return $user;
    }

    public function testRules(): void
    {
        $today = new \DateTimeImmutable('2026-11-24');
        $this->assertSame('2001-03-09', BirthDate::parse('09/03/2001')?->format('Y-m-d'));
        $this->assertSame('2001-03-09', BirthDate::parse('2001-03-09')?->format('Y-m-d'));
        $this->assertNull(BirthDate::parse('31/02/2001'), 'Date qui n\'existe pas');
        $this->assertNull(BirthDate::parse('bonjour'));

        $this->assertSame(BirthDate::MISSING, BirthDate::checkInput('', $today));
        $this->assertSame(BirthDate::INVALID, BirthDate::checkInput('2030-01-01', $today), 'Date dans le futur');
        $this->assertSame(BirthDate::INVALID, BirthDate::checkInput('1890-01-01', $today), 'Plus de 120 ans');
        $this->assertSame(BirthDate::TOO_YOUNG, BirthDate::checkInput('2011-11-25', $today), '15 ans demain : encore trop tôt');
        $this->assertNull(BirthDate::checkInput('2011-11-24', $today), '15 ans aujourd\'hui');
        $this->assertTrue(BirthDate::needsParentalConsent(new \DateTimeImmutable('2009-01-01'), $today));
        $this->assertFalse(BirthDate::needsParentalConsent(new \DateTimeImmutable('2008-11-24'), $today), '18 ans aujourd\'hui');

        $this->assertTrue(BirthDate::isBirthday(new \DateTimeImmutable('1990-11-24'), $today));
        $this->assertFalse(BirthDate::isBirthday(new \DateTimeImmutable('1990-11-23'), $today));
        $this->assertTrue(BirthDate::isBirthday(new \DateTimeImmutable('2000-02-29'), new \DateTimeImmutable('2027-02-28')), '29 février fêté le 28 les années non bissextiles');
        $this->assertFalse(BirthDate::isBirthday(new \DateTimeImmutable('2000-02-29'), new \DateTimeImmutable('2028-02-28')), 'Année bissextile : le 29');
        $this->assertTrue(BirthDate::isBirthday(new \DateTimeImmutable('2000-02-29'), new \DateTimeImmutable('2028-02-29')));
    }

    public function testRegistrationAsksForTheDateAndBlocksUnderFifteen(): void
    {
        $crawler = $this->client->request('GET', '/inscription');
        $this->assertSelectorExists('input[type=date][name=dateOfBirth]');
        $this->assertStringContainsString('Pour vérifier l\'âge requis.', $crawler->filter('#dateOfBirthHint')->text());
        $this->assertSelectorNotExists('input[name=gender]', 'Pas de question sur le sexe');

        // Sans date : refus
        $email = $this->uniqueEmail('naissance');
        $this->register($email, '');
        $this->assertResponseStatusCodeSame(422);
        $this->assertNull($this->findUser($email));

        // Moins de 15 ans : refus avec un message doux, aucun compte créé
        $this->register($email, $this->yearsAgo(15, '+1 day'));
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.form-error', 'réservé aux personnes de 15 ans et plus');
        $this->assertNull($this->findUser($email));
    }

    public function testBetweenFifteenAndSeventeenParentsMustAgree(): void
    {
        $email = $this->uniqueEmail('naissance-ado');
        $birth = $this->yearsAgo(16);
        $this->register($email, $birth);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('input[name=parentalConsent]', 'La case apparaît après le premier envoi');
        $this->assertSelectorTextContains('.form-error', 'vos parents sont d\'accord');
        $this->assertNull($this->findUser($email));

        $this->register($email, $birth, parentalConsent: true);
        $this->assertResponseRedirects('/verification-email');
        $this->assertSame($birth, $this->findUser($email)?->getUserProfile()?->getDateOfBirth()?->format('Y-m-d'));
    }

    public function testAdultRegistrationStoresTheDate(): void
    {
        $email = $this->uniqueEmail('naissance-adulte');
        $this->register($email, '1990-05-12');
        $this->assertResponseRedirects('/verification-email');
        $profile = $this->findUser($email)->getUserProfile();
        $this->assertSame('1990-05-12', $profile->getDateOfBirth()->format('Y-m-d'));
        $this->assertFalse($profile->shouldAskBirthDate(), 'Rien à redemander sur l\'accueil');
    }

    public function testExistingMembersAreAskedOnceOnTheHomePage(): void
    {
        $user = $this->member();
        $this->client->loginUser($user);
        $this->client->request('GET', '/dashboard');
        $this->assertSelectorExists('[data-birth-date-prompt]');

        // « Plus tard » : l'invitation ne revient plus
        $this->client->submit($this->client->getCrawler()->filter('#birthDateLater')->form());
        $this->assertResponseRedirects('/dashboard');
        $this->client->followRedirect();
        $this->assertSelectorNotExists('[data-birth-date-prompt]');
        $this->assertNotNull($this->findUser($user->getEmail())->getUserProfile()->getBirthDatePromptDismissedAt());

        // Un autre membre l'enregistre depuis l'accueil : invitation terminée
        $other = $this->member();
        $this->client->loginUser($other);
        $crawler = $this->client->request('GET', '/dashboard');
        $this->client->submit($crawler->filter('[data-birth-date-prompt] form')->first()->form(), ['dateOfBirth' => '1985-07-01']);
        $this->assertResponseRedirects('/dashboard');
        $this->client->followRedirect();
        $this->assertSelectorNotExists('[data-birth-date-prompt]');
        $this->assertSame('1985-07-01', $this->findUser($other->getEmail())->getUserProfile()->getDateOfBirth()->format('Y-m-d'));
    }

    public function testProfileCanChangeOrEraseTheDate(): void
    {
        $user = $this->member('1980-01-01');
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/profil');
        $this->assertSame('1980-01-01', $crawler->filter('#dateOfBirth')->attr('value'));

        $this->client->submit($crawler->filter('#date-de-naissance')->form(), ['dateOfBirth' => '1981-02-03']);
        $this->assertResponseRedirects();
        $this->assertSame('1981-02-03', $this->findUser($user->getEmail())->getUserProfile()->getDateOfBirth()->format('Y-m-d'));

        // Trop jeune ou impossible : refusé, la date d'avant reste
        $crawler = $this->client->request('GET', '/profil');
        $this->client->submit($crawler->filter('#date-de-naissance')->form(), ['dateOfBirth' => $this->yearsAgo(10)]);
        $this->assertSame('1981-02-03', $this->findUser($user->getEmail())->getUserProfile()->getDateOfBirth()->format('Y-m-d'));

        // Vide : effacée
        $crawler = $this->client->request('GET', '/profil');
        $this->client->submit($crawler->filter('#date-de-naissance')->form(), ['dateOfBirth' => '']);
        $this->assertNull($this->findUser($user->getEmail())->getUserProfile()->getDateOfBirth());
    }

    public function testBirthdayMessageOncePerYearAndCanBeSwitchedOff(): void
    {
        $today = new \DateTimeImmutable('today');
        $user = $this->member($today->modify('-30 years')->format('Y-m-d'), 'Joël');
        $notToday = $this->member($today->modify('-30 years -1 day')->format('Y-m-d'));
        $greeter = static::getContainer()->get(BirthdayGreeter::class);

        $this->assertGreaterThanOrEqual(1, $greeter->sendAll($today));
        $this->assertSame(0, $greeter->sendAll($today), 'Relancée le même jour : aucun doublon');

        $notes = $this->em()->getRepository(Notification::class)->findBy(['user' => $this->findUser($user->getEmail()), 'type' => NotificationType::BIRTHDAY]);
        $this->assertCount(1, $notes);
        $this->assertSame('Joyeux anniversaire, Joël !', $notes[0]->getTitle());
        $this->assertStringContainsString('Une nouvelle année de vie commence', $notes[0]->getMessage());
        $this->assertSame('/plan-de-vie', $notes[0]->getActionUrl());
        $this->assertStringNotContainsString((string) ($today->format('Y') - 30), $notes[0]->getMessage(), 'Ni la date ni l\'âge dans le message');
        $this->assertCount(0, $this->em()->getRepository(Notification::class)->findBy(['user' => $this->findUser($notToday->getEmail()), 'type' => NotificationType::BIRTHDAY]));

        // Un e-mail est parti, avec le bouton vers le Plan de Vie
        $mail = array_values(array_filter($this->getMailerMessages(), fn ($m) => \in_array($user->getEmail(), array_map(fn ($a) => $a->getAddress(), $m->getTo()), true)));
        $this->assertCount(1, $mail);
        $this->assertStringContainsString('Relire mon Plan de Vie', $mail[0]->getHtmlBody());

        // Interrupteur coupé : plus rien l'année suivante
        $other = $this->member($today->modify('-40 years')->format('Y-m-d'));
        $prefs = static::getContainer()->get(\App\Notification\Service\Notifier::class)->preferences($other);
        $prefs->setNotifyBirthday(false);
        $this->em()->flush();
        $this->assertFalse($greeter->greet($this->findUser($other->getEmail()), $today));
    }

    public function testBirthdaySwitchInProfile(): void
    {
        $user = $this->member();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/profil');
        $this->assertSelectorExists('input[name=notifyBirthday][checked]');
        $form = $crawler->filter('#notifications form')->last()->form();
        $form['notifyBirthday']->untick();
        $this->client->submit($form);
        $this->assertFalse($this->findUser($user->getEmail())->getNotificationPreference()->isNotifyBirthday());
    }

    /**
     * La date reste privée : seuls le profil de la personne, l'inscription, l'API mobile « moi » et l'envoi du message
     * d'anniversaire y touchent. Toute nouvelle lecture ailleurs (couple, organisation, partenaires…) fait échouer ce test.
     */
    public function testTheDateIsReadOnlyWhereItBelongs(): void
    {
        $allowed = [
            'src/Compte/Entity/UserProfile.php',
            'src/Compte/Controller/RegistrationController.php',
            'src/Compte/Controller/ProfileController.php',
            'src/Mobile/Service/MobileUserSerializer.php',
            'src/Mobile/Controller/MobileAccountController.php',
            'src/Notification/Service/BirthdayGreeter.php',
            'templates/compte/profile.html.twig',
            'templates/compte/register.html.twig',
            'templates/dashboard/index.html.twig', // invitation de l'accueil : champ vide, envoyé par la personne elle-même
        ];
        $dir = \dirname(__DIR__, 2);
        $found = [];
        foreach (['src', 'templates'] as $root) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir.'/'.$root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (preg_match('/dateOfBirth|DateOfBirth|date_of_birth/', (string) file_get_contents($file->getPathname()))) {
                    $found[] = substr($file->getPathname(), \strlen($dir) + 1);
                }
            }
        }
        $this->assertSame([], array_values(array_diff($found, $allowed)), 'La date de naissance est lue hors des endroits prévus');
    }
}
