<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Psychometrie\BiblicalFigures;
use PHPUnit\Framework\TestCase;

/**
 * Les fiches de personnages bibliques : structure complète, et chaque citation figure bien dans la Louis Segond 1910.
 * Le texte biblique (var/bible/lsg.json, voir bin/outils/bible.py) n'est pas versionné : sans lui, la vérification est ignorée.
 */
class BiblicalFiguresTest extends TestCase
{
    private const BOOKS = ['1 rois' => '1Kgs', '2 rois' => '2Kgs', 'jacques' => 'Jas', 'matthieu' => 'Matt', 'luc' => 'Luke', 'philippiens' => 'Phil',
        '1 timothée' => '1Tim', '2 timothée' => '2Tim', '1 corinthiens' => '1Cor', 'actes' => 'Acts', 'jean' => 'John', 'psaumes' => 'Ps'];

    public function testEveryFigureIsComplete(): void
    {
        foreach (BiblicalFigures::FIGURES as $key => $f) {
            foreach (['name', 'aliases', 'title', 'refs', 'why', 'who', 'episodes', 'reading', 'weakness', 'grace', 'christ', 'quotes', 'question', 'step'] as $field) {
                $this->assertArrayHasKey($field, $f, "$key : champ $field");
                $this->assertNotEmpty($f[$field], "$key : champ $field vide");
            }
            $this->assertArrayHasKey('default', $f['why'], "$key : texte par défaut");
            $this->assertGreaterThanOrEqual(5, \count($f['episodes']), "$key : au moins cinq épisodes");
            $this->assertNotEmpty($f['reading']['traits'], "$key : traits");
            $this->assertStringContainsString('pas une affirmation du texte', $f['reading']['temperament'], "$key : la lecture doit être dite comme telle");
            $this->assertMatchesRegularExpression('/Christ|Fils|Seigneur|Sauveur/u', $f['christ'], "$key : le Christ au centre");
        }
    }

    public function testFindByNameAndWhy(): void
    {
        $this->assertSame('ELIE', BiblicalFigures::find('Élie')['key']);
        $this->assertSame('JOSEPH', BiblicalFigures::find('Joseph de Nazareth')['key']);
        $this->assertNull(BiblicalFigures::find('Personne'));
        $joseph = BiblicalFigures::find('Joseph de Nazareth');
        $this->assertStringContainsString('fidèle', BiblicalFigures::why($joseph, 'S'));
        $this->assertSame($joseph['why']['default'], BiblicalFigures::why($joseph, 'D'));
    }

    public function testQuotesAreInTheLouisSegond(): void
    {
        $file = \dirname(__DIR__, 2).'/var/bible/lsg.json';
        if (!is_file($file)) {
            $this->markTestSkipped('Texte de la Louis Segond absent (var/bible/lsg.json).');
        }
        $bible = [];
        foreach (json_decode((string) file_get_contents($file), true)['books'] as $b) {
            $bible[$b['book']] = $b;
        }
        $norm = fn (string $s) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', str_replace(["''", '’'], "'", $s)))));
        $checked = 0;
        foreach (BiblicalFigures::FIGURES as $key => $f) {
            $quotes = $f['quotes'];
            foreach ($f['episodes'] as $e) {
                if (isset($e['quote'])) {
                    $quotes[] = $e['quote'];
                }
            }
            foreach ($quotes as $q) {
                $this->assertMatchesRegularExpression('/^((?:[12] )?[^\d]+) (\d+):(\d+)(?:-(\d+))?$/u', $q['ref'], "$key : référence {$q['ref']}");
                preg_match('/^((?:[12] )?[^\d]+) (\d+):(\d+)(?:-(\d+))?$/u', $q['ref'], $m);
                $book = self::BOOKS[mb_strtolower(trim($m[1]))] ?? null;
                $this->assertNotNull($book, "$key : livre inconnu du test : {$m[1]}");
                $chapter = null;
                foreach ($bible[$book]['chapters'] as $c) {
                    if ((int) $m[2] === $c['chapter']) {
                        $chapter = $c;
                    }
                }
                $this->assertNotNull($chapter, "$key : chapitre introuvable {$q['ref']}");
                $text = '';
                foreach ($chapter['verses'] as $v) {
                    if ($v['number'] >= (int) $m[3] && $v['number'] <= (int) ($m[4] ?? $m[3])) {
                        $text .= ' '.$v['text'];
                    }
                }
                $this->assertStringContainsString($norm($q['text']), $norm($text), "$key : la citation ne figure pas dans {$q['ref']}");
                ++$checked;
            }
        }
        $this->assertGreaterThanOrEqual(12, $checked);
    }
}
