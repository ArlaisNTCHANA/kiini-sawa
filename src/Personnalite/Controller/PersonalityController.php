<?php

namespace App\Personnalite\Controller;

use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Repository\PersonalityProfileRepository;
use App\Personnalite\Repository\PersonalityQuestionRepository;
use App\Personnalite\Service\PersonalityScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mes-tests', name: 'app_personality_')]
class PersonalityController extends AbstractController
{
    /** Anciens tests courts remplacés par les questionnaires psychométriques (AssessmentController). */
    private const REPLACED = ['MBTI' => 'type', 'DISC' => 'style', 'ENNEAGRAMME' => 'enneagramme', 'BIG5' => 'profil-complet'];

    private const TESTS = [
        'INTELLIGENCES' => ['name' => 'Intelligences Multiples', 'subtitle' => 'Théorie de Gardner', 'description' => 'Identifiez vos 8 formes d\'intelligence dominantes.', 'color' => '#06b6d4', 'bg' => '#ecfeff', 'icon' => 'compass', 'questions' => 8],
        'TALENT'        => ['name' => 'Architecture des Talents', 'subtitle' => 'Zones de Génie', 'description' => 'Diagnostiquer vos zones de génie naturel, sous pression et de plaisir.', 'color' => '#f97316', 'bg' => '#fff7ed', 'icon' => 'star', 'questions' => 6],
        'EQUILIBRE'     => ['name' => 'Équilibre de Vie', 'subtitle' => 'Roue de la vie', 'description' => 'Mesurez vos 8 domaines de vie et identifiez les zones à rééquilibrer.', 'color' => '#3b82f6', 'bg' => '#eff6ff', 'icon' => 'activity', 'questions' => 8],
        'HYGIENE'       => ['name' => 'Hygiène de Vie', 'subtitle' => '6 Axes Vitaux', 'description' => 'Audit en 6 axes (physique, mental, relationnel, spirituel, ressources, environnement).', 'color' => '#ec4899', 'bg' => '#fdf2f8', 'icon' => 'heart', 'questions' => 30],
    ];

