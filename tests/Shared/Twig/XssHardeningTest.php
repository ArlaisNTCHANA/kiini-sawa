<?php

namespace App\Tests\Shared\Twig;

use App\Compte\Entity\User;
use App\Journal\Entity\ReflectionNote;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use App\Shared\Twig\JsonSafeExtension;
use App\Shared\Twig\MermaidExtension;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Audit de sécurité, lot 1 : données saisies par les utilisateurs et JavaScript / HTML / Mermaid. */
class XssHardeningTest extends WebTestCase
{
    use AccountTestTrait;

    private const PAYLOADS = ['</script><img src=x onerror=alert(1)>', '\');alert(1);(\'', '"><svg onload=alert(1)>', '<!-- <script>', "a\u{2028}b'c\"d&e"];

    // ── Filtre json_safe ─────────────────────────────────────────────────────

    public function testJsonSafeNeverLetsAValueLeaveItsScriptOrAttribute(): void
    {
        foreach (self::PAYLOADS as $payload) {
            $out = (string) JsonSafeExtension::encode(['title' => $payload, 'n' => [1, 2]]);
            $this->assertSame(0, preg_match('/[<>&\']/', $out), 'caractère dangereux dans : '.$out);
            $this->assertSame(1, substr_count($out, '"') % 2 === 0 ? 1 : 0, 'guillemets non appariés : '.$out);
            $this->assertSame(['title' => $payload, 'n' => [1, 2]], json_decode($out, true), 'le JSON reste fidèle à la valeur');
            $this->assertStringNotContainsString("\u{2028}", $out);
        }
        $this->assertSame('null', (string) JsonSafeExtension::encode(null));
        $this->assertSame('"é"', (string) JsonSafeExtension::encode('é'), 'les accents restent lisibles');
    }

    public function testNoTemplateInsertsJsonAsRawHtml(): void
    {
        $bad = [];
        foreach ($this->templates() as $file => $source) {
            if (preg_match('/\|\s*json_encode\s*\|\s*raw/', $source) || preg_match('/\|\s*json_encode\s*\}\}/', $source)) {
                $bad[] = $file;
            }
        }
        $this->assertSame([], $bad, 'utiliser |json_safe à la place de |json_encode|raw');
    }

    public function testAlpineDirectivesNeverInterpolateTwigBetweenQuotes(): void
    {
        $rituels = (string) file_get_contents(\dirname(__DIR__, 3).'/templates/rituels/index.html.twig');
        $this->assertStringNotContainsString("matchesSearch('{{", $rituels);
        $this->assertStringContainsString('data-title="{{ habit.title }}"', $rituels);
        $this->assertStringContainsString('matchesSearch($el.dataset.title)', $rituels);
    }

    public function testWizardEscapesGoalTitlesBeforeInsertingHtml(): void
    {
        $wizard = (string) file_get_contents(\dirname(__DIR__, 3).'/templates/plan_de_vie/wizard.html.twig');
        $this->assertStringNotContainsString('${lg.title}', $wizard);
        $this->assertSame(3, substr_count($wizard, '${ksEscape(lg.title)}'));
        $this->assertStringContainsString('function ksEscape', $wizard);
    }

    // ── Mermaid ──────────────────────────────────────────────────────────────

    public function testMermaidLabelsCannotCloseTheLabelOrInjectSyntax(): void
    {
        foreach (['Ma vision"]; click Goal1 call alert() "', "ligne1\nligne2 --> X", '<img src=x onerror=alert(1)>', '`code`', 'Titre #35; #quot;', "tab\tet\r\nretour"] as $in) {
            $out = MermaidExtension::clean($in);
            $this->assertSame(0, preg_match('/["<>`\[\]{};\n\r\t\\\\#]/u', $out), 'caractère interdit dans : '.$out);
        }
        $this->assertSame("L'avenir (2030)", MermaidExtension::clean("L'avenir [2030]"), 'les apostrophes restent');
        $this->assertSame('abc', MermaidExtension::clean('abcdef', 3));
    }

    public function testMermaidIsStrictAndEverySourceLabelIsCleaned(): void
    {
        foreach (['plan_de_vie/index.html.twig', 'plan_de_vie/plans.html.twig'] as $t) {
            $src = (string) file_get_contents(\dirname(__DIR__, 3).'/templates/'.$t);
            $this->assertStringNotContainsString("securityLevel: 'loose'", $src, $t);
            $this->assertStringContainsString("securityLevel: 'strict'", $src, $t);
            $this->assertStringNotContainsString('htmlLabels: true', $src, $t);
            $this->assertStringContainsString('htmlLabels: false', $src, $t);
            preg_match_all('/id="mermaid-[a-z-]*src".*?<\/div>/s', $src, $blocks);
            $this->assertNotEmpty($blocks[0], $t);
            foreach ($blocks[0] as $block) {
                preg_match_all('/\{\{[^}]*\}\}/', $block, $exprs);
                foreach ($exprs[0] as $expr) {
                    if (preg_match('/\b(title|name|content)\b/', $expr)) {
                        $this->assertStringContainsString('mermaid_label', $expr, $t.' : '.$expr);
                    }
                }
            }
        }
    }

    // ── Pages réelles ────────────────────────────────────────────────────────

    public function testHabitTitleCannotBreakOutOfTheRitualsPage(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $em = $this->em();
        $habit = (new Habit())->setUser($user)->setTitle("');alert(1);('</script><b id=pwn>")->setFrequency(HabitFrequency::DAILY);
        $em->persist($habit);
        $em->flush();
        $em->clear();
        $client->loginUser($em->find(User::class, $user->getId()));

        $client->request('GET', '/mes-plans/rituels');
        $this->assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent();
        $this->assertStringNotContainsString('<b id=pwn>', $html);
        $this->assertStringNotContainsString("matchesSearch('", $html);
        $this->assertStringContainsString('data-title="', $html);
    }

    public function testJournalEntryCannotCloseTheScriptOfTheJournalPage(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $em = $this->em();
        $note = (new ReflectionNote())->setUser($user)->setContent('</script><b id=pwn>boom</b>')->setDate(new \DateTime());
        $em->persist($note);
        $em->flush();
        $em->clear();
        $client->loginUser($em->find(User::class, $user->getId()));

        $client->request('GET', '/journal');
        $this->assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent();
        $this->assertStringNotContainsString('<b id=pwn>', $html);
        $this->assertStringContainsString('\\u003C/script\\u003E', $html, 'la balise est écrite en séquences \\\\u dans le JSON');
    }

    /** @return array<string, string> */
    private function templates(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 3).'/templates', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (str_ends_with($f->getFilename(), '.twig')) {
                $out[substr($f->getPathname(), \strlen(\dirname(__DIR__, 3)) + 1)] = (string) file_get_contents($f->getPathname());
            }
        }

        return $out;
    }
}
