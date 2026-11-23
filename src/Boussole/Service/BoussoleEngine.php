<?php

namespace App\Boussole\Service;

use App\PlanDeVie\Service\PlanCatalog;

/**
 * Calcul de la Boussole 360 côté serveur : même résultat, au caractère près, que assets/js/boussole-engine.js.
 *
 * Le navigateur affiche le résultat tout de suite avec le moteur JS ; le serveur refait le calcul à partir des
 * 24 réponses brutes avant tout enregistrement (on ne fait jamais confiance à un score envoyé par le client).
 * L'application mobile n'a pas de moteur : elle envoie les réponses et affiche ce que renvoie l'API.
 * Parité vérifiée par tests/Boussole/BoussoleParityTest.php.
 */
final class BoussoleEngine
{
    public const PLAN_ORDER = ['VIE', 'CROISSANCE', 'DEVELOPPEMENT', 'HYGIENE', 'INTENDANCE', 'TRANSMISSION'];
    public const ITEM_COUNT = 24;

    /**
     * Vérifie que les réponses sont exactement 24 entiers de 1 à 5 (dans l'ordre des affirmations).
     *
     * @return list<int>|null les réponses normalisées, ou null si elles sont invalides
     */
    public static function normalizeAnswers(mixed $answers): ?array
    {
        if (!\is_array($answers) || !array_is_list($answers) || self::ITEM_COUNT !== \count($answers)) {
            return null;
        }
        $clean = [];
        foreach ($answers as $answer) {
            if (\is_string($answer) && ctype_digit($answer)) {
                $answer = (int) $answer;
            }
            if (!\is_int($answer) || $answer < 1 || $answer > 5) {
                return null;
            }
            $clean[] = $answer;
        }

        return $clean;
    }

    public static function planName(string $key): string
    {
        return PlanCatalog::label($key);
    }

    /**
     * Les 6 plans dans l'ordre du radar : clé, nom (PlanCatalog), nom court, couleur, angle.
     *
     * @return list<array{cle: string, nom: string, court: string, couleur: string, angle: int}>
     */
    public static function plans(): array
    {
        return array_map(fn (string $k) => [
            'cle' => $k,
            'nom' => self::planName($k),
            'court' => BoussoleContent::PLANS[$k]['short'],
            'couleur' => BoussoleContent::PLANS[$k]['color'],
            'angle' => BoussoleContent::PLANS[$k]['angle'],
        ], self::PLAN_ORDER);
    }