    public function __construct(
        private readonly PersonalityQuestionRepository $questionRepo,
        private readonly PersonalityProfileRepository  $profileRepo,
        private readonly PersonalityScoreService       $scoreService,
        private readonly EntityManagerInterface        $em,
        private readonly \App\Personnalite\Repository\AssessmentRepository $assessments,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Catalogue — /mes-tests/
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        /** @var \App\Compte\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $profileTests = $user->getProfileTests();
        $latestByType = [];
        foreach ($profileTests as $pt) {
            $t = strtoupper($pt->getType());
            if ($t === 'ENNEAGRAM') {
                $t = 'ENNEAGRAMME';
            }
            if (!isset($latestByType[$t]) || $pt->getDate() > $latestByType[$t]->getDate()) {
                $latestByType[$t] = $pt;
            }
        }

        $assessments = [];
        foreach ($this->assessments->findForUser($user) as $a) {
            $assessments[$a->getInstrument()] ??= $a;
        }

        return $this->render('personnalite/index.html.twig', [
            'tests'       => self::TESTS,
            'latestTests' => $latestByType,
            'instruments' => \App\Personnalite\Psychometrie\Instruments::CATALOG,
            'assessments' => $assessments,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Passer un test — GET /mes-tests/{type}/passer
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{type}/passer', name: 'take', methods: ['GET'])]
    public function take(string $type): Response
    {
        $type = strtoupper($type);
        if (isset(self::REPLACED[$type])) {
            return $this->redirectToRoute('app_assessment_take', ['slug' => self::REPLACED[$type]], 301);
        }
        if (!isset(self::TESTS[$type])) {
            throw $this->createNotFoundException("Test inconnu : $type");
        }

        $questions = $this->questionRepo->findBy(
            ['testType' => $type],
            ['sortOrder' => 'ASC']
        );

        if (empty($questions)) {
            $this->addFlash('warning', "Les questions pour le test $type ne sont pas encore disponibles.");
            return $this->redirectToRoute('app_personality_index');
        }

        return $this->render('personnalite/test.html.twig', [
            'testMeta'  => self::TESTS[$type],
            'type'      => $type,
            'questions' => $questions,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Soumettre un test — POST /mes-tests/{type}/passer
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{type}/passer', name: 'submit', methods: ['POST'])]
    public function submit(string $type, Request $request): Response
    {
        $type = strtoupper($type);
        if (!isset(self::TESTS[$type])) {
            throw $this->createNotFoundException("Test inconnu : $type");
        }

        /** @var \App\Compte\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Collect answers: key = question category/id, value = answer value
        $rawAnswers = $request->request->all('answers');
        if (empty($rawAnswers)) {
            $this->addFlash('error', 'Aucune réponse reçue. Veuillez compléter le test.');
            return $this->redirectToRoute('app_personality_take', ['type' => strtolower($type)]);
        }

        // Calculate result
        $calculated = $this->scoreService->calculate($type, $rawAnswers);

        // Save ProfileTest
        $profileTest = new ProfileTest();
        $profileTest->setUser($user);
        $profileTest->setType($type);
        $profileTest->setResult($calculated['result']);
        $profileTest->setInsights($calculated['insights']);
        $profileTest->setDate(new \DateTime());

        $this->em->persist($profileTest);
        $this->em->flush();

        $this->addFlash('success', 'Test complété ! Voici vos résultats.');
        return $this->redirectToRoute('app_personality_result', ['type' => strtolower($type)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Résultats — /mes-tests/{type}/resultats
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{type}/resultats', name: 'result')]
    public function result(string $type): Response
    {
        $type = strtoupper($type);
        if (!isset(self::TESTS[$type])) {
            throw $this->createNotFoundException("Test inconnu : $type");
        }

        /** @var \App\Compte\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Get latest test of this type
        $latestTest = null;
        foreach ($user->getProfileTests() as $pt) {
            $ptType = strtoupper($pt->getType());
            if ($ptType === $type || ($type === 'ENNEAGRAMME' && $ptType === 'ENNEAGRAM')) {
                if (!$latestTest || $pt->getDate() > $latestTest->getDate() || ($pt->getDate() == $latestTest->getDate() && $pt->getId() > $latestTest->getId())) {
                    $latestTest = $pt;
                }
            }
        }

        if (!$latestTest) {
            $this->addFlash('info', 'Vous n\'avez pas encore passé ce test.');
            return $this->redirectToRoute('app_personality_take', ['type' => strtolower($type)]);
        }

        // Find profile if available
        $profile = null;
        $resultCode = $latestTest->getResult();
        $codeToFind = $resultCode;
        $formattedResult = $resultCode;

        if (str_starts_with($resultCode, '{')) {
            try {
                $parsed = json_decode($resultCode, true);
                if (is_array($parsed) && !empty($parsed)) {
                    if (isset($parsed['type'])) {
                        $codeToFind = (string)$parsed['type'];
                        $formattedResult = $parsed['type'];
                        if (isset($parsed['wing'])) {
                            $formattedResult .= ' w' . $parsed['wing'];
                        }
                    } else {
                        arsort($parsed);
                        $codeToFind = (string)array_key_first($parsed);
                    }
                    $codeToFind = strtoupper($codeToFind);
                }
            } catch (\Throwable) {}
        }

        $profile = $this->profileRepo->findOneBy(['testType' => $type, 'code' => $codeToFind]);

        // Parse multidimensional results
        $chartData = [];
        $detailsData = [];
        $isMulti = in_array($type, ['DISC', 'BIG5', 'INTELLIGENCES', 'TALENT', 'EQUILIBRE', 'HYGIENE']);
        if ($isMulti && str_starts_with($resultCode, '{')) {
            try {
                $parsed = json_decode($resultCode, true);
                if ($type === 'HYGIENE' && isset($parsed['axes']) && isset($parsed['details'])) {
                    foreach ($parsed['axes'] as $key => $val) {
                        $chartData[] = ['label' => ucfirst($key), 'key' => $key, 'value' => (float)$val];
                    }
                    $detailsData = $parsed['details'];
                } else {
                    foreach ($parsed as $key => $val) {
                        $chartData[] = ['label' => ucfirst($key), 'key' => $key, 'value' => (float)$val];
                    }
                }
            } catch (\Throwable) {}
        }

        return $this->render('personnalite/result.html.twig', [
            'testMeta'        => self::TESTS[$type],
            'type'            => $type,
            'test'            => $latestTest,
            'profile'         => $profile,
            'chartData'       => $chartData,
            'detailsData'     => $detailsData,
            'isMulti'         => $isMulti,
            'formattedResult' => $formattedResult,
        ]);
    }
}
