<?php

namespace App\PlanDeVie\Service;

use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prépare les données affichées par les pages des plans (Plan de Vie, hub des stratégies,
 * visualisations, emails) et par l'assistant de saisie.
 *
 * Extrait de l'ancien UserPlanController (phase 3) : le code de préparation est inchangé,
 * seul son emplacement l'est, pour être partagé par les contrôleurs de chaque module.
 */
final class PlanDataProvider
{
    /** Piliers des principes (« lois »), un par plan stratégique. */
    public const PILLARS = ['CROISSANCE', 'DEVELOPPEMENT', 'TRANSMISSION', 'HYGIENE'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getLifePlanData(User $user): array
    {
        $appConfig = $this->em->getRepository(\App\Admin\Entity\AppConfig::class)->find('default');
        $defaultPurposeStatement = $appConfig ? $appConfig->getDefaultPurposeStatement() : '';

        $planDeVie = $user->getPlanDeVie();
        $internalBalances = $user->getInternalBalances();
        $profileTests = $user->getProfileTests();
        $planDeCroissance = $user->getPlanDeCroissance();
        $planDeTransmission = $user->getPlanDeTransmission();
        $planDHygiene = $user->getPlanDHygiene();

        // Separate values by category
        $valuesReal = [];
        $valuesDeclared = [];
        $valuesCounter = [];
        if ($planDeVie) {
            foreach ($planDeVie->getValues() as $v) {
                if ($v->getCategory() === 'declared') {
                    $valuesDeclared[] = $v;
                } elseif ($v->getCategory() === 'counter') {
                    $valuesCounter[] = $v;
                } else {
                    $valuesReal[] = $v;
                }
            }
        }

        // Separate internal balances by type and subtype
        $convictionsDeep = [];
        $convictionsMedium = [];
        $convictionsLight = [];
        $limitingBeliefs = [];
        $talents = [];
        foreach ($internalBalances as $ib) {
            if ($ib->getType() === 'CONVICTION') {
                if ($ib->getSubtype() === 'medium') {
                    $convictionsMedium[] = $ib;
                } elseif ($ib->getSubtype() === 'light') {
                    $convictionsLight[] = $ib;
                } else {
                    $convictionsDeep[] = $ib;
                }
            } elseif ($ib->getType() === 'LIMITING_BELIEF') {
                $limitingBeliefs[] = $ib;
            } elseif ($ib->getType() === 'TALENT') {
                $talents[] = $ib;
            }
        }

        // Separate profile tests by type
        $mbti = null;
        $enneagram = null;
        foreach ($profileTests as $pt) {
            $val = $pt->getResult();
            if (str_starts_with($val, '{')) {
                try {
                    $parsed = json_decode($val, true);
                    if (is_array($parsed) && isset($parsed['type'])) {
                        $val = $parsed['type'];
                    }
                } catch (\Throwable) {}
            }
            $ptType = strtoupper($pt->getType());
            if ($ptType === 'MBTI') {
                $mbti = $val;
            } elseif ($ptType === 'ENNEAGRAM' || $ptType === 'ENNEAGRAMME') {
                $enneagram = $val;
            }
        }

        // Build latest tests dashboard array for all 8 test types
        $latestTests = [];
        $testTypesMeta = [
            'MBTI'          => ['name' => 'Type de fonctionnement', 'icon' => 'brain', 'color' => '#6366f1', 'bg' => '#eef2ff', 'url_key' => 'mbti'],
            'DISC'          => ['name' => 'Style comportemental', 'icon' => 'zap', 'color' => '#f43f5e', 'bg' => '#fff1f2', 'url_key' => 'disc'],
            'ENNEAGRAM'     => ['name' => 'Ennéagramme', 'icon' => 'target', 'color' => '#f59e0b', 'bg' => '#fffbeb', 'url_key' => 'enneagramme'],
            'BIG5'          => ['name' => 'Big Five', 'icon' => 'sparkles', 'color' => '#10b981', 'bg' => '#ecfdf5', 'url_key' => 'big5'],
            'DONS'          => ['name' => 'Dons spirituels', 'icon' => 'flame', 'color' => '#B45309', 'bg' => '#FFF7ED', 'url_key' => 'dons'],
            'EQUILIBRE'     => ['name' => 'Équilibre de Vie', 'icon' => 'activity', 'color' => '#3b82f6', 'bg' => '#eff6ff', 'url_key' => 'equilibre'],
            'HYGIENE'       => ['name' => 'Hygiène de Vie', 'icon' => 'heart', 'color' => '#ec4899', 'bg' => '#fdf2f8', 'url_key' => 'hygiene'],
        ];

        foreach ($testTypesMeta as $key => $meta) {
            $latestTests[$key] = [
                'completed' => false,
                'type' => $key,
                'result' => null,
                'insights' => null,
                'chartData' => [],
                'meta' => $meta,
            ];
        }

        foreach ($profileTests as $pt) {
            $type = strtoupper($pt->getType());
            $normType = ($type === 'ENNEAGRAMME') ? 'ENNEAGRAM' : $type;

            if (isset($latestTests[$normType])) {
                $ptDate = $pt->getDate();
                $existingDate = isset($latestTests[$normType]['date']) ? $latestTests[$normType]['date'] : null;
                if (!$existingDate || $ptDate > $existingDate) {
                    $resultVal = $pt->getResult();
                    $chartData = [];
                    $displayResult = $resultVal;
                    if (str_starts_with($resultVal, '{')) {
                        $parsed = json_decode($resultVal, true);
                        if (is_array($parsed)) {
                            if (isset($parsed['type'])) {
                                $displayResult = $parsed['type'];
                                if (isset($parsed['wing'])) {
                                    $displayResult .= ' w' . $parsed['wing'];
                                }
                            } else {
                                foreach ($parsed as $k => $v) {
                                    $label = match ($k) {
                                        'D' => 'Dominance (D)',
                                        'I' => 'Influence (I)',
                                        'S' => 'Stabilité (S)',
                                        'C' => 'Conformité (C)',
                                        'O' => 'Ouverture',
                                        'C_big5' => 'Conscienciosité',
                                        'E' => 'Extraversion',
                                        'A' => 'Agréabilité',
                                        'N' => 'Névrosisme',
                                        'linguistique' => 'Linguistique',
                                        'logique' => 'Logique',
                                        'spatiale' => 'Spatiale',
                                        'musicale' => 'Musicale',
                                        'kinesthesique' => 'Kinesthésique',
                                        'naturaliste' => 'Naturaliste',
                                        'interpersonnelle' => 'Interpersonnelle',
                                        'intrapersonnelle' => 'Intrapersonnelle',
                                        'spontane' => 'Don naturel / Spontané',
                                        'pression' => 'Réaction sous pression',
                                        'plaisir' => 'Zone de plaisir',
                                        'sante' => 'Santé',
                                        'famille' => 'Famille & Relations',
                                        'finances' => 'Finances',
                                        'carriere' => 'Carrière',
                                        'spiritualite' => 'Spiritualité',
                                        'social' => 'Social',
                                        'personnel' => 'Développement personnel',
                                        'loisirs' => 'Loisirs',
                                        'physique' => 'Physique',
                                        'mental' => 'Mental',
                                        'relationnel' => 'Relationnel',
                                        'spirituel' => 'Spirituel',
                                        'ressources' => 'Ressources',
                                        default => ucfirst($k),
                                    };
                                    $chartData[] = [
                                        'key' => $k,
                                        'label' => $label,
                                        'value' => (float)$v
                                    ];
                                }
                            }
                        }
                    }

                    $latestTests[$normType] = array_merge($latestTests[$normType], [
                        'completed' => true,
                        'result' => $displayResult,
                        'insights' => $pt->getInsights(),
                        'date' => $ptDate,
                        'chartData' => $chartData,
                    ]);
                }
            }
        }

        // Group life goals by category
        $lifeGoals = [
            'BE' => [],
            'DO' => [],
            'HAVE' => [],
            'GIVE' => []
        ];
        $timelineGoals = [];
        $allLifeGoals = [];
        if ($planDeVie) {
            $allLifeGoals = $planDeVie->getLifeGoals()->toArray();
            usort($allLifeGoals, function($a, $b) {
                return strcmp($a->getCategory(), $b->getCategory()) ?: strcmp($a->getTitle(), $b->getTitle());
            });
            foreach ($planDeVie->getLifeGoals() as $lg) {
                if (isset($lifeGoals[$lg->getCategory()])) {
                    $lifeGoals[$lg->getCategory()][] = $lg;
                }
                if ($lg->getDeadline()) {
                    $timelineGoals[] = $lg;
                }
            }
            // Sort timeline goals by deadline ascending
            usort($timelineGoals, function($a, $b) {
                return $a->getDeadline() <=> $b->getDeadline();
            });
        }

        $timelineGoalsGrouped = [];
        foreach ($timelineGoals as $lg) {
            $year = $lg->getDeadline()->format('Y');
            $timelineGoalsGrouped[$year][] = $lg;
        }

        // Group growth goals by axis
        $growthGoals = [
            'COMPETENCE' => [],
            'CHARACTER' => [],
            'RELATION' => []
        ];
        $competences = [
            'COMPETENCE' => [],
            'CONNAISSANCE' => []
        ];
        if ($planDeCroissance) {
            foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                $axis = $gg->getAxis();
                $axisStr = $axis instanceof \BackedEnum ? $axis->value : (string)$axis;
                if (isset($growthGoals[$axisStr])) {
                    $growthGoals[$axisStr][] = $gg;
                }
                if ($axis === \App\PlanDeCroissance\Enum\GrowthAxis::COMPETENCE) {
                    $type = $gg->getType() ?: 'COMPETENCE';
                    if (isset($competences[$type])) {
                        $competences[$type][] = $gg;
                    }
                }
            }
        }

        $growthGoalsBySource = [];
        if ($planDeCroissance) {
            foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                if ($gg->getSourceType() && $gg->getSourceId()) {
                    $growthGoalsBySource[$gg->getSourceType()][$gg->getSourceId()] = $gg;
                }
            }
        }

