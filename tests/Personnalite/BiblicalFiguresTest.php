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
    private const BOOKS = ['genèse' => 'Gen', 'exode' => 'Exod', 'lévitique' => 'Lev', 'nombres' => 'Num', 'deutéronome' => 'Deut', 'josué' => 'Josh', 'juges' => 'Judg', 'ruth' => 'Ruth',
        '1 samuel' => '1Sam', '2 samuel' => '2Sam', '1 rois' => '1Kgs', '2 rois' => '2Kgs', '1 chroniques' => '1Chr', '2 chroniques' => '2Chr', 'esdras' => 'Ezra', 'néhémie' => 'Neh',
        'esther' => 'Esth', 'job' => 'Job', 'psaumes' => 'Ps', 'proverbes' => 'Prov', 'ecclésiaste' => 'Eccl', 'ésaïe' => 'Isa', 'jérémie' => 'Jer', 'ézéchiel' => 'Ezek', 'daniel' => 'Dan',
        'osée' => 'Hos', 'jonas' => 'Jonah', 'michée' => 'Mic', 'aggée' => 'Hag', 'zacharie' => 'Zech', 'malachie' => 'Mal', 'matthieu' => 'Matt', 'marc' => 'Mark', 'luc' => 'Luke', 'jean' => 'John',
        'actes' => 'Acts', 'romains' => 'Rom', '1 corinthiens' => '1Cor', '2 corinthiens' => '2Cor', 'galates' => 'Gal', 'éphésiens' => 'Eph', 'philippiens' => 'Phil', 'colossiens' => 'Col',
        '1 thessaloniciens' => '1Thess', '2 thessaloniciens' => '2Thess', '1 timothée' => '1Tim', '2 timothée' => '2Tim', 'tite' => 'Titus', 'hébreux' => 'Heb', 'jacques' => 'Jas',
        '1 pierre' => '1Pet', '2 pierre' => '2Pet', '1 jean' => '1John', 'apocalypse' => 'Rev'];

    public function testEveryFigureIsComplete(): void
    {
        foreach (BiblicalFigures::all() as $key => $f) {
            foreach (['name', 'aliases', 'title', 'refs', 'who', 'episodes', 'weakness', 'grace', 'christ', 'quotes', 'question', 'step'] as $field) {
                $this->assertArrayHasKey($field, $f, "$key : champ $field");
                $this->assertNotEmpty($f[$field], "$key : champ $field vide");
            }
            $this->assertTrue(isset($f['profile']) || !empty($f['lenses']), "$key : un profil DISC ou au moins une lecture par test");
            $this->assertArrayHasKey('default', $f['why'] ?? ['default' => 1], "$key : texte par défaut");
            $this->assertGreaterThanOrEqual(5, \count($f['episodes']), "$key : au moins cinq épisodes");
            $this->assertMatchesRegularExpression('/Christ|Fils|Seigneur|Sauveur/u', $f['christ'], "$key : le Christ au centre");
            // La crédibilité d'abord : chaque dimension du profil est appuyée par des indices référencés, et on dit ce que le texte ne montre pas
            foreach ($f['lenses'] ?? [] as $lensKey => $lens) {
                $this->assertMatchesRegularExpression('/^(ENN|GIFT|TYPE):/', $lensKey, "$key : lecture $lensKey");
                foreach (['label', 'solidity', 'limits', 'why'] as $field) {
                    $this->assertNotEmpty($lens[$field] ?? null, "$key $lensKey : $field");
                }
                $this->assertGreaterThanOrEqual(2, \count($lens['traits']), "$key $lensKey : au moins deux indices");
                foreach ($lens['traits'] as $t) {
                    $this->assertCount(3, $t);
                    $this->assertMatchesRegularExpression('/\d+[:,]\d+/', $t[2], "$key $lensKey : la référence doit citer un verset");
                }
                $this->assertNotEmpty($lens['absent'], "$key $lensKey : ce que le texte ne montre pas");
            }
            if (!isset($f['profile'])) {
                continue;
            }
            $p = $f['profile'];
            $this->assertMatchesRegularExpression('/^[DISC]{1,2}$/', $p['code'], "$key : code DISC");
            $this->assertSame(str_split($p['code']), array_keys($p['dimensions']), "$key : une dimension par lettre du code");
            foreach ($p['dimensions'] as $letter => $dim) {
                $this->assertContains($dim['level'], [1, 2, 3], "$key $letter : solidité");
                $this->assertGreaterThanOrEqual(2, \count($dim['traits']), "$key $letter : au moins deux indices");
                foreach ($dim['traits'] as $t) {
                    $this->assertCount(3, $t, "$key $letter : trait, ce que dit le texte, référence");
                    $this->assertMatchesRegularExpression('/\d+[:,]\d+/', $t[2], "$key $letter : la référence doit citer un verset");
                }
            }
            $this->assertNotEmpty($p['absent'], "$key : ce que le texte ne montre pas");
            $this->assertNotEmpty($p['limits'], "$key : limites de la lecture");
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
        // Insensible aux accents, à la casse, à la ponctuation et à la forme Unicode (précomposée ou décomposée)
        $norm = fn (string $s) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(preg_replace('/\p{Mn}/u', '', \Normalizer::normalize(str_replace(["''", '’'], "'", $s), \Normalizer::FORM_D)))));
        $checked = 0;
        foreach (BiblicalFigures::all() as $key => $f) {
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

    /** Chaque figure citée par un profil DISC a sa fiche, et la fiche décrit bien le profil qui la cite. */
    public function testEveryDiscProfileFigureHasASheetOfTheSameProfile(): void
    {
        foreach (\App\Personnalite\Psychometrie\DiscCombos::PROFILES as $code => $profile) {
            foreach ($profile['figures'] as $figure) {
                $sheet = BiblicalFigures::find($figure['name']);
                $this->assertNotNull($sheet, "Fiche manquante : {$figure['name']} ($code)");
                $this->assertSame($code, $sheet['profile']['code'], "{$figure['name']} : la fiche décrit le profil {$sheet['profile']['code']}, pas $code");
            }
        }
    }

    public function testEveryEnneagramFigureHasALensSheet(): void
    {
        foreach (\App\Personnalite\Psychometrie\EnneagramProfiles::TYPES as $type => $t) {
            foreach ($t['figures'] as $f) {
                $sheet = BiblicalFigures::find($f['name']);
                $this->assertNotNull($sheet, 'Pas de fiche pour '.$f['name']);
                $lens = $sheet['lenses']['ENN:'.$type] ?? null;
                $this->assertNotNull($lens, $f['name'].' sans lecture ENN:'.$type);
                $this->assertGreaterThanOrEqual(2, \count($lens['traits']));
            }
        }
    }

    public function testEveryGiftFigureHasALensSheet(): void
    {
        foreach (\App\Personnalite\Psychometrie\GiftProfiles::GIFTS as $code => $g) {
            $this->assertCount(2, $g['figures'], $code);
            foreach ($g['figures'] as $f) {
                $sheet = BiblicalFigures::find($f[0]);
                $this->assertNotNull($sheet, 'Pas de fiche pour '.$f[0]);
                $lens = $sheet['lenses']['GIFT:'.$code] ?? null;
                $this->assertNotNull($lens, $f[0].' sans lecture GIFT:'.$code);
                $this->assertGreaterThanOrEqual(3, \count($lens['traits']));
            }
        }
    }
}
