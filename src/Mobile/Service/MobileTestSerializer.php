<?php

namespace App\Mobile\Service;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Personnalite\Entity\Assessment;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\BiblicalFigures;
use App\Personnalite\Psychometrie\DiscBank;
use App\Personnalite\Psychometrie\Instruments;
use App\Personnalite\Psychometrie\IpipItemBank;
use App\Personnalite\Psychometrie\ReportBuilder;
use App\Personnalite\Repository\AssessmentRepository;
use App\Personnalite\Repository\PersonalityQuestionRepository;
use App\Personnalite\Repository\ProfileTestRepository;
use App\Personnalite\Service\ProfileSynthesis;
use App\Personnalite\Service\SelfAssessmentProfiles;
use App\Personnalite\Service\SelfAssessmentReport;

/**
 * Sérialiseur officiel pour « Me connaître » : Mes tests et Mon profil KIINI SAWA.
 * Conforme au lexique du livre (zéro terme banni, zéro émoji, vouvoiement).
 */
final class MobileTestSerializer
{
    private const AUTO_TESTS = [
        'equilibre' => [
            'name' => 'Équilibre de Vie',
            'subtitle' => 'Roue de la vie',
            'description' => 'Mesurez vos 8 domaines de vie et identifiez les zones à rééquilibrer.',
            'color' => '#3B82F6',
            'bg' => '#EFF6FF',
            'icon' => 'activity',
            'level' => 'Auto-évaluation',
            'minutes' => 6,
        ],
        'hygiene' => [
            'name' => 'Hygiène de Vie',
            'subtitle' => '6 Axes Vitaux',
            'description' => 'Audit en 6 axes (physique, mental, relationnel, spirituel, ressources, environnement).',
            'color' => '#EC4899',
            'bg' => '#FDF2F8',
            'icon' => 'heart',
            'level' => 'Auto-évaluation',
            'minutes' => 10,
        ],
    ];

    public function __construct(
        private readonly AssessmentRepository $assessments,
        private readonly ProfileTestRepository $profileTests,
        private readonly PersonalityQuestionRepository $questions,
        private readonly Entitlements $entitlements,
        private readonly ReportBuilder $reportBuilder,
        private readonly SelfAssessmentReport $selfReportBuilder,
        private readonly ProfileSynthesis $profileSynthesis,
    ) {
    }

    /**
     * Catalogue complet des tests avec état d'avancement pour l'utilisateur.
     *
     * @return array<string, mixed>
     */
    public function serializeCatalog(User $user): array
    {
        $userAssessments = $this->assessments->findForUser($user);
        $latestAssessments = [];
        foreach ($userAssessments as $a) {
            $latestAssessments[$a->getInstrument()] ??= $a;
        }

        $userProfileTests = $this->profileTests->findBy(['user' => $user], ['date' => 'DESC', 'id' => 'DESC']);
        $latestProfileTests = [];
        foreach ($userProfileTests as $pt) {
            $key = strtolower($pt->getType() ?? '');
            $latestProfileTests[$key] ??= $pt;
        }

        $groups = [];

        // Groupe 1 : Personnalité
        $personalityTests = [];
        foreach (['disc', 'profil-complet', 'big-five', 'type'] as $slug) {
            $personalityTests[] = $this->formatPsychometricItem($slug, $latestAssessments[$slug] ?? null, $user);
        }
        $groups[] = [
            'key' => 'personnalite',
            'label' => 'Personnalité',
            'tests' => $personalityTests,
        ];

        // Groupe 2 : Motivations et dons
        $motivationTests = [];
        foreach (['enneagramme', 'dons-spirituels'] as $slug) {
            $motivationTests[] = $this->formatPsychometricItem($slug, $latestAssessments[$slug] ?? null, $user);
        }
        $groups[] = [
            'key' => 'motivations_et_dons',
            'label' => 'Motivations et dons',
            'tests' => $motivationTests,
        ];

        // Groupe 3 : Auto-évaluations
        $autoTests = [];
        foreach (self::AUTO_TESTS as $slug => $meta) {
            $pt = $latestProfileTests[$slug] ?? null;
            $autoTests[] = [
                'slug' => $slug,
                'name' => $meta['name'],
                'subtitle' => $meta['subtitle'],
                'description' => $meta['description'],
                'minutes' => $meta['minutes'],
                'icon' => $meta['icon'],
                'color' => $meta['color'],
                'bg' => $meta['bg'],
                'level' => $meta['level'],
                'questionCount' => 'equilibre' === $slug ? 8 : 30,
                'isTaken' => null !== $pt,
                'lastTakenAt' => $pt?->getDate()?->format(\DateTimeInterface::ATOM),
                'lastAssessmentId' => $pt?->getId(),
                'resultHeadline' => $pt ? 'Complété le '.$pt->getDate()->format('d/m/Y') : null,
                'locked' => false,
            ];
        }
        $groups[] = [
            'key' => 'auto_evaluations',
            'label' => 'Auto-évaluations',
            'tests' => $autoTests,
        ];

        $completedCount = \count($latestAssessments);
        $isPro = $this->entitlements->isPro($user);

        return [
            'groups' => $groups,
            'synthesisSummary' => [
                'completedCount' => $completedCount,
                'enoughForSynthesis' => $completedCount >= 2,
                'locked' => !$isPro,
            ],
        ];
    }