        $croissanceAxes = [
            'LIMITING_BELIEF' => [],
            'COUNTER_VALUE' => [],
            'DECLARED_VALUE' => [],
            'CONVICTION' => []
        ];

        foreach ($limitingBeliefs as $lb) {
            $goal = $growthGoalsBySource['LIMITING_BELIEF'][$lb->getId()] ?? null;
            $croissanceAxes['LIMITING_BELIEF'][] = [
                'id' => $lb->getId(),
                'text' => $lb->getText(),
                'goal' => $goal
            ];
        }

        foreach ($valuesCounter as $vc) {
            $goal = $growthGoalsBySource['COUNTER_VALUE'][$vc->getId()] ?? null;
            $croissanceAxes['COUNTER_VALUE'][] = [
                'id' => $vc->getId(),
                'text' => $vc->getName(),
                'goal' => $goal
            ];
        }

        foreach ($valuesDeclared as $vd) {
            $goal = $growthGoalsBySource['DECLARED_VALUE'][$vd->getId()] ?? null;
            $croissanceAxes['DECLARED_VALUE'][] = [
                'id' => $vd->getId(),
                'text' => $vd->getName(),
                'goal' => $goal
            ];
        }

        $allConvictions = array_merge($convictionsDeep, $convictionsMedium, $convictionsLight);
        foreach ($allConvictions as $conv) {
            $goal = $growthGoalsBySource['CONVICTION'][$conv->getId()] ?? null;
            $croissanceAxes['CONVICTION'][] = [
                'id' => $conv->getId(),
                'text' => $conv->getText(),
                'goal' => $goal
            ];
        }

