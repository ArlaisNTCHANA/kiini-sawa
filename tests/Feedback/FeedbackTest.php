<?php

namespace App\Tests\Feedback;

use App\Compte\Repository\UserRepository;
use App\Feedback\Entity\AppFeedback;
use App\Feedback\Entity\CommunityFeedback;
use App\Notification\Entity\Notification;
use App\Feedback\Service\FeedbackService;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Feedback nourricier (regard des proches, anonymat protégé) et « Donner mon avis » sur l'application.
 */
class FeedbackTest extends WebTestCase
{
    use AccountTestTrait;

    public function testFriendsAnswerAndSummaryAppearsAfterThreeAnswers(): void
    {
        $client = static::createClient();
        $owner = $this->ownerNamed('Abigaïl');
        $client->loginUser($owner);

        $client->request('GET', '/mes-tests/feedback');
        $client->submitForm('Créer mon lien');
        $request = $this->service()->activeRequest($owner);
        $this->assertNotNull($request);
        $this->assertEqualsWithDelta((new \DateTimeImmutable('+7 days'))->getTimestamp(), $request->getExpiresAt()->getTimestamp(), 120, 'lien valable une semaine');
        $client->followRedirect();
        $this->assertSelectorTextContains('main', 'Votre message, prêt à envoyer');
        $this->assertSelectorTextContains('main', 'Envoyer sur WhatsApp');
        $client->request('POST', '/logout', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE]);

        // Trois proches répondent (sessions différentes)
        foreach ([['Fiabilité', 'Patience'], ['Fiabilité', 'Sagesse'], ['Fiabilité', 'Courage']] as $i => $qualities) {
            $client->getCookieJar()->clear();
            $crawler = $client->request('GET', '/avis/'.$request->getToken());
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('h1', 'Abigaïl vous demande d\'être son témoin');
            $html = (string) $client->getResponse()->getContent();
            $this->assertStringNotContainsString('lui ou en elle', $html, 'tournures neutres : l\'application ne connaît pas le sexe');
            $this->assertStringNotContainsString('vu(e)', $html);
            $this->assertStringContainsString('og:image', $html, 'aperçu du lien dans WhatsApp');
            $this->assertStringContainsString('Abigaïl vous demande d\'être son témoin', $crawler->filter('meta[property="og:title"]')->attr('content'));
            $form = $crawler->selectButton('Envoyer mon regard')->form();
            foreach ($qualities as $q) {
                $form['qualities'][array_search($q, FeedbackService::QUALITIES, true)]->tick();
            }
            $client->submit($form, ['strengths' => 'Force numéro '.$i, 'improvements' => 2 === $i ? 'Oser dire non' : '']);
            // Redirection après l'envoi : sans elle, Turbo laissait le formulaire affiché (la personne croyait que rien n'était parti)
            $this->assertResponseRedirects('/avis/'.$request->getToken().'/merci');
            $client->followRedirect();
            $this->assertSelectorTextContains('main', 'Merci pour votre regard');
            $this->assertSelectorTextContains('main', 'Découvrir KIINI SAWA');
            if (1 === $i) {
                $this->assertFalse($this->service()->summary($request)['detail'], 'Pas de détail avant 3 réponses');
            }
        }

