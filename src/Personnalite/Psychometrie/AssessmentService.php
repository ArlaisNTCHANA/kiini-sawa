<?php

namespace App\Personnalite\Psychometrie;

use App\Compte\Entity\User;
use App\Personnalite\Entity\Assessment;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Repository\AssessmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre une passation, calcule les scores et alimente les plans.
 *
 * Pour les questionnaires IPIP, les scores portent sur toutes les réponses IPIP les plus récentes
 * de la personne (profil complet, type, style…) : chaque passation enrichit le profil.
 * Le résultat est aussi écrit dans ProfileTest (types BIG5, MBTI, DISC, ENNEAGRAM, DONS),
 * utilisé par le Plan de Vie, le hub des stratégies et le rapport de couple.
 */
final class AssessmentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssessmentRepository $assessments,
        private readonly Scorer $scorer,
    ) {
    }

    /** @param array<string, mixed> $raw réponses envoyées (clé d'item => 1-5) */
    public function record(User $user, string $instrument, array $raw, ?int $seconds): Assessment
    {
        $allowed = array_column(Instruments::items($instrument), 'key');
        $answers = [];
        foreach ($allowed as $key) {
            $v = (int) ($raw[$key] ?? 0);
            if ($v >= 1 && $v <= 5) {
                $answers[$key] = $v;
            }
        }
        if (\count($answers) < \count($allowed)) {
            throw new \InvalidArgumentException(sprintf('Merci de répondre à toutes les questions (%d sur %d).', \count($answers), \count($allowed)));
        }

        $assessment = new Assessment($user, $instrument, $answers, $seconds);
        $bank = Instruments::CATALOG[$instrument]['bank'];
        $scores = match ($bank) {
            'ipip' => $this->scorer->ipip($this->mergedIpipAnswers($user, $answers)),
            'enn' => $this->scorer->enneagram($answers),
            default => $this->scorer->gifts($answers),
        };
        $assessment->setScores($scores)->setQuality($this->scorer->quality($answers, $seconds));
        $this->em->persist($assessment);
        $this->writeProfileTests($user, $bank, $scores);
        $this->em->flush();

        return $assessment;
    }

    /** Dernières réponses IPIP de la personne, complétées par les nouvelles. */
    public function mergedIpipAnswers(User $user, array $new = []): array
    {
        $merged = [];
        foreach (array_reverse($this->assessments->findForUser($user)) as $a) {
            if ('ipip' === (Instruments::CATALOG[$a->getInstrument()]['bank'] ?? null)) {
                $merged = array_merge($merged, $a->getAnswers());
            }
        }

        return array_merge($merged, $new);
    }

    /** Profil IPIP actuel de la personne (toutes réponses confondues), ou null. */
    public function currentProfile(User $user): ?array
    {
        $answers = $this->mergedIpipAnswers($user);

        return $answers ? $this->scorer->ipip($answers) : null;
    }

    private function writeProfileTests(User $user, string $bank, array $scores): void
    {
        $add = function (string $type, array|string $result, string $insights) use ($user): void {
            $this->em->persist((new ProfileTest())->setUser($user)->setType($type)
                ->setResult(\is_array($result) ? json_encode($result, \JSON_UNESCAPED_UNICODE) : $result)
                ->setInsights($insights)->setDate(new \DateTime()));
        };

        if ('ipip' === $bank) {
            if (\count($scores['domains']) === 5) {
                $d = $scores['domains'];
                $add('BIG5', ['O' => $d['O']['score'], 'C_big5' => $d['C']['score'], 'E' => $d['E']['score'], 'A' => $d['A']['score'], 'N' => $d['N']['score']], 'Big Five (IPIP-NEO)');
            }
            if ($scores['type']) {
                $code = $scores['type']['code'];
                $add('MBTI', ['type' => $code], Interpretations::TYPES[$code][0].' — '.Interpretations::TYPES[$code][1]);
            }
            if ($scores['style']) {
                $s = $scores['style'];
                $add('DISC', $s['scores'], Interpretations::STYLES[$s['primary']]['name'].' — '.Interpretations::STYLES[$s['primary']]['essence']);
            }
        } elseif ('enn' === $bank) {
            $t = EnneagramBank::TYPES[$scores['type']];
            $add('ENNEAGRAM', ['type' => $scores['type'], 'wing' => $scores['wing']], $t['name'].' — '.$t['motivation']);
        } else {
            $names = array_map(fn ($g) => SpiritualGiftsBank::GIFTS[$g]['name'], $scores['top']);
            $add('DONS', ['top' => $scores['top']], implode(', ', $names));
        }
    }
}