    /**
     * Questionnaire à passer avec ses questions et son échelle de réponses.
     *
     * @return array<string, mixed>
     */
    public function serializeQuestions(User $user, string $slug): array
    {
        $slug = strtolower($slug);

        if (isset(self::AUTO_TESTS[$slug])) {
            return $this->serializeAutoEvaluationQuestions($slug);
        }

        if (!Instruments::exists($slug)) {
            throw new \InvalidArgumentException(sprintf('Test inconnu : %s', $slug));
        }

        $this->entitlements->requireTest($user, $slug);

        $def = Instruments::CATALOG[$slug];
        $rawItems = Instruments::items($slug);
        $bank = $def['bank'];

        $formattedItems = [];
        if ('disc' === $bank) {
            foreach ($rawItems as $it) {
                $formattedItems[] = [
                    'key' => $it['key'],
                    'type' => 'disc_block',
                    'text' => $it['text'],
                    'words' => $it['words'] ?? [],
                ];
            }
            $scale = null;
        } else {
            foreach ($rawItems as $it) {
                $formattedItems[] = [
                    'key' => $it['key'],
                    'type' => 'scale',
                    'text' => $it['text'],
                ];
            }
            $scale = IpipItemBank::SCALE;
        }

        return [
            'slug' => $slug,
            'name' => $def['name'],
            'subtitle' => $def['subtitle'],
            'description' => $def['description'],
            'minutes' => $def['minutes'],
            'bank' => $bank,
            'level' => $def['level'],
            'scale' => $scale,
            'items' => $formattedItems,
            'totalCount' => \count($formattedItems),
        ];
    }

    /**
     * Rapport complet d'un test psychométrique.
     *
     * @return array<string, mixed>
     */
    public function serializePsychometricReport(Assessment $assessment): array
    {
        $reportData = $this->reportBuilder->build($assessment);
        $slug = $assessment->getInstrument();
        $scores = $assessment->getScores();

        $headline = $this->extractHeadline($slug, $scores, $reportData);

        return [
            'id' => $assessment->getId(),
            'slug' => $slug,
            'takenAt' => $assessment->getTakenAt()->format(\DateTimeInterface::ATOM),
            'headline' => $headline,
            'instrument' => [
                'name' => Instruments::CATALOG[$slug]['name'] ?? $slug,
                'subtitle' => Instruments::CATALOG[$slug]['subtitle'] ?? '',
                'color' => Instruments::CATALOG[$slug]['color'] ?? '#2E6B53',
                'bg' => Instruments::CATALOG[$slug]['bg'] ?? '#EEF3EF',
                'level' => Instruments::CATALOG[$slug]['level'] ?? '',
            ],
            'scores' => $scores,
            'details' => $this->sanitizeReportDetails($slug, $reportData),
        ];
    }