        $client->loginUser(static::getContainer()->get(UserRepository::class)->find($owner->getId()));
        $client->request('GET', '/mes-tests/feedback');
        $this->assertSelectorTextContains('main', '3 réponses');
        $this->assertSelectorTextContains('main', 'Fiabilité · 3');
        $this->assertSelectorTextContains('main', 'Oser dire non');
    }

    public function testValidationAndDuplicatesAndClosedLinks(): void
    {
        $client = static::createClient();
        $owner = $this->ownerNamed('Samuel');
        $service = $this->service();
        $request = $service->open($owner, false);

        $this->assertNotEmpty($service->submit($request, ['qualities' => [], 'strengths' => '']));
        $this->assertNotEmpty($service->submit($request, ['qualities' => \array_slice(FeedbackService::QUALITIES, 0, 6), 'strengths' => 'x']), 'Au plus 5 qualités');
        $this->assertNotEmpty($service->submit($request, ['qualities' => ['Généreux'], 'strengths' => 'x']), 'les anciennes qualités ne sont plus acceptées');

        $crawler = $client->request('GET', '/avis/'.$request->getToken());
        $form = $crawler->selectButton('Envoyer mon regard')->form();
        $form['qualities'][0]->tick();
        $client->submit($form, ['strengths' => 'Très à l\'écoute']);
        $client->request('GET', '/avis/'.$request->getToken());
        $this->assertResponseRedirects('/avis/'.$request->getToken().'/merci', null, 'une deuxième visite mène au remerciement');

        // Sans réponse depuis ce navigateur, la page de remerciement renvoie au formulaire
        $client->getCookieJar()->clear();
        $client->request('GET', '/avis/'.$request->getToken().'/merci');
        $this->assertResponseRedirects('/avis/'.$request->getToken());

        $fresh = $this->service();
        $fresh->close($fresh->findOpen($request->getToken()));
        $client->request('GET', '/avis/'.$request->getToken());
        $this->assertResponseStatusCodeSame(410);
        $this->assertSelectorTextContains('main', 'Samuel a clôturé cette demande');
        $this->assertSelectorTextContains('main', 'Découvrir KIINI SAWA');

        $client->request('GET', '/avis/'.str_repeat('0', 24));
        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextContains('main', 'n\'existe pas ou n\'est plus actif');
    }

    public function testExpiredLinkKeepsResultsAndCanBeExtendedOrRenewed(): void
    {
        $client = static::createClient();
        $owner = $this->ownerNamed('Esther');
        $service = $this->service();
        $request = $service->open($owner, false);
        $this->assertSame([], $service->submit($request, ['qualities' => ['Joie'], 'strengths' => 'Sa joie communicative']));
        $request->setExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->em()->flush();

        // La personne invitée voit une page claire, et ne peut plus répondre
        $client->request('GET', '/avis/'.$request->getToken());
        $this->assertResponseStatusCodeSame(410);
        $this->assertSelectorTextContains('main', 'Le délai pour répondre à Esther est passé');
        $this->assertNotEmpty($service->submit($request, ['qualities' => ['Joie'], 'strengths' => 'Trop tard']));
        $this->assertNull($service->activeRequest($owner));

        // Le membre garde ses résultats et peut rouvrir le lien une semaine
        $client->loginUser($owner);
        $client->request('GET', '/mes-tests/feedback');
        $this->assertSelectorTextContains('main', 'n\'accepte plus de réponses');
        $this->assertSelectorTextContains('main', '1 réponse');
        $client->submitForm('Rouvrir ce lien une semaine');
        $this->em()->clear();
        $reopened = $this->service()->findOpen($request->getToken());
        $this->assertNotNull($reopened, 'le même lien fonctionne de nouveau');
        $this->assertGreaterThan(new \DateTimeImmutable('+6 days'), $reopened->getExpiresAt());

        // Prolongation plafonnée : jamais plus de 3 semaines devant soi
        $this->assertTrue($this->service()->extend($reopened));
        $this->assertTrue($this->service()->extend($reopened));
        $this->assertFalse($this->service()->extend($reopened));
        $this->assertLessThanOrEqual((new \DateTimeImmutable('+21 days'))->getTimestamp(), $reopened->getExpiresAt()->getTimestamp());

        // Une demande fermée ne se prolonge pas ; un nouveau lien la remplace, l'ancienne reste consultable
        $this->service()->close($reopened);
        $this->assertFalse($this->service()->extend($reopened));
        $client->request('GET', '/mes-tests/feedback');
        $this->assertSelectorTextContains('main', 'Vous avez fermé cette demande');
        $client->submitForm('Créer un nouveau lien');
        $client->followRedirect();
        $new = $this->service()->activeRequest($owner);
        $this->assertNotSame($request->getToken(), $new->getToken());
        $this->assertSelectorTextContains('main', 'Mes demandes précédentes');
        $client->request('GET', '/mes-tests/feedback/demande/'.$request->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', '1 réponse');

        // Les demandes d'un autre membre restent privées
        $client->loginUser($this->ownerNamed('Autre'));
        $client->request('GET', '/mes-tests/feedback/demande/'.$request->getId());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testTheFirstNameIsAskedBeforeCreatingTheLink(): void
    {
        $client = static::createClient();
        $owner = $this->createUser();
        $owner->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($owner);

        $client->request('GET', '/mes-tests/feedback');
        $this->assertSelectorExists('input[name="firstName"]');
        $client->submitForm('Créer mon lien', ['firstName' => '']);
        $this->assertNull($this->service()->activeRequest($owner), 'sans prénom, les proches verraient le début de l\'adresse e-mail');
        $client->request('GET', '/mes-tests/feedback');
        $client->submitForm('Créer mon lien', ['firstName' => 'Noémie']);
        $this->assertNotNull($this->service()->activeRequest($owner));
        $this->em()->clear();
        $this->assertSame('Noémie', static::getContainer()->get(UserRepository::class)->find($owner->getId())->getUserProfile()->getFirstName());
    }

    public function testShareMessagesAreReadyToPaste(): void
    {
        static::createClient();
        $service = $this->service();
        $request = $service->open($this->ownerNamed('Paul'), false);
        $url = 'https://kiinisawa.com/avis/'.$request->getToken();

        $tu = $service->shareMessage($request, $url, 'tu');
        $this->assertStringContainsString($url, $tu);
        $this->assertStringContainsString('ton regard', $tu);
        $this->assertStringContainsString('tu peux rester anonyme', $tu);
        $this->assertStringContainsString(FeedbackService::frenchDate($request->getExpiresAt()), $tu);
        $vous = $service->shareMessage($request, $url, 'vous');
        $this->assertStringContainsString('votre regard', $vous);

        $request->setForceRealName(true);
        $this->assertStringNotContainsString('anonyme', $service->shareMessage($request, $url, 'tu'), 'pas de promesse d\'anonymat si le prénom est demandé');

        $this->assertSame('mardi 1er décembre', FeedbackService::frenchDate(new \DateTimeImmutable('2026-12-01')));
        $this->assertSame('jeudi 3 décembre', FeedbackService::frenchDate(new \DateTimeImmutable('2026-12-03')));
    }

    public function testOldAnswersWithAdjectivesAreShownAsNouns(): void
    {
        static::createClient();
        $service = $this->service();
        $request = $service->open($this->ownerNamed('Ruth'), true);
        $old = (new CommunityFeedback())->setUser($request->getUser())->setFeedbackRequest($request)->setRelationship('ami')
            ->setQualities(json_encode(['Généreux', 'Drôle'], \JSON_UNESCAPED_UNICODE))->setStrengths('Toujours là')->setIsAnonymous(true);
        $this->em()->persist($old);
        $this->em()->flush();

        $summary = $service->summary($request);
        $this->assertSame(['Générosité' => 1, 'Humour' => 1], $summary['qualities']);
    }

    public function testDailyChecksTellTheMemberWhenTheLinkEndsOrNeedsAReminder(): void
    {
        static::createClient();
        $service = $this->service();
        $ended = $service->open($this->ownerNamed('Marthe'), false);
        $ended->setExpiresAt(new \DateTimeImmutable('-2 hours'));
        $quiet = $service->open($this->ownerNamed('Jonas'), false);
        $this->em()->getConnection()->executeStatement('UPDATE feedback_request SET created_at = :d WHERE id = :id', ['d' => (new \DateTime('-4 days'))->format('Y-m-d H:i:s'), 'id' => $quiet->getId()]);
        $this->em()->flush();

        $first = $service->runDailyChecks(new \DateTimeImmutable('today'));
        $this->assertGreaterThanOrEqual(1, $first['fin']);
        $this->assertGreaterThanOrEqual(1, $first['relance']);
        $notifications = $this->em()->getRepository(Notification::class);
        $this->assertNotNull($notifications->findOneBy(['user' => $ended->getUser(), 'metadata' => 'feedback-fin-'.$ended->getId().'-'.$ended->getExpiresAt()->format('Ymd')]));
        $this->assertNotNull($notifications->findOneBy(['user' => $quiet->getUser(), 'metadata' => 'feedback-relance-'.$quiet->getId()]));

        $again = $service->runDailyChecks(new \DateTimeImmutable('today'));
        $this->assertNull($notifications->findOneBy(['user' => $ended->getUser(), 'metadata' => 'feedback-fin-'.$ended->getId().'-x']));
        $this->assertSame(1, $notifications->count(['user' => $quiet->getUser(), 'metadata' => 'feedback-relance-'.$quiet->getId()]), 'pas de doublon');
    }

    /** La page du membre explique les trois témoins du livre (schéma interactif, texte du livre) et le mode d'emploi, à la largeur de la page. */
    public function testMemberPageExplainsTheThreeWitnesses(): void
    {
        $client = static::createClient();
        $client->loginUser($this->ownerNamed('Esther'));
        $crawler = $client->request('GET', '/mes-tests/feedback');
        $this->assertResponseIsSuccessful();

        $this->assertCount(3, $crawler->filter('[data-temoins] [role="tab"][data-temoin]'), 'un onglet par témoin, utilisable au clavier');
        $this->assertCount(3, $crawler->filter('[data-temoins] [role="tabpanel"]'));
        $this->assertCount(3, $crawler->filter('[data-temoin-cercle]'), 'les trois cercles du schéma');
        $this->assertSame('true', $crawler->filter('[data-temoin="TEMOIGNAGE"]')->attr('aria-selected'), 'le témoignage (le feedback nourricier) est montré d\'abord');
        $text = $crawler->filter('main')->text();
        // Définitions au « vous » (le membre ne doit pas croire qu'une phrase au « tu » lui parle d'un acte précis) ; exemple du livre mot pour mot, présenté comme tel
        foreach (['Un acte concret que vous avez posé', 'Une donnée issue d\'un test ou d\'une mesure sérieuse', 'Ce que les autres ont vu et confirmé',
            'Exemple du livre KIINI', 'Deux collègues m\'ont dit qu\'ils se sentaient écoutés en réunion', 'Pourquoi demander le regard des autres', 'Bien choisir les personnes',
            'Ce que vos proches verront', 'Ce que vous recevrez', 'Relire avec bienveillance', 'Comment ça marche'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertCount(1, $crawler->filter('img[src*="fig_temoins"]'), 'le schéma du livre reste consultable');
        $this->assertCount(1, $crawler->filter('a[href="/methode/confirmations"]'));
        $this->assertCount(1, $crawler->filter('main .ks-btn--primary'), 'une seule action principale');
        $this->assertCount(0, $crawler->filter('main [style*="max-width: 680px"]'), 'plus de cadre plus étroit que le reste de la page');
    }

    /** Les proches peuvent ajouter leurs propres qualités et répondre « en un mot » ; la synthèse regroupe les mêmes mots. */
    public function testOwnQualitiesAndOneWordAnswers(): void
    {
        $client = static::createClient();
        $owner = $this->ownerNamed('Ruth');
        $service = $this->service();
        $request = $service->open($owner, false);

        $crawler = $client->request('GET', '/avis/'.$request->getToken());
        $this->assertCount(1, $crawler->filter('[data-temoins-public] [data-temoins]'), 'le schéma des trois témoins, côté proches');
        $this->assertSelectorTextContains('main', 'Si vous deviez décrire Ruth en un mot');
        $this->assertSelectorTextContains('main', 'ce que Ruth n\'est pas');
        $this->assertSelectorTextContains('main', 'KIINI SAWA, qu\'est-ce que c\'est');
        $form = $crawler->selectButton('Envoyer mon regard')->form();
        $form['otherQualities'][0]->setValue('  sens de l\'accueil ');
        $client->submit($form, ['strengths' => 'Accueillir', 'word' => 'lumineuse', 'notWord' => 'rancunière']);
        $this->assertResponseRedirects('/avis/'.$request->getToken().'/merci', null, 'une qualité ajoutée suffit');
        $service = $this->service(); // nouveau noyau après la requête : on relit la demande
        $request = $service->findByToken($request->getToken());

        $this->assertSame([], $service->submit($request, ['qualities' => ['Patience'], 'otherQualities' => ['patience', 'SENS DE L\'ACCUEIL', 'Sens de l\'accueil'], 'strengths' => 'x', 'word' => 'Lumineuse.', 'notWord' => '']));
        $this->assertSame([], $service->submit($request, ['qualities' => [], 'otherQualities' => ['écoute'], 'strengths' => 'x', 'word' => '<b>LUMINEUSE</b>']));
        $this->assertNotEmpty($service->submit($request, ['qualities' => [], 'otherQualities' => ['Un', 'Deux', 'Trois', 'Quatre'], 'strengths' => 'x']), 'au plus 3 qualités ajoutées');

        $summary = $service->summary($request);
        $this->assertSame(1, $summary['qualities']['Patience'], 'une qualité de la liste écrite autrement n\'est pas comptée deux fois');
        $this->assertSame(1, $summary['qualities']['Écoute'], 'reprend le nom de la liste');
        $this->assertSame(2, $summary['qualities']['Sens de l\'accueil']);
        $this->assertSame(['Lumineuse' => 3], $summary['words'], 'même mot, majuscules et ponctuation différentes : regroupé');
        $this->assertSame(['Rancunière' => 1], $summary['notWords']);

        $client->loginUser(static::getContainer()->get(UserRepository::class)->find($owner->getId()));
        $client->request('GET', '/mes-tests/feedback');
        $this->assertSelectorTextContains('main', 'En un mot, vous êtes…');
        $this->assertSelectorTextContains('main', 'Lumineuse · 3');
        $this->assertSelectorTextContains('main', 'Rancunière');
    }

    /**
     * Page de la personne invitée (souvent son premier contact avec KIINI SAWA) : son rôle de témoin, KIINI SAWA en une
     * phrase, pourquoi son regard compte (citations du livre mot pour mot), les trois témoins, le déroulé, le questionnaire
     * en quatre étapes ; puis remerciement et invitation à la Boussole 360 ; lien terminé = même porte d'entrée.
     */
    public function testGuestPageWelcomesExplainsAndInvites(): void
    {
        $client = static::createClient();
        $owner = $this->ownerNamed('Noémie');
        $request = $this->service()->open($owner, false);

        $crawler = $client->request('GET', '/avis/'.$request->getToken());
        $this->assertResponseIsSuccessful();
        $text = $crawler->filter('main')->text();
        foreach (['Noémie vous demande d\'être son témoin', 'KIINI SAWA est une application chrétienne', 'Pourquoi votre regard compte',
            'Votre regard est le troisième témoin', 'Comment ça se passe', 'Environ 3 minutes', 'Confidentiel', 'Boussole 360, gratuite et sans compte',
            'Vous et Noémie', 'Ses qualités', 'En quelques mots', 'Pour finir'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertCount(1, $crawler->filter('.fb-hero a.ks-btn--primary[href="#questionnaire"]'), 'une seule action dans l\'accueil : commencer');
        $this->assertCount(4, $crawler->filter('#questionnaire fieldset[data-step]'), 'questionnaire en quatre étapes');
        $this->assertCount(1, $crawler->filter('#questionnaire [role="progressbar"]'), 'barre de progression');
        $this->assertCount(7, $crawler->filter('#questionnaire input[type="radio"][name="relationship"]'));
        $this->assertCount(1, $crawler->filter('[data-temoins-public] [data-temoins] [role="tab"][data-temoin="TEMOIGNAGE"][aria-selected="true"]'), 'le schéma s\'ouvre sur « votre regard »');
        $this->assertSame(0, $crawler->filter('main details[data-temoins-public]')->count(), 'les trois témoins ne sont plus repliés');
        // Le proche lit pour la première fois : un exemple suivi avec le prénom, rien au « tu » qui semblerait lui parler
        $temoins = $crawler->filter('[data-temoins-public]')->text();
        $this->assertStringContainsString('imaginons que Noémie se dise', $temoins);
        $this->assertStringContainsString('Noémie se souvient d\'avoir pris le temps d\'écouter', $temoins);
        $this->assertStringContainsString('C\'est votre part', $temoins);
        foreach (['que tu as posé', 'm\'ont dit', 'vous avez découvert'] as $confusing) {
            $this->assertStringNotContainsString($confusing, $temoins);
        }
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Anonyme si vous le souhaitez', $html);
        $this->assertStringContainsString('Noémie vous demande d\'être son témoin', $crawler->filter('meta[property="og:title"]')->attr('content'));
        $this->assertStringContainsString('confidentielles', $crawler->filter('meta[property="og:description"]')->attr('content'));
        $this->assertStringNotContainsString('choisi(e)', $html);

        // Envoi (sans JavaScript : toutes les étapes sont dans le formulaire), puis remerciement et invitation
        $form = $crawler->selectButton('Envoyer mon regard')->form();
        $form['relationship']->select('eglise');
        $form['qualities'][0]->tick();
        $client->submit($form, ['strengths' => 'Sa fidélité']);
        $this->assertResponseRedirects('/avis/'.$request->getToken().'/merci');
        $crawler = $client->followRedirect();
        $this->assertSelectorTextContains('main', 'Merci pour votre regard');
        $boussole = $crawler->filter('main a.ks-btn--primary');
        $this->assertCount(1, $boussole, 'une seule action principale');
        $this->assertStringStartsWith('/boussole?', (string) $boussole->attr('href'), 'la Boussole 360, sans compte');
        $this->assertStringContainsString('utm_source=feedback', (string) $boussole->attr('href'));
        $this->assertCount(1, $crawler->filter('main a[href^="/inscription?"]'));

        // Lien inconnu : même porte d'entrée
        $client->getCookieJar()->clear();
        $crawler = $client->request('GET', '/avis/'.str_repeat('1', 24));
        $this->assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('utm_content=lien-termine', (string) $crawler->filter('main a.ks-btn--primary')->attr('href'));
    }

    /** Les phrases du livre citées sur les pages des proches existent mot pour mot dans le livre. */
    public function testGuestPagesQuoteTheBookWordForWord(): void
    {
        $dir = \dirname(__DIR__, 2);
        $book = file_get_contents($dir.'/content/livre/CHAP_12.md').file_get_contents($dir.'/content/livre/CHAP_23.md');
        $book = str_replace('’', '\'', $book);
        foreach (['templates/feedback/form.html.twig', 'templates/feedback/thanks.html.twig'] as $file) {
            preg_match_all('/<blockquote class="fb-quote">« (.+?) »</u', (string) file_get_contents($dir.'/'.$file), $m);
            foreach ($m[1] as $quote) {
                $this->assertStringContainsString($quote, $book, 'citation fidèle au livre : '.$quote);
            }
        }
        $form = (string) file_get_contents($dir.'/templates/feedback/form.html.twig');
        $this->assertStringContainsString('Toute affaire se réglera sur la déclaration de deux ou de trois témoins » (2 Corinthiens 13:1)', $form);
        $this->assertStringContainsString('Toute affaire se réglera sur la déclaration de deux ou de trois témoins » (2 Corinthiens 13:1)', $book);
        // Le proche découvre souvent KIINI SAWA : le livre est présenté par son titre, jamais « la méthode » ni une citation au « tu » qui semblerait lui parler
        foreach (['form.html.twig', 'thanks.html.twig', '_temoins.html.twig'] as $file) {
            $content = (string) file_get_contents($dir.'/templates/feedback/'.$file);
            $this->assertStringNotContainsString('La méthode appelle', $content, $file);
            $this->assertStringNotContainsString('Le livre de la méthode', $content, $file);
            $this->assertStringNotContainsString('t\\\'aiment', $content, $file);
        }
        $this->assertStringContainsString('KIINI : Bâtis la vie pour laquelle tu as été créé', $form);
    }

    private function service(): FeedbackService
    {
        return static::getContainer()->get(FeedbackService::class);
    }

    private function ownerNamed(string $firstName): \App\Compte\Entity\User
    {
        $owner = $this->createUser();
        $profile = $owner->getUserProfile() ?? (new \App\Compte\Entity\UserProfile())->setUser($owner);
        $profile->setFirstName($firstName);
        $owner->setUserProfile($profile);
        $this->em()->persist($profile);
        $owner->setIsOnboardingCompleted(true);
        $this->em()->flush();

        return $owner;
    }

    public function testAppFeedbackReachesTheAdmin(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);
        $client->request('GET', '/donner-mon-avis?page=/journal');
        $message = 'Le bouton du journal pourrait être plus grand '.uniqid();
        $client->submitForm('Envoyer', ['category' => 'IDEE', 'content' => $message]);
        $this->assertResponseRedirects('/donner-mon-avis');
        $item = $this->em()->getRepository(AppFeedback::class)->findOneBy(['user' => $user]);
        $this->assertStringContainsString($message, $item->getContent());
        $this->assertStringContainsString('/journal', $item->getContent());

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/avis');
        $this->assertSelectorTextContains('body', $message);
    }
}
