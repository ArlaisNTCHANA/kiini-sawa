<?php

namespace App\Tests\Mobile;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Mobile\Entity\MobileToken;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests complets de l'API mobile (/api/mobile/v1/*) :
 * jetons Bearer, rotation, réutilisation, limitation de débit, mode strict CSRF,
 * rituels, journal, profil et suppression de compte.
 */
class MobileApiTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        $_SERVER['APP_SAME_ORIGIN_STRICT'] = $_ENV['APP_SAME_ORIGIN_STRICT'] = '0';
        putenv('APP_SAME_ORIGIN_STRICT=0');
        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{status: int, json: array<string, mixed>, headers: \Symfony\Component\HttpFoundation\ResponseHeaderBag}
     */
    private function api(string $method, string $path, ?array $payload = null, ?string $bearer = null, array $server = []): array
    {
        $headers = $server + ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $payload) {
            $headers['CONTENT_TYPE'] = 'application/json';
        }
        if (null !== $bearer) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
        }

        $this->client->request(
            $method,
            $path,
            server: $headers,
            content: null !== $payload ? json_encode($payload, \JSON_THROW_ON_ERROR) : null
        );

        $response = $this->client->getResponse();
        $decoded = json_decode((string) $response->getContent(), true);

        return [
            'status' => $response->getStatusCode(),
            'json' => \is_array($decoded) ? $decoded : [],
            'headers' => $response->headers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loginMobile(string $email, string $password = 'mot-de-passe-solide', string $device = 'Appareil de test'): array
    {
        $res = $this->api('POST', '/api/mobile/v1/connexion', [
            'email' => $email,
            'password' => $password,
            'appareil' => $device,
        ]);
        $this->assertSame(200, $res['status'], 'Connexion mobile attendue à 200');

        return $res['json'];
    }

    public function testLoginReturnsTokensEntitlementsAndPlansCatalog(): void
    {
        $user = $this->createUser('mot-de-passe-solide', verified: true, pro: false);
        $res = $this->api('POST', '/api/mobile/v1/connexion', [
            'email' => '  '.strtoupper((string) $user->getEmail()).'  ',
            'password' => 'mot-de-passe-solide',
            'appareil' => 'iPhone de Luc',
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('no-store', (string) $res['headers']->get('Cache-Control'));

        $json = $res['json'];
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $json['accessToken']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $json['refreshToken']);
        $this->assertSame(1200, $json['expiresIn']);
        $this->assertSame($user->getEmail(), $json['user']['email']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $json['user']['account']);
        $this->assertFalse($json['user']['entitlements']['isPro']);
        $this->assertArrayHasKey('habits', $json['user']['entitlements']['limits']);
        $this->assertCount(6, $json['user']['plansCatalog']);
        $this->assertSame(
            ['VIE', 'CROISSANCE', 'DEVELOPPEMENT', 'HYGIENE', 'INTENDANCE', 'TRANSMISSION'],
            array_column($json['user']['plansCatalog'], 'key')
        );

        // Seules les empreintes SHA-256 doivent être stockées en base
        $stored = $this->em()->getRepository(MobileToken::class)->findOneBy(['user' => $user]);
        $this->assertNotNull($stored);
        $this->assertNotSame($json['accessToken'], $stored->getAccessHash());
        $this->assertSame(hash('sha256', $json['accessToken']), $stored->getAccessHash());
        $this->assertSame(hash('sha256', $json['refreshToken']), $stored->getRefreshHash());
    }

    public function testLoginFailsWithSameGenericMessageForUnknownOrBadPassword(): void
    {
        $user = $this->createUser('mot-de-passe-solide');

        $unknown = $this->api('POST', '/api/mobile/v1/connexion', [
            'email' => $this->uniqueEmail('inconnu'),
            'password' => 'mot-de-passe-solide',
        ]);
        $wrongPass = $this->api('POST', '/api/mobile/v1/connexion', [
            'email' => $user->getEmail(),
            'password' => 'mauvais-mot-de-passe',
        ]);

        $this->assertSame(401, $unknown['status']);
        $this->assertSame(401, $wrongPass['status']);
        $this->assertSame($unknown['json']['error'], $wrongPass['json']['error']);
    }

    public function testLoginRejectsUnverifiedAndAdminOnlyAfterPasswordCheck(): void
    {
        $unverified = $this->createUser('secret-unverified', verified: false);
        // Mauvais mot de passe sur compte non vérifié : ne doit pas révéler l'état du compte
        $bad = $this->api('POST', '/api/mobile/v1/connexion', ['email' => $unverified->getEmail(), 'password' => 'faux']);
        $this->assertSame(401, $bad['status']);

        $okUnverified = $this->api('POST', '/api/mobile/v1/connexion', ['email' => $unverified->getEmail(), 'password' => 'secret-unverified']);
        $this->assertSame(403, $okUnverified['status']);
        $this->assertSame('email_unverified', $okUnverified['json']['code']);

        $admin = $this->createUser('secret-admin', verified: true);
        $admin->setRoles(['ROLE_ADMIN']);
        $this->em()->flush();

        $okAdmin = $this->api('POST', '/api/mobile/v1/connexion', ['email' => $admin->getEmail(), 'password' => 'secret-admin']);
        $this->assertSame(403, $okAdmin['status']);
        $this->assertSame('admin_forbidden', $okAdmin['json']['code']);
    }

    public function testLoginIsRateLimitedPerAccount(): void
    {
        $user = $this->createUser('mot-de-passe-solide');

        for ($i = 0; $i < 5; ++$i) {
            $res = $this->api('POST', '/api/mobile/v1/connexion', [
                'email' => $user->getEmail(),
                'password' => 'mauvais-'.$i,
            ]);
            $this->assertSame(401, $res['status']);
        }

        $blocked = $this->api('POST', '/api/mobile/v1/connexion', [
            'email' => $user->getEmail(),
            'password' => 'mot-de-passe-solide',
        ]);
        $this->assertSame(429, $blocked['status']);
        $this->assertSame('rate_limited', $blocked['json']['code']);
    }

    public function testBearerAuthenticationAndExpiredTokenRejection(): void
    {
        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/moi')['status']);
        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/moi', bearer: 'jeton-invalide-trop-court')['status']);

        $user = $this->createUser('mot-de-passe-solide');
        $rawExpired = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable();
        $expiredToken = new MobileToken(
            user: $user,
            deviceName: 'Ancien appareil',
            accessHash: hash('sha256', $rawExpired),
            refreshHash: hash('sha256', bin2hex(random_bytes(32))),
            accessExpiresAt: $now->modify('-1 minute'),
            refreshExpiresAt: $now->modify('+30 days'),
            now: $now->modify('-1 hour'),
        );
        $this->em()->persist($expiredToken);
        $this->em()->flush();

        $expiredRes = $this->api('GET', '/api/mobile/v1/moi', bearer: $rawExpired);
        $this->assertSame(401, $expiredRes['status']);

        $login = $this->loginMobile((string) $user->getEmail());
        $me = $this->api('GET', '/api/mobile/v1/moi', bearer: $login['accessToken']);
        $this->assertSame(200, $me['status']);
        $this->assertSame($user->getEmail(), $me['json']['user']['email']);
    }

    public function testRefreshRotationAndReuseDetectionRevokesAllSessions(): void
    {
        $user = $this->createUser('mot-de-passe-solide');
        $first = $this->loginMobile((string) $user->getEmail(), 'mot-de-passe-solide', 'Appareil 1');
        $second = $this->loginMobile((string) $user->getEmail(), 'mot-de-passe-solide', 'Appareil 2');

        $rotated = $this->api('POST', '/api/mobile/v1/jeton', ['refreshToken' => $first['refreshToken']]);
        $this->assertSame(200, $rotated['status']);
        $this->assertNotSame($first['accessToken'], $rotated['json']['accessToken']);
        $this->assertNotSame($first['refreshToken'], $rotated['json']['refreshToken']);

        // L'ancien jeton d'accès ne marche plus, le nouveau fonctionne
        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/moi', bearer: $first['accessToken'])['status']);
        $this->assertSame(200, $this->api('GET', '/api/mobile/v1/moi', bearer: $rotated['json']['accessToken'])['status']);

        // Réutilisation malveillante de l'ancien refreshToken : doit révoquer TOUTES les sessions du compte
        $reuse = $this->api('POST', '/api/mobile/v1/jeton', ['refreshToken' => $first['refreshToken']]);
        $this->assertSame(401, $reuse['status']);

        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/moi', bearer: $rotated['json']['accessToken'])['status']);
        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/moi', bearer: $second['accessToken'])['status']);
        $this->assertSame(401, $this->api('POST', '/api/mobile/v1/jeton', ['refreshToken' => $second['refreshToken']])['status']);
    }

    public function testLogoutRevokesCurrentSessionAndSupportsRevokingAll(): void
    {
        $user = $this->createUser('mot-de-passe-solide');
        $s1 = $this->loginMobile((string) $user->getEmail(), 'mot-de-passe-solide', 'Tel 1');
        $s2 = $this->loginMobile((string) $user->getEmail(), 'mot-de-passe-solide', 'Tel 2');

        $logout1 = $this->api('POST', '/api/mobile/v1/deconnexion', ['refreshToken' => $s1['refreshToken']], bearer: $s1['accessToken']);
        $this->assertSame(200, $logout1['status']);
        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/moi', bearer: $s1['accessToken'])['status']);
        $this->assertSame(401, $this->api('POST', '/api/mobile/v1/jeton', ['refreshToken' => $s1['refreshToken']])['status']);
        $this->assertSame(200, $this->api('GET', '/api/mobile/v1/moi', bearer: $s2['accessToken'])['status']);

        $logoutAll = $this->api('POST', '/api/mobile/v1/deconnexion', ['tous' => true], bearer: $s2['accessToken']);
        $this->assertSame(200, $logoutAll['status']);
        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/moi', bearer: $s2['accessToken'])['status']);
    }

    public function testStrictSameOriginAllowsCookielessBearerAndBlocksCrossSite(): void
    {
        static::ensureKernelShutdown();
        $_SERVER['APP_SAME_ORIGIN_STRICT'] = $_ENV['APP_SAME_ORIGIN_STRICT'] = '1';
        putenv('APP_SAME_ORIGIN_STRICT=1');
        $this->client = static::createClient();

        $user = $this->createUser('mot-de-passe-solide');
        $login = $this->loginMobile((string) $user->getEmail());

        // Redémarrer le navigateur simulé évite qu'AbstractBrowser n'injecte automatiquement HTTP_REFERER depuis l'historique
        $this->client->restart();
        // Sans cookie ni Origin : sans Bearer => 401 ; avec Bearer => 200
        $this->assertSame(401, $this->api('POST', '/api/mobile/v1/sync', ['ops' => []])['status']);
        $this->client->restart();
        $ok = $this->api('POST', '/api/mobile/v1/sync', ['account' => $login['user']['account'], 'ops' => []], bearer: $login['accessToken']);
        $this->assertSame(200, $ok['status']);

        // En-tête Origin cross-site : refusé même avec Bearer valide
        $cross = $this->api('POST', '/api/mobile/v1/sync', ['ops' => []], bearer: $login['accessToken'], server: [
            'HTTP_ORIGIN' => 'https://site-malveillant.example',
        ]);
        $this->assertSame(403, $cross['status']);

        // Cookie de session présent sans Origin : toujours refusé (fail-closed)
        $this->client->restart();
        $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('REMEMBERME', 'abc', null, '/', 'localhost'));
        $cookieWithoutOrigin = $this->api('POST', '/api/mobile/v1/sync', ['ops' => []], bearer: $login['accessToken']);
        $this->assertSame(403, $cookieWithoutOrigin['status']);
    }

    public function testQuickDayTodaySyncAndAccountConflict(): void
    {
        $user = $this->createUser('mot-de-passe-solide', pro: true);
        $habit = (new Habit())->setUser($user)->setTitle('Méditation matinale')->setFrequency(HabitFrequency::DAILY);
        $this->em()->persist($habit);
        $this->em()->flush();
        $habitId = $habit->getId();

        $other = $this->createUser('mot-de-passe-solide');
        $loginA = $this->loginMobile((string) $user->getEmail());
        $loginB = $this->loginMobile((string) $other->getEmail());

        $today = $this->api('GET', '/api/mobile/v1/aujourdhui', bearer: $loginA['accessToken']);
        $this->assertSame(200, $today['status']);
        $this->assertSame('Méditation matinale', $today['json']['habits'][0]['title']);

        $uid1 = 'mob-op-0001-habit';
        $uid2 = 'mob-op-0002-review';
        $uid3 = 'mob-op-0003-note';
        $day = date('Y-m-d');
        $at = date(\DATE_ATOM);

        $sync = $this->api('POST', '/api/mobile/v1/sync', [
            'account' => $loginA['user']['account'],
            'ops' => [
                ['uid' => $uid1, 'type' => 'habit.log', 'date' => $day, 'at' => $at, 'habit' => $habitId, 'status' => true],
                ['uid' => $uid2, 'type' => 'review.save', 'date' => $day, 'at' => $at, 'intention' => 'Paix', 'kept' => true, 'energy' => 4, 'mood' => 5, 'stress' => 2, 'gratitude' => 'Merci Seigneur'],
                ['uid' => $uid3, 'type' => 'journal.add', 'date' => $day, 'at' => $at, 'content' => 'Note hors ligne', 'entryType' => 'INSIGHT'],
            ],
        ], bearer: $loginA['accessToken']);

        $this->assertSame(200, $sync['status']);
        $this->assertSame([$uid1 => 'ok', $uid2 => 'ok', $uid3 => 'ok'], $sync['json']['results']);
        $this->assertTrue($sync['json']['snapshot']['habits'][0]['done']);
        $this->assertSame('Paix', $sync['json']['snapshot']['review']['intention']);
        $this->assertSame('Note hors ligne', $sync['json']['snapshot']['journal'][0]['text']);

        // Envoi en double idempotent
        $dup = $this->api('POST', '/api/mobile/v1/sync', [
            'account' => $loginA['user']['account'],
            'ops' => [
                ['uid' => $uid1, 'type' => 'habit.log', 'date' => $day, 'at' => $at, 'habit' => $habitId, 'status' => true],
            ],
        ], bearer: $loginA['accessToken']);
        $this->assertSame(200, $dup['status']);
        $this->assertSame([$uid1 => 'deja'], $dup['json']['results']);

        // Cloisonnement de compte : compte A envoyé avec jeton de compte B => 409
        $conflict = $this->api('POST', '/api/mobile/v1/sync', [
            'account' => $loginA['user']['account'],
            'ops' => [],
        ], bearer: $loginB['accessToken']);
        $this->assertSame(409, $conflict['status']);
        $this->assertSame('account', $conflict['json']['code']);
    }

    public function testHabitsCrudIsolationAndFreeTierLimitWithoutUpgradeUrl(): void
    {
        $freeUser = $this->createUser('mot-de-passe-solide', pro: false);
        $entitlements = static::getContainer()->get(Entitlements::class);
        $limit = $entitlements->limit($freeUser, 'habits');
        $loginFree = $this->loginMobile((string) $freeUser->getEmail());

        if (null !== $limit) {
            for ($i = 1; $i <= $limit; ++$i) {
                $ok = $this->api('POST', '/api/mobile/v1/rituels', [
                    'title' => 'Rituel '.$i,
                    'frequency' => 'DAILY',
                ], bearer: $loginFree['accessToken']);
                $this->assertSame(201, $ok['status']);
            }
            $blocked = $this->api('POST', '/api/mobile/v1/rituels', [
                'title' => 'Rituel en trop',
                'frequency' => 'DAILY',
            ], bearer: $loginFree['accessToken']);
            $this->assertSame(403, $blocked['status']);
            $this->assertSame('pro_required', $blocked['json']['code']);
            $this->assertArrayNotHasKey('upgradeUrl', $blocked['json'], 'Aucun lien de paiement dans l\'API mobile');
            $this->assertStringNotContainsString('€', (string) $blocked['json']['error']);
            $this->assertStringNotContainsString('FCFA', (string) $blocked['json']['error']);
        }

        $proUser = $this->createUser('mot-de-passe-solide', pro: true);
        $otherUser = $this->createUser('mot-de-passe-solide', pro: true);
        $loginPro = $this->loginMobile((string) $proUser->getEmail());
        $loginOther = $this->loginMobile((string) $otherUser->getEmail());

        $created = $this->api('POST', '/api/mobile/v1/rituels', [
            'title' => 'Lecture biblique',
            'frequency' => 'DAILY',
            'rootingAnchor' => 'Après le café du matin',
        ], bearer: $loginPro['accessToken']);
        $this->assertSame(201, $created['status']);
        $habitId = $created['json']['habit']['id'];

        $list = $this->api('GET', '/api/mobile/v1/rituels', bearer: $loginPro['accessToken']);
        $this->assertSame(200, $list['status']);
        $this->assertSame(1, $list['json']['count']);
        $this->assertSame('Après le café du matin', $list['json']['habits'][0]['rootingAnchor']);

        // Isolement entre utilisateurs
        $this->assertSame(404, $this->api('PATCH', '/api/mobile/v1/rituels/'.$habitId, ['title' => 'Piraté'], bearer: $loginOther['accessToken'])['status']);
        $this->assertSame(404, $this->api('DELETE', '/api/mobile/v1/rituels/'.$habitId, bearer: $loginOther['accessToken'])['status']);

        $updated = $this->api('PATCH', '/api/mobile/v1/rituels/'.$habitId, [
            'title' => 'Lecture et prière',
            'frequency' => 'WEEKLY',
        ], bearer: $loginPro['accessToken']);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('Lecture et prière', $updated['json']['habit']['title']);
        $this->assertSame('WEEKLY', $updated['json']['habit']['frequency']);

        $deleted = $this->api('DELETE', '/api/mobile/v1/rituels/'.$habitId, bearer: $loginPro['accessToken']);
        $this->assertSame(200, $deleted['status']);
    }

    public function testJournalPaginationFilteringCarnetsAndIsolation(): void
    {
        $user = $this->createUser('mot-de-passe-solide', pro: true);
        $other = $this->createUser('mot-de-passe-solide', pro: true);
        $login = $this->loginMobile((string) $user->getEmail());
        $loginOther = $this->loginMobile((string) $other->getEmail());

        $carnetRes = $this->api('POST', '/api/mobile/v1/journal/carnets', [
            'name' => 'Prières',
            'color' => '#2E6B53',
        ], bearer: $login['accessToken']);
        $this->assertSame(201, $carnetRes['status']);
        $carnetId = $carnetRes['json']['carnet']['id'];

        $n1 = $this->api('POST', '/api/mobile/v1/journal', [
            'content' => 'Première note dans carnet',
            'type' => 'INSIGHT',
            'carnetId' => $carnetId,
        ], bearer: $login['accessToken']);
        $this->assertSame(201, $n1['status']);
        $noteId = $n1['json']['entry']['id'];

        $this->api('POST', '/api/mobile/v1/journal', [
            'content' => 'Deuxième note libre',
            'type' => 'IDEA',
        ], bearer: $login['accessToken']);

        // Pagination & filtres
        $page1 = $this->api('GET', '/api/mobile/v1/journal?limit=1&offset=0', bearer: $login['accessToken']);
        $this->assertSame(200, $page1['status']);
        $this->assertCount(1, $page1['json']['entries']);
        $this->assertSame(2, $page1['json']['totalCount']);
        $this->assertTrue($page1['json']['hasMore']);

        $filteredCarnet = $this->api('GET', '/api/mobile/v1/journal?carnetId='.$carnetId, bearer: $login['accessToken']);
        $this->assertSame(1, $filteredCarnet['json']['totalCount']);
        $this->assertSame('Première note dans carnet', $filteredCarnet['json']['entries'][0]['content']);

        $filteredType = $this->api('GET', '/api/mobile/v1/journal?type=IDEA', bearer: $login['accessToken']);
        $this->assertSame(1, $filteredType['json']['totalCount']);

        // Isolement entre comptes
        $this->assertSame(404, $this->api('DELETE', '/api/mobile/v1/journal/'.$noteId, bearer: $loginOther['accessToken'])['status']);
        $this->assertSame(404, $this->api('DELETE', '/api/mobile/v1/journal/carnets/'.$carnetId, bearer: $loginOther['accessToken'])['status']);

        // Supprimer le carnet conserve la note sans carnet
        $this->assertSame(200, $this->api('DELETE', '/api/mobile/v1/journal/carnets/'.$carnetId, bearer: $login['accessToken'])['status']);
        $afterCarnetDelete = $this->api('GET', '/api/mobile/v1/journal', bearer: $login['accessToken']);
        $this->assertSame(2, $afterCarnetDelete['json']['totalCount']);

        $this->assertSame(200, $this->api('DELETE', '/api/mobile/v1/journal/'.$noteId, bearer: $login['accessToken'])['status']);
    }

    public function testProfileUpdateAndAccountDeletionCascadesTokens(): void
    {
        $user = $this->createUser('mot-de-passe-solide');
        $userId = $user->getId();
        $login = $this->loginMobile((string) $user->getEmail());

        $updated = $this->api('PATCH', '/api/mobile/v1/moi', [
            'firstName' => 'Esther',
            'city' => 'Abidjan',
            'profession' => '',
        ], bearer: $login['accessToken']);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('Esther', $updated['json']['user']['profile']['firstName']);
        $this->assertSame('Abidjan', $updated['json']['user']['profile']['city']);
        $this->assertNull($updated['json']['user']['profile']['profession']);

        // Mauvaise confirmation ou mauvais mot de passe
        $badConfirm = $this->api('DELETE', '/api/mobile/v1/compte', [
            'confirmation' => 'OUI',
            'currentPassword' => 'mot-de-passe-solide',
        ], bearer: $login['accessToken']);
        $this->assertSame(400, $badConfirm['status']);

        $badPassword = $this->api('DELETE', '/api/mobile/v1/compte', [
            'confirmation' => 'SUPPRIMER',
            'currentPassword' => 'faux',
        ], bearer: $login['accessToken']);
        $this->assertSame(400, $badPassword['status']);

        // Suppression effective
        $deleted = $this->api('DELETE', '/api/mobile/v1/compte', [
            'confirmation' => 'supprimer',
            'currentPassword' => 'mot-de-passe-solide',
        ], bearer: $login['accessToken']);
        $this->assertSame(200, $deleted['status']);
        $this->assertTrue($deleted['json']['ok']);
        $this->assertSame('Votre compte KIINI SAWA a été supprimé', $this->lastEmail()->getSubject());

        $this->assertNull($this->findUser((string) $user->getEmail()));
        $remainingTokens = (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM mobile_token WHERE user_id = ?', [$userId]);
        $this->assertSame(0, $remainingTokens);
        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/moi', bearer: $login['accessToken'])['status']);
    }
}
