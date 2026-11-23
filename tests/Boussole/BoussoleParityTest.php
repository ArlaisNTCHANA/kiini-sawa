<?php

namespace App\Tests\Boussole;

use App\Boussole\Service\BoussoleContent;
use App\Boussole\Service\BoussoleEngine;
use App\PlanDeVie\Service\PlanCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Le calcul serveur de la Boussole 360 donne exactement le résultat du moteur JS du site.
 * Cas produits par `node tests/boussole-parite.mjs --ecrire` à partir de assets/js/boussole-engine.js.
 */
final class BoussoleParityTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function fixture(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../fixtures/boussole-parite.json'), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function testDetailedCasesMatchTheJavascriptEngine(): void
    {
        $engine = new BoussoleEngine();
        foreach (self::fixture()['detail'] as $i => $case) {
            self::assertSame($case['result'], $engine->evaluate($case['answers']), 'Cas n°'.$i.' : '.implode(',', $case['answers']));
        }
    }

    public function testAllCasesMatchTheJavascriptEngine(): void
    {
        $engine = new BoussoleEngine();
        $cases = self::fixture()['empreintes'];
        self::assertGreaterThan(700, \count($cases));
        foreach ($cases as $i => $case) {
            $json = json_encode($engine->evaluate($case['answers']), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
            self::assertSame($case['sha256'], hash('sha256', $json), 'Cas n°'.$i.' : '.implode(',', $case['answers']));
        }
    }

    public function testStructureAndPlanNames(): void
    {
        self::assertCount(24, BoussoleContent::ITEMS);
        foreach (BoussoleEngine::PLAN_ORDER as $key) {
            $items = array_filter(BoussoleContent::ITEMS, fn (array $i) => $i['plan'] === $key);
            self::assertCount(4, $items);
            self::assertCount(2, array_filter($items, fn (array $i) => $i['rev']));
            self::assertArrayHasKey($key, PlanCatalog::PLANS);
        }
        self::assertCount(30, BoussoleContent::PAIR_DYNAMICS);
        self::assertSame(array_keys(PlanCatalog::PLANS), BoussoleEngine::PLAN_ORDER);
    }

    public function testAnswersAreValidatedStrictly(): void
    {
        self::assertSame(array_fill(0, 24, 3), BoussoleEngine::normalizeAnswers(array_fill(0, 24, '3')));
        self::assertNull(BoussoleEngine::normalizeAnswers(array_fill(0, 23, 3)));
        self::assertNull(BoussoleEngine::normalizeAnswers(array_fill(0, 25, 3)));
        self::assertNull(BoussoleEngine::normalizeAnswers([...array_fill(0, 23, 3), 6]));
        self::assertNull(BoussoleEngine::normalizeAnswers([...array_fill(0, 23, 3), 0]));
        self::assertNull(BoussoleEngine::normalizeAnswers([...array_fill(0, 23, 3), 2.5]));
        self::assertNull(BoussoleEngine::normalizeAnswers(['a' => 1]));
        self::assertNull(BoussoleEngine::normalizeAnswers('3,3,3'));
    }
}