        // Legacy goals
        $legacyGoals = $planDeTransmission ? $planDeTransmission->getLegacyGoals() : [];

        // Hygiene domains
        $hygieneDomainsList = $planDHygiene ? $planDHygiene->getHygieneDomains()->toArray() : [];
        $groupedHygieneDomains = [
            'Physique' => [],
            'Mental' => [],
            'Relationnel' => [],
            'Spirituel' => [],
            'Ressources' => [],
            'Environnement' => [],
            'Autre' => []
        ];
        foreach ($hygieneDomainsList as $hd) {
            $parent = $hd->getParentDomain();
            $groupedHygieneDomains[$parent][] = $hd;
        }

        $hygieneTest = $this->em->getRepository(\App\Personnalite\Entity\ProfileTest::class)->findOneBy(
            ['user' => $user, 'type' => 'HYGIENE'],
            ['date' => 'DESC']
        );
        $hygieneTestScores = null;
        if ($hygieneTest) {
            $hygieneTestScores = json_decode($hygieneTest->getResult(), true);
        }

        return [
            'planDeVie' => $planDeVie,
            'valuesReal' => $valuesReal,
            'valuesDeclared' => $valuesDeclared,
            'valuesCounter' => $valuesCounter,
            'convictionsDeep' => $convictionsDeep,
            'convictionsMedium' => $convictionsMedium,
            'convictionsLight' => $convictionsLight,
            'limitingBeliefs' => $limitingBeliefs,
            'talents' => $talents,
            'mbti' => $mbti,
            'enneagram' => $enneagram,
            'latestTests' => $latestTests,
            'lifeGoals' => $lifeGoals,
            'allLifeGoals' => $allLifeGoals,
            'timelineGoals' => $timelineGoals,
            'timelineGoalsGrouped' => $timelineGoalsGrouped,
            'growthGoals' => $growthGoals,
            'croissanceAxes' => $croissanceAxes,
            'competences' => $competences,
            'legacyGoals' => $legacyGoals,
            'hygieneDomains' => $hygieneDomainsList,
            'groupedHygieneDomains' => $groupedHygieneDomains,
            'hygieneTest' => $hygieneTest,
            'hygieneTestScores' => $hygieneTestScores,
            'defaultPurposeStatement' => $defaultPurposeStatement,
        ];
    }

    /** Données du Plan de Vie et principes regroupés par pilier (hub, visualisations des stratégies). */
    public function getStrategiesData(User $user): array
    {
        $data = $this->getLifePlanData($user);
        $data['growthPrinciples'] = array_fill_keys(self::PILLARS, []);
        foreach ($user->getPrinciples() as $pr) {
            if (isset($data['growthPrinciples'][$pr->getPillar()])) {
                $data['growthPrinciples'][$pr->getPillar()][] = $pr;
            }
        }

        return $data;
    }

    /** Données pré-remplies de l'assistant de saisie. */
    public function getWizardData(User $user): array
    {
        $planDeVie = $user->getPlanDeVie();
        // Fetch user internal balances and profile tests to pre-populate
        $internalBalances = $user->getInternalBalances();
        $profileTests = $user->getProfileTests();
        $planDeCroissance = $user->getPlanDeCroissance();
        $planDeTransmission = $user->getPlanDeTransmission();
        $planDHygiene = $user->getPlanDHygiene();

        // Separate values by category
        $valuesReal = [];
        $valuesDeclared = [];
        $valuesCounter = [];
        if ($planDeVie) {
            foreach ($planDeVie->getValues() as $v) {
                if ($v->getCategory() === 'declared') {
                    $valuesDeclared[] = $v;
                } elseif ($v->getCategory() === 'counter') {
                    $valuesCounter[] = $v;
                } else {
                    $valuesReal[] = $v;
                }
            }
        }

        // Separate internal balances by type and subtype
        $convictionsDeep = [];
        $convictionsMedium = [];
        $convictionsLight = [];
        $limitingBeliefs = [];
        $talents = [];
        foreach ($internalBalances as $ib) {
            if ($ib->getType() === 'CONVICTION') {
                if ($ib->getSubtype() === 'medium') {
                    $convictionsMedium[] = $ib;
                } elseif ($ib->getSubtype() === 'light') {
                    $convictionsLight[] = $ib;
                } else {
                    $convictionsDeep[] = $ib;
                }
            } elseif ($ib->getType() === 'LIMITING_BELIEF') {
                $limitingBeliefs[] = $ib;
            } elseif ($ib->getType() === 'TALENT') {
                $talents[] = $ib;
            }
        }

        // Separate profile tests by type
        $mbtiVal = null;
        $discVal = null;
        $enneagramVal = null;
        $big5Val = null;
        foreach ($profileTests as $pt) {
            $val = $pt->getResult();
            if (str_starts_with($val, '{')) {
                try {
                    $parsed = json_decode($val, true);
                    if (is_array($parsed) && isset($parsed['type'])) {
                        $val = $parsed['type'];
                    }
                } catch (\Throwable) {}
            }
            $ptType = strtoupper($pt->getType());
            if ($ptType === 'MBTI') {
                $mbtiVal = $val;
            } elseif ($ptType === 'DISC') {
                $discVal = $val;
            } elseif ($ptType === 'ENNEAGRAM' || $ptType === 'ENNEAGRAMME') {
                $enneagramVal = $val;
            } elseif ($ptType === 'BIG5') {
                $big5Val = $val;
            }
        }

        // Life goals
        $lifeGoals = [
            'BE' => [],
            'DO' => [],
            'HAVE' => [],
            'GIVE' => []
        ];
        foreach ($planDeVie->getLifeGoals() as $lg) {
            if (isset($lifeGoals[$lg->getCategory()])) {
                $lifeGoals[$lg->getCategory()][] = $lg;
            }
        }

        // Growth goals
        $growthGoals = [
            'COMPETENCE' => [],
            'CHARACTER' => [],
            'RELATION' => []
        ];
        $competences = [
            'COMPETENCE' => [],
            'CONNAISSANCE' => []
        ];
        if ($planDeCroissance) {
            foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                $axis = $gg->getAxis();
                $axisStr = $axis instanceof \BackedEnum ? $axis->value : (string)$axis;
                if (isset($growthGoals[$axisStr])) {
                    $growthGoals[$axisStr][] = $gg;
                }
                if ($axis === \App\PlanDeCroissance\Enum\GrowthAxis::COMPETENCE) {
                    $type = $gg->getType() ?: 'COMPETENCE';
                    if (isset($competences[$type])) {
                        $competences[$type][] = $gg;
                    }
                }
            }
        }

        // Legacy goals
        $legacyGoals = $planDeTransmission ? $planDeTransmission->getLegacyGoals() : [];

        // Hygiene domains & Test results integration
        $hygieneTest = $this->em->getRepository(\App\Personnalite\Entity\ProfileTest::class)->findOneBy(
            ['user' => $user, 'type' => 'HYGIENE'],
            ['date' => 'DESC']
        );
        $hygieneTestScores = null;
        if ($hygieneTest) {
            $hygieneTestScores = json_decode($hygieneTest->getResult(), true);
        }

        $hygieneDomains = $planDHygiene ? $planDHygiene->getHygieneDomains()->toArray() : [];
        if (empty($hygieneDomains) && $hygieneTestScores && isset($hygieneTestScores['details'])) {
            foreach ($hygieneTestScores['details'] as $axisKey => $subthemes) {
                if (is_array($subthemes)) {
                    foreach ($subthemes as $subthemeName => $score) {
                        $score = (float)$score;
                        // Pre-populate if it needs work (score < 7.0)
                        if ($score < 7.0) {
                            if ($score >= 4.0) {
                                $subtype = 'consolidate';
                            } else {
                                $subtype = 'build';
                            }
                            $hd = new \App\PlanDHygiene\Entity\HygieneDomain();
                            $hd->setName($subthemeName);
                            $hd->setSubtype($subtype);
                            $hygieneDomains[] = $hd;
                        }
                    }
                }
            }
        }

        // Sort life goals to prioritize GIVE first
        $allLifeGoals = $planDeVie ? $planDeVie->getLifeGoals()->toArray() : [];
        usort($allLifeGoals, function($a, $b) {
            $aGive = (strtoupper($a->getCategory()) === 'GIVE') ? 1 : 0;
            $bGive = (strtoupper($b->getCategory()) === 'GIVE') ? 1 : 0;
            if ($aGive !== $bGive) {
                return $bGive <=> $aGive;
            }
            return strcmp($a->getTitle(), $b->getTitle());
        });

        $appConfig = $this->em->getRepository(\App\Admin\Entity\AppConfig::class)->find('default');
        $defaultPurposeStatement = $appConfig ? $appConfig->getDefaultPurposeStatement() : '';

        return [
            'planDeVie' => $planDeVie,
            'valuesReal' => $valuesReal,
            'valuesDeclared' => $valuesDeclared,
            'valuesCounter' => $valuesCounter,
            'convictionsDeep' => $convictionsDeep,
            'convictionsMedium' => $convictionsMedium,
            'convictionsLight' => $convictionsLight,
            'limitingBeliefs' => $limitingBeliefs,
            'talents' => $talents,
            'mbtiVal' => $mbtiVal,
            'discVal' => $discVal,
            'enneagramVal' => $enneagramVal,
            'big5Val' => $big5Val,
            'lifeGoals' => $lifeGoals,
            'allLifeGoals' => $allLifeGoals,
            'growthGoals' => $growthGoals,
            'competences' => $competences,
            'legacyGoals' => $legacyGoals,
            'hygieneDomains' => $hygieneDomains,
            'hygieneTest' => $hygieneTest,
            'hygieneTestScores' => $hygieneTestScores,
            'defaultPurposeStatement' => $defaultPurposeStatement,
        ];
    }
}
