<?php

namespace App\PlanDeVie\Controller;

use App\Rituels\Entity\Habit;
use App\Rituels\Form\HabitType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserPlanController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }


    private function getLifePlanData(EntityManagerInterface $em): array
    {
        $user = $this->getUser();
        if (!$user) {
            return [];
        }

        $appConfig = $em->getRepository(\App\Admin\Entity\AppConfig::class)->find('default');
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
            'MBTI'          => ['name' => 'MBTI', 'icon' => 'brain', 'color' => '#6366f1', 'bg' => '#eef2ff', 'url_key' => 'mbti'],
            'DISC'          => ['name' => 'DISC', 'icon' => 'zap', 'color' => '#f43f5e', 'bg' => '#fff1f2', 'url_key' => 'disc'],
            'ENNEAGRAM'     => ['name' => 'Ennéagramme', 'icon' => 'target', 'color' => '#f59e0b', 'bg' => '#fffbeb', 'url_key' => 'enneagramme'],
            'BIG5'          => ['name' => 'Big Five', 'icon' => 'sparkles', 'color' => '#10b981', 'bg' => '#ecfdf5', 'url_key' => 'big5'],
            'INTELLIGENCES' => ['name' => 'Intelligences Multiples', 'icon' => 'compass', 'color' => '#06b6d4', 'bg' => '#ecfeff', 'url_key' => 'intelligences'],
            'TALENT'        => ['name' => 'Architecture des Talents', 'icon' => 'star', 'color' => '#f97316', 'bg' => '#fff7ed', 'url_key' => 'talent'],
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

        $hygieneTest = $em->getRepository(\App\Personnalite\Entity\ProfileTest::class)->findOneBy(
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

    #[Route('/life-plan', name: 'app_user_life_plan')]
    public function lifePlan(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $data = $this->getLifePlanData($em);

        return $this->render('plan_de_vie/index.html.twig', $data);
    }

    #[Route('/mes-plans/vie/visualiser', name: 'app_user_life_plan_visualize')]
    public function lifePlanVisualize(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $data = $this->getLifePlanData($em);
        $data['variant'] = 'WEB';

        return $this->render('plan_de_vie/visualize.html.twig', $data);
    }

    #[Route('/mes-plans/vie/email', name: 'app_user_life_plan_send_email', methods: ['POST'])]
    public function lifePlanSendEmail(EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Utilisateur non connecté.'], 401);
        }

        try {
            $data = $this->getLifePlanData($em);
            $data['variant'] = 'EMAIL';

            // Rendre le template Twig en chaîne de caractères
            $htmlContent = $this->renderView('plan_de_vie/visualize.html.twig', $data);

            // Construire l'e-mail
            $email = (new Email())
                ->from('noreply@kiinisawa.com')
                ->to($user->getEmail())
                ->subject('Votre Plan de Vie — KIINI SAWA')
                ->html($htmlContent);

            $mailer->send($email);

            return new JsonResponse(['success' => true, 'emailSentTo' => $user->getEmail()]);
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'envoi du plan par email', ['exception' => $e]);

            return new JsonResponse(['success' => false, 'error' => 'L\'email n\'a pas pu être envoyé. Réessayez dans quelques minutes.'], 500);
        }
    }


    #[Route('/mes-strategies/croissance/visualiser', name: 'app_user_croissance_plan_visualize')]
    public function croissancePlanVisualize(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $data = $this->getLifePlanData($em);

        // Group principles by pillar for strategies view data structure
        $principles = $user->getPrinciples();
        $growthPrinciples = [
            'CROISSANCE' => [],
            'DEVELOPPEMENT' => [],
            'CONTRIBUTION' => [],
            'DISCIPLINE' => [],
        ];
        foreach ($principles as $pr) {
            $pillar = $pr->getPillar();
            if ($pillar && isset($growthPrinciples[$pillar])) {
                $growthPrinciples[$pillar][] = $pr;
            }
        }
        $data['growthPrinciples'] = $growthPrinciples;
        $data['variant'] = 'WEB';

        return $this->render('plan_de_croissance/visualize.html.twig', $data);
    }

    #[Route('/mes-strategies/croissance/email', name: 'app_user_croissance_plan_send_email', methods: ['POST'])]
    public function croissancePlanSendEmail(EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Utilisateur non connecté.'], 401);
        }

        try {
            $data = $this->getLifePlanData($em);

            // Group principles by pillar
            $principles = $user->getPrinciples();
            $growthPrinciples = [
                'CROISSANCE' => [],
                'DEVELOPPEMENT' => [],
                'CONTRIBUTION' => [],
                'DISCIPLINE' => [],
            ];
            foreach ($principles as $pr) {
                $pillar = $pr->getPillar();
                if ($pillar && isset($growthPrinciples[$pillar])) {
                    $growthPrinciples[$pillar][] = $pr;
                }
            }
            $data['growthPrinciples'] = $growthPrinciples;
            $data['variant'] = 'EMAIL';

            // Rendre le template Twig en chaîne de caractères
            $htmlContent = $this->renderView('plan_de_croissance/visualize.html.twig', $data);

            // Construire l'e-mail
            $email = (new Email())
                ->from('noreply@kiinisawa.com')
                ->to($user->getEmail())
                ->subject('Votre Plan de Croissance — KIINI SAWA')
                ->html($htmlContent);

            $mailer->send($email);

            return new JsonResponse(['success' => true, 'emailSentTo' => $user->getEmail()]);
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'envoi du plan par email', ['exception' => $e]);

            return new JsonResponse(['success' => false, 'error' => 'L\'email n\'a pas pu être envoyé. Réessayez dans quelques minutes.'], 500);
        }
    }


    #[Route('/mes-strategies/developpement/visualiser', name: 'app_user_developpement_plan_visualize')]
    public function developpementPlanVisualize(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $data = $this->getLifePlanData($em);

        // Group principles by pillar
        $principles = $user->getPrinciples();
        $growthPrinciples = [
            'CROISSANCE' => [],
            'DEVELOPPEMENT' => [],
            'CONTRIBUTION' => [],
            'DISCIPLINE' => [],
        ];
        foreach ($principles as $pr) {
            $pillar = $pr->getPillar();
            if ($pillar && isset($growthPrinciples[$pillar])) {
                $growthPrinciples[$pillar][] = $pr;
            }
        }
        $data['growthPrinciples'] = $growthPrinciples;
        $data['variant'] = 'WEB';

        return $this->render('plan_de_developpement/visualize.html.twig', $data);
    }

    #[Route('/mes-strategies/developpement/email', name: 'app_user_developpement_plan_send_email', methods: ['POST'])]
    public function developpementPlanSendEmail(EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Utilisateur non connecté.'], 401);
        }

        try {
            $data = $this->getLifePlanData($em);

            // Group principles by pillar
            $principles = $user->getPrinciples();
            $growthPrinciples = [
                'CROISSANCE' => [],
                'DEVELOPPEMENT' => [],
                'CONTRIBUTION' => [],
                'DISCIPLINE' => [],
            ];
            foreach ($principles as $pr) {
                $pillar = $pr->getPillar();
                if ($pillar && isset($growthPrinciples[$pillar])) {
                    $growthPrinciples[$pillar][] = $pr;
                }
            }
            $data['growthPrinciples'] = $growthPrinciples;
            $data['variant'] = 'EMAIL';

            $htmlContent = $this->renderView('plan_de_developpement/visualize.html.twig', $data);

            $email = (new Email())
                ->from('noreply@kiinisawa.com')
                ->to($user->getEmail())
                ->subject('Votre Plan de Développement — KIINI SAWA')
                ->html($htmlContent);

            $mailer->send($email);

            return new JsonResponse(['success' => true, 'emailSentTo' => $user->getEmail()]);
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'envoi du plan par email', ['exception' => $e]);

            return new JsonResponse(['success' => false, 'error' => 'L\'email n\'a pas pu être envoyé. Réessayez dans quelques minutes.'], 500);
        }
    }

    #[Route('/mes-strategies/contribution/visualiser', name: 'app_user_contribution_plan_visualize')]
    public function contributionPlanVisualize(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $data = $this->getLifePlanData($em);

        // Group principles by pillar
        $principles = $user->getPrinciples();
        $growthPrinciples = [
            'CROISSANCE' => [],
            'DEVELOPPEMENT' => [],
            'CONTRIBUTION' => [],
            'DISCIPLINE' => [],
        ];
        foreach ($principles as $pr) {
            $pillar = $pr->getPillar();
            if ($pillar && isset($growthPrinciples[$pillar])) {
                $growthPrinciples[$pillar][] = $pr;
            }
        }
        $data['growthPrinciples'] = $growthPrinciples;
        $data['variant'] = 'WEB';

        return $this->render('plan_de_transmission/visualize.html.twig', $data);
    }

    #[Route('/mes-strategies/contribution/email', name: 'app_user_contribution_plan_send_email', methods: ['POST'])]
    public function contributionPlanSendEmail(EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Utilisateur non connecté.'], 401);
        }

        try {
            $data = $this->getLifePlanData($em);

            // Group principles by pillar
            $principles = $user->getPrinciples();
            $growthPrinciples = [
                'CROISSANCE' => [],
                'DEVELOPPEMENT' => [],
                'CONTRIBUTION' => [],
                'DISCIPLINE' => [],
            ];
            foreach ($principles as $pr) {
                $pillar = $pr->getPillar();
                if ($pillar && isset($growthPrinciples[$pillar])) {
                    $growthPrinciples[$pillar][] = $pr;
                }
            }
            $data['growthPrinciples'] = $growthPrinciples;
            $data['variant'] = 'EMAIL';

            $htmlContent = $this->renderView('plan_de_transmission/visualize.html.twig', $data);

            $email = (new Email())
                ->from('noreply@kiinisawa.com')
                ->to($user->getEmail())
                ->subject('Votre Plan de Transmission — KIINI SAWA')
                ->html($htmlContent);

            $mailer->send($email);

            return new JsonResponse(['success' => true, 'emailSentTo' => $user->getEmail()]);
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'envoi du plan par email', ['exception' => $e]);

            return new JsonResponse(['success' => false, 'error' => 'L\'email n\'a pas pu être envoyé. Réessayez dans quelques minutes.'], 500);
        }
    }

    #[Route('/mes-strategies/discipline/visualiser', name: 'app_user_discipline_plan_visualize')]
    public function disciplinePlanVisualize(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $data = $this->getLifePlanData($em);

        // Group principles by pillar
        $principles = $user->getPrinciples();
        $growthPrinciples = [
            'CROISSANCE' => [],
            'DEVELOPPEMENT' => [],
            'CONTRIBUTION' => [],
            'DISCIPLINE' => [],
        ];
        foreach ($principles as $pr) {
            $pillar = $pr->getPillar();
            if ($pillar && isset($growthPrinciples[$pillar])) {
                $growthPrinciples[$pillar][] = $pr;
            }
        }
        $data['growthPrinciples'] = $growthPrinciples;
        $data['variant'] = 'WEB';

        return $this->render('plan_dhygiene/visualize.html.twig', $data);
    }

    #[Route('/mes-strategies/discipline/email', name: 'app_user_discipline_plan_send_email', methods: ['POST'])]
    public function disciplinePlanSendEmail(EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Utilisateur non connecté.'], 401);
        }

        try {
            $data = $this->getLifePlanData($em);

            // Group principles by pillar
            $principles = $user->getPrinciples();
            $growthPrinciples = [
                'CROISSANCE' => [],
                'DEVELOPPEMENT' => [],
                'CONTRIBUTION' => [],
                'DISCIPLINE' => [],
            ];
            foreach ($principles as $pr) {
                $pillar = $pr->getPillar();
                if ($pillar && isset($growthPrinciples[$pillar])) {
                    $growthPrinciples[$pillar][] = $pr;
                }
            }
            $data['growthPrinciples'] = $growthPrinciples;
            $data['variant'] = 'EMAIL';

            $htmlContent = $this->renderView('plan_dhygiene/visualize.html.twig', $data);

            $email = (new Email())
                ->from('noreply@kiinisawa.com')
                ->to($user->getEmail())
                ->subject('Votre Plan d\'Hygiène de Vie — KIINI SAWA')
                ->html($htmlContent);

            $mailer->send($email);

            return new JsonResponse(['success' => true, 'emailSentTo' => $user->getEmail()]);
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'envoi du plan par email', ['exception' => $e]);

            return new JsonResponse(['success' => false, 'error' => 'L\'email n\'a pas pu être envoyé. Réessayez dans quelques minutes.'], 500);
        }
    }


    #[Route('/mes-strategies', name: 'app_user_strategies')]
    public function strategies(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $data = $this->getLifePlanData($em);

        // Fetch principles and group by pillar
        $principles = $user->getPrinciples();
        $growthPrinciples = [
            'CROISSANCE' => [],
            'DEVELOPPEMENT' => [],
            'CONTRIBUTION' => [],
            'DISCIPLINE' => [],
        ];
        foreach ($principles as $pr) {
            $pillar = $pr->getPillar();
            if ($pillar && isset($growthPrinciples[$pillar])) {
                $growthPrinciples[$pillar][] = $pr;
            }
        }
        $data['growthPrinciples'] = $growthPrinciples;

        return $this->render('plan_de_vie/strategies.html.twig', $data);
    }


    #[Route('/mes-plans/vie/wizard', name: 'app_user_life_plan_wizard')]
    public function lifePlanWizard(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $step = $request->query->getInt('step', 1);
        $single = $request->query->getBoolean('single', false);
        $plan = $request->query->get('plan');

        $allowedSteps = [];
        if ($plan === 'CROISSANCE') {
            $allowedSteps = [2, 9];
        } elseif ($plan === 'DEVELOPPEMENT') {
            $allowedSteps = [8];
        } elseif ($plan === 'CONTRIBUTION') {
            $allowedSteps = [10, 11];
        } elseif ($plan === 'DISCIPLINE') {
            $allowedSteps = [12];
        } else {
            // Default: Plan de Vie steps
            $allowedSteps = [1, 2, 3, 4, 5, 6, 7, 13];
        }

        // Adjust step if not in allowedSteps (only when multi-step)
        if (!$single && !in_array($step, $allowedSteps)) {
            $step = $allowedSteps[0];
        }

        $currentWizardStep = array_search($step, $allowedSteps) !== false ? array_search($step, $allowedSteps) + 1 : 1;

        $planDeVie = $user->getPlanDeVie() ?? new \App\PlanDeVie\Entity\PlanDeVie();
        if (!$planDeVie->getId()) {
            $planDeVie->setUser($user);
            $em->persist($planDeVie);
        }

        // Si le formulaire global du wizard est soumis :
        if ($request->isMethod('POST')) {
            $saveAll = !$single;
            $save1 = ($saveAll && in_array(1, $allowedSteps)) || $step === 1;
            $save2 = ($saveAll && in_array(2, $allowedSteps)) || $step === 2;
            $save3 = ($saveAll && in_array(3, $allowedSteps)) || $step === 3;
            $save4 = ($saveAll && in_array(4, $allowedSteps)) || $step === 4;
            $save5 = ($saveAll && in_array(5, $allowedSteps)) || $step === 5;
            $save6 = ($saveAll && in_array(6, $allowedSteps)) || $step === 6;
            $save7 = ($saveAll && in_array(7, $allowedSteps)) || $step === 7;
            $save8 = ($saveAll && in_array(8, $allowedSteps)) || $step === 8;
            $save9 = ($saveAll && in_array(9, $allowedSteps)) || $step === 9;
            $save10 = ($saveAll && in_array(10, $allowedSteps)) || $step === 10;
            $save11 = ($saveAll && in_array(11, $allowedSteps)) || $step === 11;
            $save12 = ($saveAll && in_array(12, $allowedSteps)) || $step === 12;

            // 1. Purpose (Step 1)
            if ($save1) {
                $purposeStmt = $request->request->get('purpose_statement');
                if ($purposeStmt !== null) {
                    $purpose = $planDeVie->getPurpose() ?? new \App\PlanDeVie\Entity\Purpose();
                    $purpose->setStatement(trim($purposeStmt));
                    $purpose->setPlanDeVie($planDeVie);
                    $em->persist($purpose);
                }
            }

            // 2. Balance Interne: Values, Convictions, Limiting Beliefs (Step 2)
            if ($save2) {
                // Values
                if ($request->request->has('values_real') || $request->request->has('values_declared') || $request->request->has('values_counter') || $saveAll) {
                    foreach ($planDeVie->getValues() as $v) {
                        $em->remove($v);
                    }
                    
                    $valuesReal = $request->request->all('values_real');
                    foreach ($valuesReal as $valName) {
                        if (!empty(trim($valName))) {
                            $v = new \App\PlanDeVie\Entity\Value();
                            $v->setName(trim($valName));
                            $v->setBehavior('');
                            $v->setCategory('real');
                            $v->setPlanDeVie($planDeVie);
                            $em->persist($v);
                        }
                    }

                    $valuesDeclared = $request->request->all('values_declared');
                    foreach ($valuesDeclared as $valName) {
                        if (!empty(trim($valName))) {
                            $v = new \App\PlanDeVie\Entity\Value();
                            $v->setName(trim($valName));
                            $v->setBehavior('');
                            $v->setCategory('declared');
                            $v->setPlanDeVie($planDeVie);
                            $em->persist($v);
                        }
                    }

                    $valuesCounter = $request->request->all('values_counter');
                    foreach ($valuesCounter as $valName) {
                        if (!empty(trim($valName))) {
                            $v = new \App\PlanDeVie\Entity\Value();
                            $v->setName(trim($valName));
                            $v->setBehavior('');
                            $v->setCategory('counter');
                            $v->setPlanDeVie($planDeVie);
                            $em->persist($v);
                        }
                    }
                }
                
                // Convictions & Limiting Beliefs
                if ($request->request->has('convictions_deep') || $request->request->has('convictions_medium') || $request->request->has('convictions_light') || $request->request->has('limiting_beliefs') || $saveAll) {
                    foreach ($user->getInternalBalances() as $ib) {
                        if ($ib->getType() === 'CONVICTION' || $ib->getType() === 'LIMITING_BELIEF') {
                            $em->remove($ib);
                        }
                    }

                    $convictionsDeep = $request->request->all('convictions_deep');
                    foreach ($convictionsDeep as $c) {
                        if (!empty(trim($c))) {
                            $ib = new \App\PlanDeVie\Entity\InternalBalance();
                            $ib->setUser($user);
                            $ib->setType('CONVICTION');
                            $ib->setSubtype('deep');
                            $ib->setText(trim($c));
                            $em->persist($ib);
                        }
                    }

                    $convictionsMedium = $request->request->all('convictions_medium');
                    foreach ($convictionsMedium as $c) {
                        if (!empty(trim($c))) {
                            $ib = new \App\PlanDeVie\Entity\InternalBalance();
                            $ib->setUser($user);
                            $ib->setType('CONVICTION');
                            $ib->setSubtype('medium');
                            $ib->setText(trim($c));
                            $em->persist($ib);
                        }
                    }

                    $convictionsLight = $request->request->all('convictions_light');
                    foreach ($convictionsLight as $c) {
                        if (!empty(trim($c))) {
                            $ib = new \App\PlanDeVie\Entity\InternalBalance();
                            $ib->setUser($user);
                            $ib->setType('CONVICTION');
                            $ib->setSubtype('light');
                            $ib->setText(trim($c));
                            $em->persist($ib);
                        }
                    }

                    $limitingBeliefs = $request->request->all('limiting_beliefs');
                    foreach ($limitingBeliefs as $lb) {
                        if (!empty(trim($lb))) {
                            $ib = new \App\PlanDeVie\Entity\InternalBalance();
                            $ib->setUser($user);
                            $ib->setType('LIMITING_BELIEF');
                            $ib->setSubtype(null);
                            $ib->setText(trim($lb));
                            $em->persist($ib);
                        }
                    }
                }
            }

            // 3. Passions (Step 3)
            if ($save3) {
                if ($request->request->has('passions') || $saveAll) {
                    foreach ($planDeVie->getPassions() as $p) {
                        $em->remove($p);
                    }
                    $passions = $request->request->all('passions');
                    foreach ($passions as $passName) {
                        if (!empty(trim($passName))) {
                            $p = new \App\PlanDeVie\Entity\Passion();
                            $p->setName(trim($passName));
                            $p->setPlanDeVie($planDeVie);
                            $em->persist($p);
                        }
                    }
                }
            }

            // 4. Talents (Step 4)
            if ($save4) {
                if ($request->request->has('talents') || $saveAll) {
                    foreach ($user->getInternalBalances() as $ib) {
                        if ($ib->getType() === 'TALENT') {
                            $em->remove($ib);
                        }
                    }
                    $talents = $request->request->all('talents');
                    foreach ($talents as $t) {
                        if (!empty(trim($t))) {
                            $ib = new \App\PlanDeVie\Entity\InternalBalance();
                            $ib->setUser($user);
                            $ib->setType('TALENT');
                            $ib->setText(trim($t));
                            $em->persist($ib);
                        }
                    }
                }
            }

            // 5. ProfileTest (Step 5)
            if ($save5) {
                if ($request->request->has('profile_mbti') || $saveAll) {
                    $mbti = $request->request->get('profile_mbti');
                    foreach ($user->getProfileTests() as $pt) {
                        if ($pt->getType() === 'MBTI') {
                            $em->remove($pt);
                        }
                    }
                    if ($mbti) {
                        $pt = new \App\Personnalite\Entity\ProfileTest();
                        $pt->setUser($user);
                        $pt->setType('MBTI');
                        $pt->setResult(trim($mbti));
                        $pt->setDate(new \DateTime());
                        $em->persist($pt);
                    }
                }
                if ($request->request->has('profile_disc') || $saveAll) {
                    $disc = $request->request->get('profile_disc');
                    foreach ($user->getProfileTests() as $pt) {
                        if ($pt->getType() === 'DISC') {
                            $em->remove($pt);
                        }
                    }
                    if ($disc) {
                        $pt = new \App\Personnalite\Entity\ProfileTest();
                        $pt->setUser($user);
                        $pt->setType('DISC');
                        $pt->setResult(trim($disc));
                        $pt->setDate(new \DateTime());
                        $em->persist($pt);
                    }
                }
                if ($request->request->has('profile_enneagram') || $saveAll) {
                    $enneagram = $request->request->get('profile_enneagram');
                    foreach ($user->getProfileTests() as $pt) {
                        if ($pt->getType() === 'ENNEAGRAM') {
                            $em->remove($pt);
                        }
                    }
                    if ($enneagram) {
                        $pt = new \App\Personnalite\Entity\ProfileTest();
                        $pt->setUser($user);
                        $pt->setType('ENNEAGRAM');
                        $pt->setResult(trim($enneagram));
                        $pt->setDate(new \DateTime());
                        $em->persist($pt);
                    }
                }
                if ($request->request->has('profile_big5') || $saveAll) {
                    $big5 = $request->request->get('profile_big5');
                    foreach ($user->getProfileTests() as $pt) {
                        if ($pt->getType() === 'BIG5') {
                            $em->remove($pt);
                        }
                    }
                    if ($big5) {
                        $pt = new \App\Personnalite\Entity\ProfileTest();
                        $pt->setUser($user);
                        $pt->setType('BIG5');
                        $pt->setResult(trim($big5));
                        $pt->setDate(new \DateTime());
                        $em->persist($pt);
                    }
                }
            }

            // 6. Vision (Step 6)
            if ($save6) {
                $visionContent = $request->request->get('vision_content');
                $visionHorizon = $request->request->get('vision_horizon');
                if ($visionContent !== null) {
                    $vision = $planDeVie->getVision() ?? new \App\PlanDeVie\Entity\Vision();
                    $vision->setContent(trim($visionContent));
                    $vision->setHorizon(trim($visionHorizon ?? '5 ans'));
                    $vision->setPlanDeVie($planDeVie);
                    $em->persist($vision);
                }
            }

            // 7. LifeGoals (Step 7)
            if ($save7) {
                if ($request->request->has('life_goals_be') || $request->request->has('life_goals_do') || $request->request->has('life_goals_have') || $request->request->has('life_goals_give') || $saveAll) {
                    foreach ($planDeVie->getLifeGoals() as $lg) {
                        $em->remove($lg);
                    }
                    $categories = ['BE', 'DO', 'HAVE', 'GIVE'];
                    foreach ($categories as $cat) {
                        $goals = $request->request->all('life_goals_' . strtolower($cat));
                        $deadlines = $request->request->all('life_goals_' . strtolower($cat) . '_deadlines');
                        if (is_array($goals)) {
                            foreach ($goals as $index => $gTitle) {
                                if (!empty(trim($gTitle))) {
                                    $lg = new \App\PlanDeVie\Entity\LifeGoal();
                                    $lg->setTitle(trim($gTitle));
                                    $lg->setCategory($cat);
                                    $lg->setProgress(0);
                                    $lg->setPlanDeVie($planDeVie);

                                    if (is_array($deadlines) && isset($deadlines[$index]) && !empty(trim($deadlines[$index]))) {
                                        try {
                                            $lg->setDeadline(new \DateTime($deadlines[$index]));
                                        } catch (\Exception $e) {
                                            // Ignore invalid date format
                                        }
                                    }

                                    $em->persist($lg);
                                }
                            }
                        }
                    }
                }
            }

            // 8, 9, 10. GrowthGoals (Steps 8, 9, 10)
            $planDeCroissance = $user->getPlanDeCroissance();
            if (!$planDeCroissance && ($saveAll || in_array($step, [8, 9, 10]))) {
                $planDeCroissance = new \App\PlanDeCroissance\Entity\PlanDeCroissance();
                $planDeCroissance->setUser($user);
                $em->persist($planDeCroissance);
            }

            // Step 8: COMPETENCE & CONNAISSANCE
            if ($save8) {
                if ($request->request->has('growth_competence_title') || $request->request->has('growth_connaissance_title') || $saveAll) {
                    if ($planDeCroissance) {
                        foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                            if ($gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::COMPETENCE) {
                                $em->remove($gg);
                            }
                        }
                    }
                    
                    // 1. Process Compétences
                    $compTitles = $request->request->all('growth_competence_title');
                    $compStrats = $request->request->all('growth_competence_strategy');
                    $compStatus = $request->request->all('growth_competence_status');
                    $compGoals = $request->request->all('growth_competence_goals');
                    if (is_array($compTitles)) {
                        foreach ($compTitles as $index => $title) {
                            if (!empty(trim($title))) {
                                $goal = new \App\PlanDeCroissance\Entity\GrowthGoal();
                                $goal->setTitle(trim($title));
                                $goal->setStrategy($compStrats[$index] ?? null);
                                $goal->setAxis(\App\PlanDeCroissance\Enum\GrowthAxis::COMPETENCE);
                                $goal->setType('COMPETENCE');
                                $goal->setSubtype($compStatus[$index] ?? 'build');
                                $goal->setProgress(0);

                                if (isset($compGoals[$index]) && is_array($compGoals[$index])) {
                                    foreach ($compGoals[$index] as $lgId) {
                                        $lg = $em->getRepository(\App\PlanDeVie\Entity\LifeGoal::class)->find($lgId);
                                        if ($lg) {
                                            $goal->addSupportedLifeGoal($lg);
                                        }
                                    }
                                }

                                $planDeCroissance->addGrowthGoal($goal);
                                $em->persist($goal);
                            }
                        }
                    }

                    // 2. Process Connaissances
                    $connTitles = $request->request->all('growth_connaissance_title');
                    $connStrats = $request->request->all('growth_connaissance_strategy');
                    $connStatus = $request->request->all('growth_connaissance_status');
                    $connGoals = $request->request->all('growth_connaissance_goals');
                    if (is_array($connTitles)) {
                        foreach ($connTitles as $index => $title) {
                            if (!empty(trim($title))) {
                                $goal = new \App\PlanDeCroissance\Entity\GrowthGoal();
                                $goal->setTitle(trim($title));
                                $goal->setStrategy($connStrats[$index] ?? null);
                                $goal->setAxis(\App\PlanDeCroissance\Enum\GrowthAxis::COMPETENCE);
                                $goal->setType('CONNAISSANCE');
                                $goal->setSubtype($connStatus[$index] ?? 'build');
                                $goal->setProgress(0);

                                if (isset($connGoals[$index]) && is_array($connGoals[$index])) {
                                    foreach ($connGoals[$index] as $lgId) {
                                        $lg = $em->getRepository(\App\PlanDeVie\Entity\LifeGoal::class)->find($lgId);
                                        if ($lg) {
                                            $goal->addSupportedLifeGoal($lg);
                                        }
                                    }
                                }

                                $planDeCroissance->addGrowthGoal($goal);
                                $em->persist($goal);
                            }
                        }
                    }
                }
            }

            // Step 9: CHARACTER
            if ($save9) {
                if ($request->request->has('growth_character_title') || $saveAll) {
                    if ($planDeCroissance) {
                        foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                            if ($gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::CHARACTER) {
                                $em->remove($gg);
                            }
                        }
                    }
                    $titles = $request->request->all('growth_character_title');
                    $strategies = $request->request->all('growth_character_strategy');
                    $status = $request->request->all('growth_character_status');
                    $goals = $request->request->all('growth_character_goals');
                    if (is_array($titles)) {
                        foreach ($titles as $index => $title) {
                            if (!empty(trim($title))) {
                                $goal = new \App\PlanDeCroissance\Entity\GrowthGoal();
                                $goal->setTitle(trim($title));
                                $goal->setStrategy($strategies[$index] ?? null);
                                $goal->setAxis(\App\PlanDeCroissance\Enum\GrowthAxis::CHARACTER);
                                $goal->setSubtype($status[$index] ?? 'build');
                                $goal->setProgress(0);

                                if (isset($goals[$index]) && is_array($goals[$index])) {
                                    foreach ($goals[$index] as $lgId) {
                                        $lg = $em->getRepository(\App\PlanDeVie\Entity\LifeGoal::class)->find($lgId);
                                        if ($lg) {
                                            $goal->addSupportedLifeGoal($lg);
                                        }
                                    }
                                }

                                $planDeCroissance->addGrowthGoal($goal);
                                $em->persist($goal);
                            }
                        }
                    }
                }
            }

            // Step 10: RELATION
            if ($save10) {
                if ($request->request->has('growth_relation_title') || $saveAll) {
                    if ($planDeCroissance) {
                        foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                            if ($gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::RELATION) {
                                $em->remove($gg);
                            }
                        }
                    }
                    $titles = $request->request->all('growth_relation_title');
                    $strategies = $request->request->all('growth_relation_strategy');
                    $status = $request->request->all('growth_relation_status');
                    $types = $request->request->all('growth_relation_type');
                    $goals = $request->request->all('growth_relation_goals');
                    if (is_array($titles)) {
                        foreach ($titles as $index => $title) {
                            if (!empty(trim($title))) {
                                $goal = new \App\PlanDeCroissance\Entity\GrowthGoal();
                                $goal->setTitle(trim($title));
                                $goal->setStrategy($strategies[$index] ?? null);
                                $goal->setAxis(\App\PlanDeCroissance\Enum\GrowthAxis::RELATION);
                                $goal->setType($types[$index] ?? 'orientation');
                                $goal->setSubtype($status[$index] ?? 'build');
                                $goal->setProgress(0);

                                if (isset($goals[$index]) && is_array($goals[$index])) {
                                    foreach ($goals[$index] as $lgId) {
                                        $lg = $em->getRepository(\App\PlanDeVie\Entity\LifeGoal::class)->find($lgId);
                                        if ($lg) {
                                            $goal->addSupportedLifeGoal($lg);
                                        }
                                    }
                                }

                                $planDeCroissance->addGrowthGoal($goal);
                                $em->persist($goal);
                            }
                        }
                    }
                }
            }

            // 11. Transmission (Step 11)
            if ($save11) {
                if ($request->request->has('legacy_title') || $saveAll) {
                    $planDeTransmission = $user->getPlanDeTransmission();
                    if (!$planDeTransmission) {
                        $planDeTransmission = new \App\PlanDeTransmission\Entity\PlanDeTransmission();
                        $planDeTransmission->setUser($user);
                        $em->persist($planDeTransmission);
                    } else {
                        foreach ($planDeTransmission->getLegacyGoals() as $lg) {
                            $em->remove($lg);
                        }
                    }
                    $legacyTitles = $request->request->all('legacy_title');
                    $legacyStatuses = $request->request->all('legacy_status');
                    $legacyGoalsData = $request->request->all('legacy_goals');
                    
                    if (is_array($legacyTitles)) {
                        foreach ($legacyTitles as $index => $lt) {
                            if (!empty(trim($lt))) {
                                $lg = new \App\PlanDeTransmission\Entity\LegacyGoal();
                                $lg->setTitle(trim($lt));
                                $status = $legacyStatuses[$index] ?? 'build';
                                $lg->setSubtype($status);
                                
                                if (isset($legacyGoalsData[$index]) && is_array($legacyGoalsData[$index])) {
                                    foreach ($legacyGoalsData[$index] as $lgId) {
                                        $lifeGoal = $em->getRepository(\App\PlanDeVie\Entity\LifeGoal::class)->find($lgId);
                                        if ($lifeGoal) {
                                            $lg->addSupportedLifeGoal($lifeGoal);
                                        }
                                    }
                                }
                                
                                $planDeTransmission->addLegacyGoal($lg);
                                $em->persist($lg);
                            }
                        }
                    }
                }
            }

            // 12. Hygiène de Vie (Step 12)
            if ($save12) {
                if ($request->request->has('hygiene_name') || $request->request->has('hygiene_domain_name') || $saveAll) {
                    $planDHygiene = $user->getPlanDHygiene();
                    if (!$planDHygiene) {
                        $planDHygiene = new \App\PlanDHygiene\Entity\PlanDHygiene();
                        $planDHygiene->setUser($user);
                        $em->persist($planDHygiene);
                    } else {
                        foreach ($planDHygiene->getHygieneDomains() as $hd) {
                            $em->remove($hd);
                        }
                    }
                    $hygieneNames = $request->request->all('hygiene_name');
                    if (empty($hygieneNames)) {
                        $hygieneNames = $request->request->all('hygiene_domain_name');
                    }
                    $hygieneStatuses = $request->request->all('hygiene_status');
                    $hygieneGoalsData = $request->request->all('hygiene_goals');
                    
                    if (is_array($hygieneNames)) {
                        foreach ($hygieneNames as $index => $hn) {
                            if (!empty(trim($hn))) {
                                $hd = new \App\PlanDHygiene\Entity\HygieneDomain();
                                $hd->setName(trim($hn));
                                $status = $hygieneStatuses[$index] ?? 'build';
                                $hd->setSubtype($status);
                                
                                if (isset($hygieneGoalsData[$index]) && is_array($hygieneGoalsData[$index])) {
                                    foreach ($hygieneGoalsData[$index] as $lgId) {
                                        $lifeGoal = $em->getRepository(\App\PlanDeVie\Entity\LifeGoal::class)->find($lgId);
                                        if ($lifeGoal) {
                                            $hd->addSupportedLifeGoal($lifeGoal);
                                        }
                                    }
                                }
                                
                                $planDHygiene->addHygieneDomain($hd);
                                $em->persist($hd);
                            }
                        }
                    }
                }
            }

            $em->flush();
            $this->addFlash('success', 'Votre Plan a été enregistré avec succès.');
            
            if ($single || $request->request->has('save_single')) {
                if ($plan) {
                    return $this->redirectToRoute('app_user_strategies', ['plan' => $plan]);
                }
                return $this->redirectToRoute('app_user_life_plan');
            }
            if ($plan) {
                return $this->redirectToRoute('app_user_strategies', ['plan' => $plan]);
            }
            return $this->redirectToRoute('app_dashboard');
        }

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
        $hygieneTest = $em->getRepository(\App\Personnalite\Entity\ProfileTest::class)->findOneBy(
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

        $appConfig = $em->getRepository(\App\Admin\Entity\AppConfig::class)->find('default');
        $defaultPurposeStatement = $appConfig ? $appConfig->getDefaultPurposeStatement() : '';

        return $this->render('plan_de_vie/wizard.html.twig', [
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
            'step' => $step,
            'single' => $single,
            'plan' => $plan,
            'allowedSteps' => $allowedSteps,
            'currentWizardStep' => $currentWizardStep,
            'defaultPurposeStatement' => $defaultPurposeStatement,
        ]);
    }

    #[Route('/mes-strategies/croissance/axis/save/{sourceType}/{sourceId}', name: 'app_user_strategies_croissance_axis_save', methods: ['POST'])]
    public function saveCroissanceAxis(
        string $sourceType,
        int $sourceId,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $planDeCroissance = $user->getPlanDeCroissance();
        if (!$planDeCroissance) {
            $planDeCroissance = new \App\PlanDeCroissance\Entity\PlanDeCroissance();
            $planDeCroissance->setUser($user);
            $em->persist($planDeCroissance);
        }

        $goal = $em->getRepository(\App\PlanDeCroissance\Entity\GrowthGoal::class)->findOneBy([
            'planDeCroissance' => $planDeCroissance,
            'sourceType' => $sourceType,
            'sourceId' => $sourceId
        ]);

        if (!$goal) {
            $goal = new \App\PlanDeCroissance\Entity\GrowthGoal();
            $goal->setPlanDeCroissance($planDeCroissance);
            $goal->setSourceType($sourceType);
            $goal->setSourceId($sourceId);
            $goal->setAxis(\App\PlanDeCroissance\Enum\GrowthAxis::CHARACTER);
            $goal->setProgress(0);
        }

        $sourceText = '';
        if ($sourceType === 'LIMITING_BELIEF' || $sourceType === 'CONVICTION') {
            $entity = $em->getRepository(\App\PlanDeVie\Entity\InternalBalance::class)->find($sourceId);
            if ($entity && $entity->getUser() === $user) {
                $sourceText = $entity->getText();
            }
        } elseif ($sourceType === 'COUNTER_VALUE' || $sourceType === 'DECLARED_VALUE') {
            $entity = $em->getRepository(\App\PlanDeVie\Entity\Value::class)->find($sourceId);
            if ($entity && $entity->getPlanDeVie() === $user->getPlanDeVie()) {
                $sourceText = $entity->getName();
            }
        }

        if (empty($sourceText)) {
            return $this->redirectToRoute('app_user_strategies', ['plan' => 'CROISSANCE']);
        }

        $goal->setTitle($sourceText);
        $goal->setStrategy($request->request->get('strategy', ''));
        $goal->setSubtype($request->request->get('status', 'build'));

        $goal->getSupportedLifeGoals()->clear();
        $lifeGoalsParam = $request->request->all('life_goals');
        foreach ($lifeGoalsParam as $lgId) {
            $lg = $em->getRepository(\App\PlanDeVie\Entity\LifeGoal::class)->find($lgId);
            if ($lg && $lg->getPlanDeVie() === $user->getPlanDeVie()) {
                $goal->addSupportedLifeGoal($lg);
            }
        }

        // Clear and recreate GrowthActions
        foreach ($goal->getActions() as $action) {
            $em->remove($action);
        }
        $goal->getActions()->clear();

        $actionTitles = $request->request->all('action_titles');
        $actionDeadlines = $request->request->all('action_deadlines');
        if (is_array($actionTitles)) {
            foreach ($actionTitles as $idx => $actTitle) {
                if (!empty(trim($actTitle))) {
                    $action = new \App\PlanDeCroissance\Entity\GrowthAction();
                    $action->setTitle(trim($actTitle));
                    $action->setGrowthGoal($goal);
                    $action->setStatus('NOT_STARTED');
                    
                    if (isset($actionDeadlines[$idx]) && !empty(trim($actionDeadlines[$idx]))) {
                        try {
                            $action->setDeadline(new \DateTime($actionDeadlines[$idx]));
                        } catch (\Exception $e) {}
                    }
                    $em->persist($action);
                    $goal->addAction($action);
                }
            }
        }

        // Clear and recreate Habits
        foreach ($goal->getHabits() as $habit) {
            $em->remove($habit);
        }
        $goal->getHabits()->clear();

        $habitTitles = $request->request->all('habit_titles');
        $habitFrequencies = $request->request->all('habit_frequencies');
        if (is_array($habitTitles)) {
            foreach ($habitTitles as $idx => $habTitle) {
                if (!empty(trim($habTitle))) {
                    $habit = new \App\Rituels\Entity\Habit();
                    $habit->setTitle(trim($habTitle));
                    $habit->setUser($user);
                    $habit->setGrowthGoal($goal);
                    
                    $freqVal = $habitFrequencies[$idx] ?? 'DAILY';
                    $freqEnum = \App\Rituels\Enum\HabitFrequency::tryFrom($freqVal) ?? \App\Rituels\Enum\HabitFrequency::DAILY;
                    $habit->setFrequency($freqEnum);
                    
                    $em->persist($habit);
                    $goal->addHabit($habit);
                }
            }
        }

        $em->persist($goal);
        $em->flush();

        return $this->redirectToRoute('app_user_strategies', [
            'plan' => 'CROISSANCE',
            'tab' => 'SYSTEM'
        ]);
    }

    #[Route('/mes-strategies/{plan}/principles/save', name: 'app_user_strategies_principles_save', methods: ['POST'])]
    #[Route('/mes-strategies/croissance/principles/save', name: 'app_user_strategies_croissance_principles_save', methods: ['POST'])]
    public function saveCroissancePrinciples(Request $request, EntityManagerInterface $em, string $plan = 'CROISSANCE'): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $planUpper = strtoupper($plan);

        $principles = $user->getPrinciples();
        foreach ($principles as $pr) {
            if ($pr->getPillar() === $planUpper) {
                $em->remove($pr);
            }
        }

        $proposed = $request->request->all('proposed_principles');
        $propsMeta = [
            'syst-obj' => [
                'title' => 'Systèmes > Objectifs',
                'description' => 'Je ne me concentre pas sur les résultats mais sur les systèmes qui les produisent.'
            ],
            'char-comp' => [
                'title' => 'Caractère avant compétence',
                'description' => 'Ce que je deviens est plus important que ce que je sais faire.'
            ],
            'one-thing' => [
                'title' => 'Une chose à la fois',
                'description' => 'Je fais peu de choses, mais je les fais profondément.'
            ]
        ];
        
        foreach ($proposed as $key) {
            if (isset($propsMeta[$key])) {
                $pr = new \App\PlanDeCroissance\Entity\Principle();
                $pr->setUser($user);
                $pr->setTitle($propsMeta[$key]['title']);
                $pr->setDescription($propsMeta[$key]['description']);
                $pr->setPillar($planUpper);
                $em->persist($pr);
            }
        }

        $customTitles = $request->request->all('custom_principle_titles');
        $customDescs = $request->request->all('custom_principle_descriptions');
        if (is_array($customTitles)) {
            foreach ($customTitles as $idx => $title) {
                if (!empty(trim($title))) {
                    $pr = new \App\PlanDeCroissance\Entity\Principle();
                    $pr->setUser($user);
                    $pr->setTitle(trim($title));
                    $pr->setDescription(trim($customDescs[$idx] ?? ''));
                    $pr->setPillar($planUpper);
                    $em->persist($pr);
                }
            }
        }

        $em->flush();

        return $this->redirectToRoute('app_user_strategies', [
            'plan' => $planUpper,
            'tab' => 'LAWS'
        ]);
    }
}
