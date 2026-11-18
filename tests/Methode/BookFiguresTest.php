<?php

namespace App\Tests\Methode;

use App\Methode\Service\BookFigures;
use PHPUnit\Framework\TestCase;

/** Les schémas interactifs reprennent le livre mot pour mot : chaque phrase doit figurer dans le chapitre source. */
class BookFiguresTest extends TestCase
{
    private static function norm(string $s): string
    {
        $s = mb_strtolower(str_replace(['’', '**', '*', '\\'], ["'", '', '', ''], $s));
        $s = preg_replace('/[«»"“”]/u', '', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    public function testEveryFigureHasItsItems(): void
    {
        foreach (BookFigures::FIGURES as $name => $fig) {
            $data = BookFigures::get($name);
            $this->assertGreaterThanOrEqual(3, \count($data['items']), $name);
            $keys = array_column($data['items'], 'key');
            $this->assertSame($keys, array_unique($keys), $name.' : clés en double');
            foreach ($data['items'] as $i) {
                $this->assertNotSame('', $i['summary'], $name);
                $this->assertNotEmpty($i['lines'], $name.' '.$i['key']);
            }
        }
        $this->assertNull(BookFigures::get('fig_inconnue'));
    }

    public function testTheTextIsTheBooksText(): void
    {
        foreach (BookFigures::FIGURES as $name => $fig) {
            $book = self::norm((string) file_get_contents(\dirname(__DIR__, 2).'/content/livre/'.$fig['chapter'].'.md'));
            foreach (BookFigures::get($name)['items'] as $i) {
                $texts = [$i['summary'], ...array_column($i['lines'], 1)];
                foreach ($texts as $t) {
                    foreach (preg_split('/(?<=[.;?!])\s+/u', $t) as $part) {
                        $n = rtrim(self::norm($part), '.;?! ');
                        if (mb_strlen($n) >= 12) {
                            $this->assertStringContainsString($n, $book, $name.' '.$i['key'].' : « '.$part.' » ne figure pas dans '.$fig['chapter']);
                        }
                    }
                }
            }
        }
    }
}
