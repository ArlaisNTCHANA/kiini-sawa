<?php

namespace App\Tests\Couple;

use App\Couple\Entity\CoupleChallenge;
use App\Couple\Entity\CoupleItem;
use App\Couple\Entity\CoupleMeditation;
use App\Couple\Service\CoupleSanctuaryCatalog;
use App\Couple\Service\CoupleService;
use App\Notification\Entity\Notification;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le Sanctuaire du Couple sur le web : L'Autel, Le Jardin des défis, signaux (même service que l'application mobile).
 */
class CoupleSanctuaryWebTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function activeCouple(bool $pro = true): array
    {
        $couples = static::getContainer()->get(CoupleService::class);
        $a = $this->createUser(pro: $pro);
        $b = $this->createUser(pro: $pro);
        $couple = $couples->create($a, null);
        $couples->join($b, $couple, true);

        return [$couple, $a, $b];
    }

    /** Envoie un formulaire du Sanctuaire avec un vrai jeton CSRF (lu sur la page). */
    private function post(string $page, string $action, array $fields = []): void
    {
        $crawler = $this->client->request('GET', $page);
        $token = $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
        $this->client->request('POST', $action, $fields + ['_csrf_token' => $token]);
    }

    public function testDashboardShowsTheSanctuaryWithTheFourRooms(): void
    {
        [, $a] = $this->activeCouple();
        $this->client->loginUser($a);
        $this->client->request('GET', '/couple');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#sanctuaire', 'Notre Sanctuaire');
        foreach (['L\'Autel', 'Le Jardin des défis', 'L\'Atelier', 'Le Salon'] as $room) {
            $this->assertSelectorTextContains('#sanctuaire', $room);
        }
        $this->assertSelectorTextContains('#sanctuaire', CoupleSanctuaryCatalog::dailyVerse()['reference']);
    }

    public function testPagesStayClosedWithoutAUsableCouple(): void
    {
        [, $a] = $this->activeCouple(pro: false);
        $this->client->loginUser($a);
        $this->client->request('GET', '/couple/autel');
        $this->assertResponseRedirects('/couple');
        $this->client->request('GET', '/couple/defis');
        $this->assertResponseRedirects('/couple');

        $alone = $this->createUser();
        $this->client->loginUser($alone);
        $this->client->request('POST', '/couple/signal', ['preset' => 'thinking', '_csrf_token' => 'faux']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testMeditationJourneyOnTheAltar(): void
    {
        [$couple, $a, $b] = $this->activeCouple();
        $this->client->loginUser($a);
        $code = array_key_first(CoupleSanctuaryCatalog::MEDITATIONS);
        $steps = \count(CoupleSanctuaryCatalog::MEDITATIONS[$code]['steps']);

        $this->post('/couple/autel', '/couple/autel/commencer', ['code' => $code]);
        $this->assertResponseRedirects('/couple/autel#meditation');
        $this->client->request('GET', '/couple/autel');
        $this->assertSelectorTextContains('#meditation', 'étape 1 sur '.$steps);
        $this->assertSelectorTextContains('#meditation', CoupleSanctuaryCatalog::MEDITATIONS[$code]['steps'][1]['question']);

        // Un seul conjoint ne fait pas avancer l'étape
        $this->post('/couple/autel', '/couple/autel/etape');
        $this->client->request('GET', '/couple/autel');
        $this->assertSelectorTextContains('#meditation', 'étape 1 sur '.$steps);
        $this->assertSelectorTextContains('#meditation', 'avancera quand '.$b->getDisplayName());

        // Les deux : l'étape avance, et la suivante s'ouvre demain
        $this->client->loginUser($b);
        $this->post('/couple/autel', '/couple/autel/etape');
        $this->client->request('GET', '/couple/autel');
        $this->assertSelectorTextContains('#meditation', 'régularité');
        $this->post('/couple/autel', '/couple/autel/etape');
        $this->em()->clear();
        $this->assertSame(2, $this->em()->getRepository(CoupleMeditation::class)->findOneBy(['couple' => $couple->getId()])->getCurrentStep());

        // Temps de retraite proposé par B, confirmé par A : les étapes suivantes peuvent être vécues le même jour
        $this->post('/couple/autel', '/couple/retraite/proposer', ['startsOn' => date('Y-m-d'), 'endsOn' => date('Y-m-d', strtotime('+1 day')), 'retour' => 'autel']);
        $this->client->loginUser($a);
        $this->client->request('GET', '/couple/autel');
        $this->assertSelectorTextContains('#retraite', 'Je confirme la retraite');
        $this->post('/couple/autel', '/couple/retraite/confirmer', ['retour' => 'autel']);
        $this->assertResponseRedirects('/couple/autel#retraite');
        for ($i = 2; $i <= $steps; ++$i) {
            $this->client->loginUser($a);
            $this->post('/couple/autel', '/couple/autel/etape');
            $this->client->loginUser($b);
            $this->post('/couple/autel', '/couple/autel/etape');
        }
        $this->em()->clear();
        $meditation = $this->em()->getRepository(CoupleMeditation::class)->findOneBy(['couple' => $couple->getId()]);
        $this->assertSame(CoupleMeditation::STATUS_FINISHED, $meditation->getStatus());

        $notified = $this->em()->getRepository(Notification::class)->findBy(['user' => $b->getId()]);
        $this->assertNotEmpty($notified, 'Le conjoint est prévenu');
    }

    public function testIntentionsAreSharedAndOnlyOwnCoupleCanRemoveThem(): void
    {
        [$couple, $a, $b] = $this->activeCouple();
        $this->client->loginUser($a);
        $this->post('/couple/autel', '/couple/autel/intentions', ['title' => 'La santé de maman', 'details' => 'Opération jeudi']);
        $this->assertResponseRedirects('/couple/autel#intentions');

        $this->client->loginUser($b);
        $this->client->request('GET', '/couple/autel');
        $this->assertSelectorTextContains('#intentions', 'La santé de maman');

        $item = $this->em()->getRepository(CoupleItem::class)->findOneBy(['couple' => $couple->getId(), 'kind' => 'INTENTION']);
        [, $other] = $this->activeCouple();
        $this->client->loginUser($other);
        $this->post('/couple/autel', '/couple/autel/intentions/'.$item->getId().'/retirer');
        $this->assertResponseStatusCodeSame(404);

        $this->client->loginUser($b);
        $this->post('/couple/autel', '/couple/autel/intentions/'.$item->getId().'/retirer');
        $this->em()->clear();
        $this->assertNull($this->em()->find(CoupleItem::class, $item->getId()));
    }

    public function testChallengeDayOpensWhenBothSpousesValidated(): void
    {
        [$couple, $a, $b] = $this->activeCouple();
        $code = array_key_first(CoupleSanctuaryCatalog::CHALLENGES);

        $this->client->loginUser($a);
        $this->post('/couple/defis', '/couple/defis/commencer', ['code' => $code]);
        $this->post('/couple/defis', '/couple/defis/valider', ['note' => 'Très beau moment']);
        $this->client->request('GET', '/couple/defis');
        $this->assertSelectorTextContains('main', 'jour 1 sur');
        $this->assertSelectorTextContains('main', 'Il reste à');

        $this->client->loginUser($b);
        $this->client->request('GET', '/couple/defis');
        $this->assertSelectorTextContains('main', 'Très beau moment');
        $this->post('/couple/defis', '/couple/defis/valider');

        $this->em()->clear();
        $challenge = $this->em()->getRepository(CoupleChallenge::class)->findOneBy(['couple' => $couple->getId()]);
        $this->assertSame(2, $challenge->getCurrentDay());

        // Le jour 2 s'ouvre demain : message discret sur la régularité, pas de formulaire
        $this->client->request('GET', '/couple/defis');
        $this->assertSelectorTextContains('main', 'la régularité qui fait grandir, pas la vitesse');
        $this->assertSelectorNotExists('form[action="/couple/defis/valider"]');
    }

    public function testRetreatNeedsAnEndDateAfterTheStartAndStaysPendingUntilConfirmed(): void
    {
        [$couple, $a] = $this->activeCouple();
        $this->client->loginUser($a);

        $this->post('/couple/defis', '/couple/retraite/proposer', ['startsOn' => date('Y-m-d', strtotime('+3 days')), 'endsOn' => date('Y-m-d', strtotime('+1 day'))]);
        $this->post('/couple/defis', '/couple/retraite/proposer', ['startsOn' => date('Y-m-d', strtotime('-1 day')), 'endsOn' => date('Y-m-d')]);
        $this->post('/couple/defis', '/couple/retraite/proposer', ['startsOn' => date('Y-m-d'), 'endsOn' => '']);
        $this->assertCount(0, $this->em()->getRepository(\App\Couple\Entity\CoupleRetreat::class)->findBy(['couple' => $couple->getId()]));

        $this->post('/couple/defis', '/couple/retraite/proposer', ['startsOn' => date('Y-m-d', strtotime('+7 days')), 'endsOn' => date('Y-m-d', strtotime('+9 days')), 'note' => 'Retraite de Pâques']);
        $this->client->request('GET', '/couple/defis');
        $this->assertSelectorTextContains('#retraite', 'Retraite de Pâques');
        $this->assertSelectorTextContains('#retraite', 'Elle comptera dès que');
        $this->assertSelectorNotExists('#retraite form[action="/couple/retraite/confirmer"]');
    }

    public function testSignalReachesThePartner(): void
    {
        [, $a, $b] = $this->activeCouple();
        $this->client->loginUser($a);
        $preset = array_key_first(CoupleSanctuaryCatalog::PING_PRESETS);
        $this->post('/couple', '/couple/signal', ['preset' => $preset, 'message' => 'Je pense à toi']);
        $this->assertResponseRedirects('/couple#sanctuaire');

        $notes = $this->em()->getRepository(Notification::class)->findBy(['user' => $b->getId()]);
        $this->assertNotEmpty($notes);
        $this->assertStringContainsString('Je pense à toi', end($notes)->getMessage());
    }
}