    /**
     * @param list<int> $answers 24 réponses brutes (1 à 5)
     *
     * @return array<string, mixed> mêmes clés que evaluate() du moteur JS
     */
    public function evaluate(array $answers): array
    {
        if (self::ITEM_COUNT !== \count($answers)) {
            throw new \InvalidArgumentException('24 réponses sont requises.');
        }
        $answers = array_values($answers);

        $sums = array_fill_keys(self::PLAN_ORDER, 0);
        $weakSignals = [];
        foreach (BoussoleContent::ITEMS as $i => $item) {
            $raw = (int) $answers[$i];
            $raw = max(1, min(5, 0 === $raw ? 3 : $raw));
            $effective = $item['rev'] ? 6 - $raw : $raw;
            $sums[$item['plan']] += $effective;
            if ($effective <= 2) {
                $weakSignals[] = ['plan' => $item['plan'], 'score' => $effective, 'text' => $item['lowSignal']];
            }
        }

        $scores = [];
        foreach (self::PLAN_ORDER as $k) {
            // 4 affirmations de 1 à 5 : minimum 4, maximum 20
            $scores[$k] = self::jsRound((($sums[$k] - 4) / 16) * 100);
        }

        $vals = array_values($scores);
        $coherence = self::jsRound(array_sum($vals) / \count($vals));
        $maxScore = max($vals);
        $minScore = min($vals);
        $spread = $maxScore - $minScore;

        $topKeys = array_values(array_filter(self::PLAN_ORDER, fn (string $k) => $scores[$k] === $maxScore));
        $lowKeys = array_values(array_filter(self::PLAN_ORDER, fn (string $k) => $scores[$k] === $minScore));

        if (0 === $spread) {
            $priorityKey = $coherence <= 35 ? 'HYGIENE' : ($coherence >= 75 ? 'TRANSMISSION' : 'VIE');
            $strongestKey = $priorityKey;
        } else {
            $strongestKey = $topKeys[0];
            $priorityKey = $lowKeys[0];
        }

        $strongestName = self::planName($strongestKey);
        $priorityName = self::planName($priorityKey);
        $tiedNames = fn (string $sep) => implode($sep, array_map(self::planName(...), $lowKeys));

        if ($spread < 10) {
            if ($coherence >= 75) {
                $readingMain = 'Votre socle est déjà solide et remarquablement homogène ('.$coherence.' %). Vous avez posé des bases concrètes dans l\'ensemble de votre vie.';
                $readingSub = 'L\'enjeu n\'est plus de tout remettre en ordre, mais de garder ce rythme dans la durée et d\'élargir votre '.$priorityName.' ('.$scores[$priorityKey].' %).';
            } elseif ($coherence <= 35) {
                $readingMain = 'Tous vos domaines sont sous tension au même moment ('.$coherence.' %). Ce n\'est pas un manque de bonne volonté : à force de répondre aux urgences, plus aucun domaine ne dispose d\'un cadre clair.';
                $readingSub = 'Ne cherchez surtout pas à tout réparer en même temps. Commencer par restaurer un minimum de souffle sur votre '.$priorityName.' ('.$scores[$priorityKey].' %) est la première marche réaliste.';
            } else {
                $readingMain = 'Vos six domaines ressortent presque au même niveau autour de '.$coherence.' % (écart de '.$spread.' points). C\'est souvent le signe d\'une saison entre deux eaux, ou de réponses prudentes : rien n\'est en crise ouverte, mais aucun domaine n\'a encore pris un vrai cap.';
                $readingSub = 'Quand tout est à niveau égal, le meilleur point d\'entrée est votre '.$priorityName.' : c\'est lui qui donne de la clarté aux cinq autres.';
            }
        } elseif ($coherence <= 30) {
            $readingMain = 'La majorité de vos domaines sont aujourd\'hui sous tension ('.$coherence.' % en moyenne). Ce n\'est pas un manque de volonté : sans cadre simple, les urgences finissent par tout absorber.';
            $readingSub = 'Inutile de tout attaquer en même temps : stabiliser d\'abord votre '.$priorityName.' ('.$scores[$priorityKey].' %) est le pas le plus concret pour reprendre pied.';
        } elseif ($coherence >= 75 && $minScore >= 65) {
            $readingMain = 'Votre équilibre d\'ensemble est solide ('.$coherence.' %), avec tous vos domaines au-dessus de 65 %. Vous disposez déjà de bonnes fondations dans votre vie quotidienne.';
            $readingSub = 'Votre principale marge de progression se situe sur votre '.$priorityName.' ('.$scores[$priorityKey].' %) : le renforcer vous permettra de passer d\'une bonne stabilité à un impact plus large.';
        } elseif ($scores[$strongestKey] < 70) {
            $readingMain = 'Aucun de vos six domaines n\'est encore pleinement consolidé (votre meilleur score est à '.$scores[$strongestKey].' % sur votre '.$strongestName.'), mais un point décroche nettement : votre '.$priorityName.' ('.$scores[$priorityKey].' %).';
            if (\count($lowKeys) > 1) {
                $readingSub = 'Deux domaines arrivent ex æquo en bas ('.$tiedNames(' et ').' à '.$minScore.' %). Commencez par celui qui pèse le plus sur votre semaine.';
            } else {
                $readingSub = 'Votre score global est de '.$coherence.' % ('.$spread.' points d\'écart). Commencer par stabiliser votre '.$priorityName.' est le levier le plus concret pour les 30 prochains jours.';
            }
        } else {
            $readingMain = BoussoleContent::PAIR_DYNAMICS[$strongestKey.'__'.$priorityKey]
                ?? 'Votre '.$strongestName.' ('.$scores[$strongestKey].' %) contraste avec votre '.$priorityName.' ('.$scores[$priorityKey].' %).';
            if (\count($lowKeys) > 1) {
                $readingSub = 'Votre score global de '.$coherence.' % masque un écart de '.$spread.' points. Deux domaines arrivent ex æquo en bas ('.$tiedNames(' et ').' à '.$minScore.' %) : commencez par celui qui pèse le plus sur votre semaine.';
            } elseif ($spread >= 30) {
                $readingSub = 'Votre score global de '.$coherence.' % masque un contraste marqué ('.$spread.' points d\'écart). Concentrer vos 30 prochains jours sur votre '.$priorityName.' ('.$scores[$priorityKey].' %) aura plus d\'effet que de disperser vos efforts partout.';
            } else {
                $readingSub = 'L\'écart entre vos domaines reste modéré ('.$spread.' points). Renforcer votre '.$priorityName.' ('.$scores[$priorityKey].' %) est le levier le plus direct pour franchir un palier.';
            }
        }

        // Libellés honnêtes (jamais « point fort » sous 55 %, jamais « freine » à 65 % ou plus)
        $strongLabel = $scores[$strongestKey] >= 55 ? 'Ce sur quoi vous pouvez vous appuyer' : 'Votre domaine le moins fragilisé';
        if (0 === $spread) {
            $prioLabel = 'Par où commencer';
        } elseif ($scores[$priorityKey] >= 65) {
            $prioLabel = 'Votre principale marge de progression';
        } else {
            $prioLabel = \count($lowKeys) > 1 ? 'Vos priorités actuelles (ex æquo)' : 'Ce qui vous freine le plus en ce moment';
        }
        $prioTitle = \count($lowKeys) > 1 && $spread > 0
            ? $tiedNames(' & ').' ('.$minScore.' %)'
            : $priorityName.' ('.$scores[$priorityKey].' %)';

        // Signaux : d'abord ceux du plan prioritaire, puis par gravité (tri stable, comme en JS)
        usort($weakSignals, function (array $a, array $b) use ($priorityKey): int {
            $aPrio = $a['plan'] === $priorityKey ? 0 : 1;
            $bPrio = $b['plan'] === $priorityKey ? 0 : 1;

            return $aPrio !== $bPrio ? $aPrio - $bPrio : $a['score'] - $b['score'];
        });

        $strongest = BoussoleContent::PLANS[$strongestKey];
        $priority = BoussoleContent::PLANS[$priorityKey];

        return [
            'scores' => $scores,
            'coherence' => $coherence,
            'spread' => $spread,
            'strongestKey' => $strongestKey,
            'priorityKey' => $priorityKey,
            'topKeys' => $topKeys,
            'lowKeys' => $lowKeys,
            'showStrongCard' => $spread > 0,
            'strongLabel' => $strongLabel,
            'strongTitle' => $strongestName.' ('.$scores[$strongestKey].' %)',
            'strongText' => self::textForScore($strongest, $scores[$strongestKey]),
            'prioLabel' => $prioLabel,
            'prioTitle' => $prioTitle,
            'prioText' => self::textForScore($priority, $scores[$priorityKey]),
            'readingMain' => $readingMain,
            'readingSub' => $readingSub,
            'signals' => array_map(fn (array $s) => $s['text'], \array_slice($weakSignals, 0, 3)),
            'steps' => $scores[$priorityKey] < 45 ? $priority['stepsLow'] : $priority['stepsMid'],
            'stepsMode' => $scores[$priorityKey] < 45 ? 'stabilisation' : 'structuration',
            'nextPriorityBadge' => $priorityName.' · '.$scores[$priorityKey].' %',
        ];
    }

    /**
     * @param array<string, mixed> $plan
     */
    private static function textForScore(array $plan, int $score): string
    {
        if ($score >= 65) {
            return $plan['highText'];
        }

        return $score >= 40 ? $plan['midText'] : $plan['lowText'];
    }

    /** Math.round de JavaScript (moitié vers le haut), pour des valeurs positives ou nulles. */
    private static function jsRound(float|int $value): int
    {
        return (int) floor($value + 0.5);
    }
}
