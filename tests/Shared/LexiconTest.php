<?php

namespace App\Tests\Shared;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vocabulaire unique (docs/lexique.md) : aucun terme anglais ou abandonné dans le texte visible des pages.
 */
class LexiconTest extends WebTestCase
{
    use AccountTestTrait;

    private const BANNED = ['Mes stratégies', 'plans stratégiques', 'Quick Capture', 'Insight', 'Wheel of Life', 'Gantt', '(BE)', '(DO)', '(GIVE)', '(HAVE)',
        "Score d'Alignement", 'Mes Découvertes', 'Découverte de Soi', 'Architecture Intérieure', 'second cerveau',
        'Ancrage cognitif', 'Collections', 'Habitudes', 'Mon Réseau', 'Espace de Croissance'];

    public function testBookUsesTheCurrentBrandName(): void
    {
        foreach (glob(\dirname(__DIR__, 2).'/content/livre/*.md') as $file) {
            $this->assertStringNotContainsString('KIINI ALIGN', (string) file_get_contents($file), basename($file));
        }
    }

    public function testNoBannedWordsInVisibleText(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);

        foreach (['/dashboard', '/plan-de-vie', '/mes-plans', '/journal', '/mes-plans/rituels', '/planner', '/bilan', '/social', '/mes-tests/', '/abonnement', '/plan-de-vie/assistant'] as $url) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful($url);
            $doc = new \DOMDocument();
            @$doc->loadHTML($client->getResponse()->getContent());
            foreach (['script', 'style'] as $tag) {
                foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
                    $node->parentNode->removeChild($node);
                }
            }
            // Les extraits du livre gardent les mots du livre
            foreach (iterator_to_array((new \DOMXPath($doc))->query('//*[contains(concat(" ", @class, " "), " book-panel ")]')) as $node) {
                $node->parentNode->removeChild($node);
            }
            $text = preg_replace('/\s+/', ' ', $doc->textContent);
            foreach (self::BANNED as $word) {
                $pos = mb_strpos($text, $word); $this->assertFalse($pos, sprintf('« %s » encore visible sur %s : …%s…', $word, $url, false === $pos ? '' : mb_substr($text, max(0, $pos - 60), 140)));
            }
        }
    }

    /**
     * « Sanctuaire » : l'exercice du livre (« Le Sanctuaire ») ou le « Sanctuaire du Couple », jamais un sens général
     * (« votre sanctuaire », « Entrez dans votre sanctuaire »…). Décision du fondateur du 23/11/2026, docs/lexique.md.
     */
    public function testSanctuaireIsNeverUsedInAGeneralSense(): void
    {
        $root = static::getContainer()->getParameter('kernel.project_dir');
        $offenders = [];
        foreach (['templates', 'src'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!preg_match('/\.(twig|php)$/', $file->getFilename())) {
                    continue;
                }
                if (preg_match('/\b(votre|notre|ton|mon|un|une|leur)\s+sanctuaire\b(?!\s+du\s+couple)/iu', (string) file_get_contents($file->getPathname()))) {
                    $offenders[] = substr($file->getPathname(), \strlen($root) + 1);
                }
            }
        }
        $this->assertSame([], $offenders, '« Sanctuaire » doit désigner l\'exercice du livre ou le « Sanctuaire du Couple »');
    }
}
