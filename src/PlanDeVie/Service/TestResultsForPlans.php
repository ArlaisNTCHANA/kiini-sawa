<?php

namespace App\PlanDeVie\Service;

use App\Compte\Entity\User;
use App\Personnalite\Entity\Assessment;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\Content;
use App\Personnalite\Psychometrie\DiscCombos;
use App\Personnalite\Repository\AssessmentRepository;
use App\Personnalite\Service\ConfirmationService;
use App\Personnalite\Service\SelfAssessmentReport;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Derniers résultats des tests, sous la forme utile aux plans : préremplissage du profil de personnalité (étape 5),
 * dons à proposer (étape 4 et Plan de Transmission), axes prioritaires du test Hygiène de Vie (étape 12).
 *
 * Lit les scores enregistrés (pas les rapports illustrés, plus coûteux) ; les noms passent par Content::get pour
 * suivre les textes modifiés dans l'administration.
 */
final class TestResultsForPlans
{
    /** Profils dominants du Big Five proposés dans l'assistant (combinaisons à deux lettres). */
    public const BIG5_COMBOS = ['OC', 'OE', 'CE', 'CA', 'AE'];

    public function __construct(
        private readonly AssessmentRepository $assessments,
        private readonly ConfirmationService $confirmations,
        private readonly SelfAssessmentReport $selfReports,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function forUser(User $user): array
    {
        return [
            'disc' => $this->disc($user),
            'type' => $this->type($user),
            'enneagram' => $this->enneagram($user),
            'big5' => $this->big5($user),
            'gifts' => $this->gifts($user),
            'hygiene' => $this->hygiene($user),
        ];
    }

    /** Les 16 profils DISC (code => « D · Le décideur »), dans l'ordre du catalogue. */
    public static function discOptions(): array
    {
        $names = Content::get('disc.profiles');
        $out = [];
        foreach (array_keys(DiscCombos::PROFILES) as $code) {
            $out[$code] = $names[$code]['name'] ?? $code;
        }

        return $out;
    }

    private function entry(Assessment $a, string $value, string $label): array
    {
        return ['value' => $value, 'label' => $label, 'date' => $a->getTakenAt(), 'reportId' => $a->getId()];
    }

    private function disc(User $user): ?array
    {
        $a = $this->assessments->latest($user, 'disc');
        $s = $a?->getScores();
        if (!isset($s['primary'])) {
            return null;
        }
        $code = DiscCombos::code($s['primary'], $s['secondary'] ?? null);

        return $this->entry($a, $code, $code.' · '.(self::discOptions()[$code] ?? $code));
    }

    /** Type à 4 lettres : test « type » ou profil complet, le plus récent des deux. */
    private function type(User $user): ?array
    {
        $best = null;
        foreach (['type', 'profil-complet'] as $slug) {
            $a = $this->assessments->latest($user, $slug);
            if (isset($a?->getScores()['type']['code']) && (!$best || $a->getTakenAt() > $best->getTakenAt())) {
                $best = $a;
            }
        }
        if (!$best) {
            return null;
        }
        $code = $best->getScores()['type']['code'];

        return $this->entry($best, $code, $code.' · '.(Content::get('type.types')[$code][0] ?? ''));
    }

    private function enneagram(User $user): ?array
    {
        $a = $this->assessments->latest($user, 'enneagramme');
        $t = $a?->getScores()['type'] ?? null;
        if (null === $t) {
            return null;
        }

        return $this->entry($a, (string) $t, 'Type '.$t.' · '.(Content::get('enn.types')[$t]['name'] ?? ''));
    }

    /** Les deux dimensions les plus hautes (hors stabilité émotionnelle) ; une seule si la paire n'est pas proposée. */
    private function big5(User $user): ?array
    {
        $best = null;
        foreach (['profil-complet', 'big-five'] as $slug) {
            $a = $this->assessments->latest($user, $slug);
            if (isset($a?->getScores()['domains']) && (!$best || $a->getTakenAt() > $best->getTakenAt())) {
                $best = $a;
            }
        }
        if (!$best) {
            return null;
        }
        $values = [];
        foreach (['O', 'C', 'E', 'A'] as $d) {
            if (isset($best->getScores()['domains'][$d]['score'])) {
                $values[$d] = (float) $best->getScores()['domains'][$d]['score'];
            }
        }
        if (!$values) {
            return null;
        }
        arsort($values);
        $top = array_keys($values);
        $code = $top[0];
        if (isset($top[1])) {
            foreach ([$top[0].$top[1], $top[1].$top[0]] as $pair) {
                if (\in_array($pair, self::BIG5_COMBOS, true)) {
                    $code = $pair;
                    break;
                }
            }
        }
        $labels = ['O' => 'Ouverture', 'C' => 'Conscienciosité', 'E' => 'Extraversion', 'A' => 'Agréabilité'];

        return $this->entry($best, $code, implode(' + ', array_map(fn ($l) => $labels[$l], str_split($code))));
    }

    /** Les dons principaux du dernier test, et ceux que les proches ont confirmés (dès 3 réponses). */
    public function gifts(User $user): ?array
    {
        $a = $this->assessments->latest($user, 'dons-spirituels');
        $top = $a?->getScores()['top'] ?? [];
        if (!$top) {
            return null;
        }
        $names = Content::get('gifts.gifts');
        $summary = $this->confirmations->summary(
            $this->confirmations->current($user, 'dons-spirituels'),
            ['gifts' => ['top' => array_map(fn ($g) => ['code' => $g], $top)]],
        );

        $mine = array_values(array_map(fn ($g) => $names[$g]['name'] ?? $g, $top));
        $confirmed = array_column($summary['confirmed'] ?? [], 'name');
        $seenByOthers = array_column($summary['others'] ?? [], 'name'); // cités par au moins 2 proches, hors de vos dons principaux

        return [
            'names' => $mine,
            'confirmed' => $confirmed,
            'seenByOthers' => $seenByOthers,
            'suggested' => array_values(array_unique(array_merge($mine, $confirmed, $seenByOthers))),
            'date' => $a->getTakenAt(),
            'reportId' => $a->getId(),
        ];
    }

    /** Les trois axes les plus faibles du dernier test Hygiène de Vie. */
    private function hygiene(User $user): ?array
    {
        $test = $this->em->getRepository(ProfileTest::class)->findOneBy(['user' => $user, 'type' => 'HYGIENE'], ['date' => 'DESC']);
        $report = $test ? $this->selfReports->build($test) : null;
        if (!$report) {
            return null;
        }

        return [
            'priorities' => array_values(array_map(fn ($a) => ['label' => $a['label'], 'value' => $a['value']], $report['summary']['priorities'])),
            'date' => $test->getDate(),
            'testId' => $test->getId(),
        ];
    }
}