    /**
     * Rapport complet d'une auto-évaluation (ProfileTest).
     *
     * @return array<string, mixed>
     */
    public function serializeAutoEvaluationReport(ProfileTest $profileTest): array
    {
        $slug = strtolower($profileTest->getType() ?? '');
        $data = $this->selfReportBuilder->build($profileTest);

        return [
            'id' => $profileTest->getId(),
            'slug' => $slug,
            'takenAt' => $profileTest->getDate()?->format(\DateTimeInterface::ATOM),
            'headline' => self::AUTO_TESTS[$slug]['name'] ?? $slug,
            'instrument' => self::AUTO_TESTS[$slug] ?? [
                'name' => ucfirst($slug),
                'subtitle' => '',
                'color' => '#3B82F6',
                'bg' => '#EFF6FF',
                'level' => 'Auto-évaluation',
            ],
            'domains' => $data['domains'] ?? [],
            'insights' => $profileTest->getInsights(),
        ];
    }

    /**
     * Synthèse globale « Mon profil KIINI SAWA ».
     *
     * @return array<string, mixed>
     */
    public function serializeSynthesis(User $user): array
    {
        $data = $this->profileSynthesis->build($user);
        $isPro = $this->entitlements->isPro($user);

        if (!$isPro) {
            return [
                'locked' => true,
                'enough' => $data['enough'] ?? false,
                'count' => $data['count'] ?? 0,
                'done' => $data['done'] ?? [],
                'missing' => $data['missing'] ?? [],
                'previewNotice' => 'La synthèse croisée « Mon profil KIINI SAWA » révèle vos convergences et votre zone de vocation à partir de l’ensemble de vos tests.',
            ];
        }

        return [
            'locked' => false,
            'enough' => $data['enough'] ?? false,
            'count' => $data['count'] ?? 0,
            'done' => $data['done'] ?? [],
            'missing' => $data['missing'] ?? [],
            'identity' => $data['identity'] ?? null,
            'convergence' => $data['convergence'] ?? null,
            'vocation' => $data['vocation'] ?? null,
            'fragile' => $data['fragile'] ?? null,
            'resources' => $data['resources'] ?? null,
            'priorities' => $data['priorities'] ?? null,
            'life' => $data['life'] ?? null,
            'insights' => $data['insights'] ?? [],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Méthodes privées d'aide
    // ─────────────────────────────────────────────────────────────────────────

    private function formatPsychometricItem(string $slug, ?Assessment $a, User $user): array
    {
        $def = Instruments::CATALOG[$slug];
        $headline = null;
        if ($a) {
            $headline = $this->extractHeadline($slug, $a->getScores());
        }

        return [
            'slug' => $slug,
            'name' => $def['name'],
            'subtitle' => $def['subtitle'],
            'description' => $def['description'],
            'minutes' => $def['minutes'],
            'icon' => $def['icon'],
            'color' => $def['color'],
            'bg' => $def['bg'],
            'level' => $def['level'],
            'questionCount' => \count(Instruments::items($slug)),
            'isTaken' => null !== $a,
            'lastTakenAt' => $a?->getTakenAt()->format(\DateTimeInterface::ATOM),
            'lastAssessmentId' => $a?->getId(),
            'resultHeadline' => $headline,
            'locked' => !$this->entitlements->canTakeTest($user, $slug),
        ];
    }

    private function serializeAutoEvaluationQuestions(string $slug): array
    {
        $meta = self::AUTO_TESTS[$slug];
        $items = [];

        if ('equilibre' === $slug) {
            foreach (SelfAssessmentProfiles::EQUILIBRE as $key => $dom) {
                $items[] = [
                    'key' => 'equilibre:'.$key,
                    'type' => 'scale_10',
                    'category' => $dom['label'],
                    'text' => $dom['what'],
                    'question' => $dom['question'],
                ];
            }
        } else {
            $questions = $this->questions->findBy(['testType' => 'HYGIENE'], ['sortOrder' => 'ASC']);
            foreach ($questions as $q) {
                $items[] = [
                    'key' => 'hygiene:'.$q->getId(),
                    'type' => 'scale_10',
                    'category' => $q->getCategory(),
                    'text' => $q->getQuestion(),
                ];
            }
        }

        return [
            'slug' => $slug,
            'name' => $meta['name'],
            'subtitle' => $meta['subtitle'],
            'description' => $meta['description'],
            'minutes' => $meta['minutes'],
            'bank' => 'self',
            'level' => $meta['level'],
            'scale' => [
                '1' => '1 - Très faible',
                '5' => '5 - Moyen',
                '10' => '10 - Épanoui / Optimal',
            ],
            'items' => $items,
            'totalCount' => \count($items),
        ];
    }

    private function extractHeadline(string $slug, array $scores, array $reportData = []): string
    {
        return match ($slug) {
            'disc' => isset($scores['primary'])
                ? sprintf('Profil %s%s', $scores['primary'], isset($scores['secondary']) ? '/'.$scores['secondary'] : '')
                : 'DISC',
            'type' => isset($scores['type']['code'])
                ? sprintf('Type %s (%s)', $scores['type']['code'], $reportData['type']['name'] ?? '')
                : 'Type de fonctionnement',
            'enneagramme' => isset($scores['primary'])
                ? sprintf('Type %d%s', $scores['primary'], isset($scores['wing']) ? 'w'.$scores['wing'] : '')
                : 'Ennéagramme',
            'dons-spirituels' => isset($scores['ranking'][0])
                ? sprintf('Don principal : %s', $scores['ranking'][0]['name'] ?? 'Identifié')
                : 'Dons spirituels',
            'profil-complet', 'big-five' => $reportData['bf']['headline'] ?? 'Big Five',
            default => 'Complété',
        };
    }

    private function sanitizeReportDetails(string $slug, array $data): array
    {
        // Nettoie les données pour la consommation mobile JSON (retire les références Twig)
        unset($data['template'], $data['instrument'], $data['assessment']);

        if ('disc' === $slug && isset($data['disc'])) {
            return [
                'natural' => $data['disc']['natural'] ?? [],
                'adapted' => $data['disc']['adapted'] ?? [],
                'shifts' => $data['disc']['shifts'] ?? [],
                'primary' => $data['disc']['primary'] ?? '',
                'secondary' => $data['disc']['secondary'] ?? null,
                'profile' => $data['disc']['profile'] ?? [],
                'combo' => $data['disc']['combo'] ?? [],
                'intensity' => $data['disc']['intensity'] ?? [],
                'verse' => $data['disc']['verse'] ?? null,
                'meditation' => $data['disc']['meditation'] ?? null,
                'figures' => $data['disc']['combo']['figures'] ?? [],
            ];
        }

        if ('type' === $slug && isset($data['type'])) {
            return [
                'type' => $data['type'],
                'typeX' => $data['typeX'] ?? [],
            ];
        }

        if ('enneagramme' === $slug) {
            return [
                'primary' => $data['primary'] ?? null,
                'wing' => $data['wing'] ?? null,
                'type' => $data['type'] ?? [],
                'wingProfile' => $data['wingProfile'] ?? null,
                'center' => $data['center'] ?? [],
                'growth' => $data['growth'] ?? [],
                'stress' => $data['stress'] ?? [],
            ];
        }

        if ('dons-spirituels' === $slug) {
            return [
                'ranking' => $data['ranking'] ?? [],
                'top' => \array_slice($data['ranking'] ?? [], 0, 3),
                'verses' => $data['verses'] ?? [],
            ];
        }

        if (\in_array($slug, ['profil-complet', 'big-five'], true)) {
            return [
                'domains' => $data['domains'] ?? [],
                'strengths' => $data['strengths'] ?? [],
                'vigilance' => $data['vigilance'] ?? [],
                'type' => $data['type'] ?? null,
            ];
        }

        return $data;
    }
}
