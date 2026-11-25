<?php

namespace App\Tests\Mobile;

use App\Academie\Entity\CourseEnrollment;
use App\PlanDeVie\Entity\Value;
use App\Tests\Academie\AcademyFixturesTrait;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * API mobile de l'Académie (Lot 4) : mêmes règles que le site (catalogue, accès PRO, pont vers les plans sans doublon,
 * notes dans le Journal, rythme), clés en français, no-store, aucun prix.
 */
final class MobileAcademyTest extends WebTestCase
{
    use AccountTestTrait;
    use AcademyFixturesTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** @return array{status: int, json: array<string, mixed>, raw: string, cache: ?string} */
    private function api(string $method, string $path, ?array $payload = null, ?string $bearer = null): array
    {
        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $payload) {
            $headers['CONTENT_TYPE'] = 'application/json';
        }
        if (null !== $bearer) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
        }
        $this->client->request($method, $path, server: $headers, content: null !== $payload ? json_encode($payload, JSON_THROW_ON_ERROR) : null);
        $response = $this->client->getResponse();
        $raw = (string) $response->getContent();

        return ['status' => $response->getStatusCode(), 'json' => json_decode($raw, true) ?? [], 'raw' => $raw, 'cache' => $response->headers->get('Cache-Control')];
    }

    private function login(bool $pro = false): array
    {
        $user = $this->createUser(pro: $pro);
        $res = $this->api('POST', '/api/mobile/v1/connexion', ['email' => $user->getEmail(), 'password' => 'mot-de-passe-solide', 'appareil' => 'Test Phone']);
        $this->assertSame(200, $res['status']);

        return [$user, $res['json']['accessToken']];
    }

    public function testCatalogCourseAndLessonAreInFrenchWithoutPrices(): void
    {
        [, $token] = $this->login();
        $course = $this->makeCourse('VIE', lessons: [['Ce qui compte', 'VIE', 'add_value'], ['Suite', null, null]]);
        $pro = $this->makeCourse('INTENDANCE', free: false);

        $this->assertSame(401, $this->api('GET', '/api/mobile/v1/academie')['status']);

        $res = $this->api('GET', '/api/mobile/v1/academie', bearer: $token);
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('no-store', (string) $res['cache']);
        $bySlug = array_column($res['json']['parcours'], null, 'adresse');
        $this->assertTrue($bySlug[$course->getSlug()]['gratuit']);
        $this->assertTrue($bySlug[$pro->getSlug()]['reservePro']);
        $this->assertCount(6, $res['json']['plans']);
        $this->assertSame('Sanctuaire du Couple', $res['json']['couple']['libelle']);
        $this->assertDoesNotMatchRegularExpression('/\b(prix|price|eur|euros|fcfa|tarif)\b/i', $res['raw'], 'Aucun prix dans l\'application.');

        $this->assertNull($bySlug[$course->getSlug()]['vignette'], 'Pas d\'image : l\'application dessine la vignette.');
        $this->assertSame(['VIE'], array_column($bySlug[$course->getSlug()]['plansCouverts'], 'cle'));
        $this->assertTrue($bySlug[$course->getSlug()]['plansCouverts'][0]['principal']);

        $res = $this->api('GET', '/api/mobile/v1/academie/'.$course->getSlug(), bearer: $token);
        $this->assertSame('lecon-1', $res['json']['parcours']['prochaineLecon']);
        $this->assertCount(6, $res['json']['parcours']['sixPlans']);
        $this->assertSame(2, $res['json']['parcours']['chiffres']['lecons']);
        $this->assertSame(['cible' => 'VIE', 'libelle' => 'Plan de Vie', 'sorte' => 'plan'], $res['json']['parcours']['modules'][0]['lecons'][0]['exercice']);
        $this->assertNull($res['json']['parcours']['modules'][0]['lecons'][1]['exercice']);
        $this->assertSame('FREE', $res['json']['parcours']['rythme']['mode']);

        $res = $this->api('GET', '/api/mobile/v1/academie/'.$course->getSlug().'/lecon-1', bearer: $token);
        $this->assertSame(200, $res['status']);
        $lesson = $res['json']['lecon'];
        $this->assertSame('Ce qui compte', $lesson['titre']);
        $this->assertStringContainsString('<p>', $lesson['texteHtml']);
        $this->assertSame('exercice', $lesson['pratique']['type']);
        $this->assertSame('Enregistrer dans mon Plan de Vie', $lesson['pratique']['bouton']);
        $this->assertSame('lecon-2', $lesson['suivante']);

        // Parcours PRO : refusé avec le code habituel, sans prix
        $res = $this->api('GET', '/api/mobile/v1/academie/'.$pro->getSlug().'/lecon-1', bearer: $token);
        $this->assertSame(403, $res['status']);
        $this->assertSame('pro_required', $res['json']['code']);
        $this->assertSame(404, $this->api('GET', '/api/mobile/v1/academie/inconnu-'.uniqid(), bearer: $token)['status']);
    }

    public function testPracticeNotesAndPaceFollowTheSameRulesAsTheWebsite(): void
    {
        [$user, $token] = $this->login();
        $course = $this->makeCourse('VIE', lessons: [['Ce qui compte', 'VIE', 'add_value'], ['Suite', null, null]]);
        $base = '/api/mobile/v1/academie/'.$course->getSlug();

        // Note de leçon : dans le Journal
        $res = $this->api('POST', $base.'/lecon-1/notes', ['contenu' => 'Déclic dans le métro', 'plan' => 'INTENDANCE'], $token);
        $this->assertSame(201, $res['status']);
        $this->assertSame('Plan d\'Intendance', $res['json']['note']['libellePlan']);
        $noteId = $res['json']['note']['id'];

        // Exercice : la valeur arrive dans le Plan de Vie, puis est mise à jour sans doublon
        $res = $this->api('POST', $base.'/lecon-1/terminer', ['mode' => 'plan', 'texte' => 'Fidélité mobile', 'precisions' => 'Tenir parole'], $token);
        $this->assertSame(200, $res['status']);
        $this->assertSame('Plan de Vie', $res['json']['enregistreDans']);
        $this->assertSame('lecon-2', $res['json']['leconSuivante']);
        $this->assertSame(50, $res['json']['progression']['pourcentage']);
        $res = $this->api('POST', $base.'/lecon-1/terminer', ['mode' => 'plan', 'texte' => 'Fidélité mobile, encore'], $token);
        $this->em()->clear();
        $values = array_filter($this->em()->getRepository(Value::class)->findAll(), fn (Value $v) => str_starts_with((string) $v->getName(), 'Fidélité mobile') && $v->getPlanDeVie()?->getUser()?->getId() === $user->getId());
        $this->assertCount(1, $values);
        $this->assertSame('Fidélité mobile, encore', array_values($values)[0]->getName());
        $res = $this->api('GET', $base.'/lecon-1', bearer: $token);
        $this->assertTrue($res['json']['lecon']['pratique']['dejaEnregistre']);
        $this->assertSame('Fidélité mobile, encore', $res['json']['lecon']['pratique']['reponse']['texte']);
        $this->assertCount(1, $res['json']['lecon']['notes']);

        // Point à travailler : destination inconnue refusée, rien de terminé
        $res = $this->api('POST', $base.'/lecon-2/terminer', ['mode' => 'plan', 'texte' => 'X', 'destination' => 'VIE|update_purpose'], $token);
        $this->assertSame(422, $res['status']);
        $this->assertSame('PRACTICE_REFUSED', $res['json']['code']);
        $res = $this->api('POST', $base.'/lecon-2/terminer', ['mode' => 'sans'], $token);
        $this->assertTrue($res['json']['parcoursTermine']);
        $this->assertTrue($this->api('GET', $base, bearer: $token)['json']['parcours']['proposerBoussole']);

        // Rythme
        $res = $this->api('POST', $base.'/rythme', ['rythme' => 'THREE_PER_WEEK'], $token);
        $this->assertSame('THREE_PER_WEEK', $res['json']['rythme']['mode']);
        $this->assertSame(400, $this->api('POST', $base.'/rythme', ['rythme' => 'HOURLY'], $token)['status']);

        // Un autre membre ne peut pas supprimer la note ; son auteur, si
        [, $other] = $this->login();
        $this->assertSame(404, $this->api('DELETE', '/api/mobile/v1/academie/notes/'.$noteId, bearer: $other)['status']);
        $this->assertSame(200, $this->api('DELETE', '/api/mobile/v1/academie/notes/'.$noteId, bearer: $token)['status']);
    }

    public function testARitualOffersAPartenaireDeRedevabilite(): void
    {
        [, $token] = $this->login(pro: true);
        $course = $this->makeCourse('VIE', lessons: [['Un rituel', 'RITUEL', 'add_habit']]);
        $res = $this->api('POST', '/api/mobile/v1/academie/'.$course->getSlug().'/lecon-1/terminer', ['mode' => 'plan', 'texte' => 'Marcher le soir', 'choix' => 'WEEKLY'], $token);
        $this->assertSame(200, $res['status']);
        $this->assertStringStartsWith('HABIT:', $res['json']['redevabilite']['cible']);
        $this->assertStringContainsString('Partenaire de Redevabilité', $res['json']['redevabilite']['question']);
        $this->em()->clear();
        $this->assertSame(1, $this->em()->getRepository(CourseEnrollment::class)->count(['course' => $course->getId()]));
    }
}
