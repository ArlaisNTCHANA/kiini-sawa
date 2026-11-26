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
    private const REPLACED = ['MBTI' => 'type', 'DISC' => 'disc', 'ENNEAGRAMME' => 'enneagramme', 'ENNEAGRAM' => 'enneagramme', 'BIG5' => 'profil-complet', 'DONS' => 'dons-spirituels'];

    /** Questionnaires où l'on cherche le dernier rapport d'un ancien type (le profil complet couvre type, style et Big Five). */
    private const REPORT_SOURCES = ['MBTI' => ['type', 'profil-complet'], 'DISC' => ['disc', 'style', 'profil-complet'], 'BIG5' => ['profil-complet', 'big-five'],
        'ENNEAGRAMME' => ['enneagramme'], 'ENNEAGRAM' => ['enneagramme'], 'DONS' => ['dons-spirituels']];

    /** Tests retirés (trop peu de questions pour un résultat fiable) : leurs anciennes adresses ramènent au catalogue. */
    private const REMOVED = ['INTELLIGENCES', 'TALENT'];

    private const TESTS = [
        'EQUILIBRE'     => ['name' => 'Équilibre de Vie', 'subtitle' => 'Roue de la vie', 'description' => 'Mesurez vos 8 domaines de vie et identifiez les zones à rééquilibrer.', 'color' => '#3b82f6', 'bg' => '#eff6ff', 'icon' => 'activity', 'questions' => 8, 'level' => 'Auto-évaluation'],
        'HYGIENE'       => ['name' => 'Hygiène de Vie', 'subtitle' => '6 Axes Vitaux', 'description' => 'Audit en 6 axes (physique, mental, relationnel, spirituel, ressources, environnement).', 'color' => '#ec4899', 'bg' => '#fdf2f8', 'icon' => 'heart', 'questions' => 30, 'level' => 'Auto-évaluation'],
    ];

    public function __construct(
        private readonly PersonalityQuestionRepository $questionRepo,
        private readonly PersonalityProfileRepository  $profileRepo,
        private readonly PersonalityScoreService       $scoreService,
        private readonly EntityManagerInterface        $em,
        private readonly \App\Personnalite\Repository\AssessmentRepository $assessments,
        private readonly \App\Personnalite\Service\SelfAssessmentReport $selfReports,
        private readonly \App\Personnalite\Service\TestHistory $history,
        private readonly \App\Abonnement\Service\Entitlements $entitlements,
    ) {}

    /** Rapport PDF d'une auto-évaluation (dernière passation). */
    #[Route('/{type}/resultats/pdf', name: 'result_pdf', methods: ['GET'])]
    public function resultPdf(string $type, \App\Shared\Service\PdfFactory $pdf): Response
    {
        $type = strtoupper($type);
        /** @var \App\Compte\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user || !isset(self::TESTS[$type])) {
            throw $this->createNotFoundException();
        }
        // Rapports PDF des tests : PRO, comme AssessmentController::reportPdf()
        if (!$this->entitlements->isPro($user)) {
            throw new \App\Abonnement\Exception\ProRequiredException('test_pdf', 'Le rapport PDF des tests fait partie de KIINI SAWA PRO.');
        }
        $id = (int) ($this->container->get('request_stack')->getCurrentRequest()?->query->get('id') ?? 0);
        $latest = $id
            ? $this->em->getRepository(ProfileTest::class)->findOneBy(['id' => $id, 'user' => $user, 'type' => $type])
            : $this->em->getRepository(ProfileTest::class)->findOneBy(['user' => $user, 'type' => $type], ['date' => 'DESC', 'id' => 'DESC']);
        $report = $latest ? $this->selfReports->build($latest) : null;
        if (!$report) {
            throw $this->createNotFoundException();
        }
        $html = $this->renderView('personnalite/assessment/report/pdf.html.twig', $report + ['body' => $report['template'], 'name' => $user->getDisplayName(), 'instrument' => ['name' => self::TESTS[$type]['name']]]);

        return new Response($pdf->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition('attachment', 'kiini-sawa-'.strtolower($type).'-'.$latest->getDate()->format('Y-m-d').'.pdf'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Catalogue — /mes-tests/
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/', name: 'index', methods: ['GET'])]
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
            'groups'      => \App\Personnalite\Psychometrie\Instruments::GROUPS,
            'levels'      => \App\Personnalite\Psychometrie\Instruments::LEVELS,
            'assessments' => $assessments,
            // Point 7 (26/11/2026) : la Boussole 360 figure aussi dans « Mes tests » et compte dans la progression
            'boussole' => $this->em->getRepository(\App\Boussole\Entity\BoussoleResult::class)->findOneBy(['user' => $user], ['id' => 'DESC']),
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
        if (\in_array($type, self::REMOVED, true)) {
            return $this->redirectToRoute('app_personality_index', [], 301);
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
        if (!$this->isCsrfTokenValid('personality_test_'.strtolower($type), $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré. Vos réponses n\'ont pas été enregistrées : merci de recommencer.');

            return $this->redirectToRoute('app_personality_take', ['type' => strtolower($type)]);
        }

        // Collect answers: key = question category/id, value = answer value
        // Une réponse vide n'est pas une réponse (elle valait 0 au calcul : rapport Équilibre de Vie à 0/10, 25/11/2026)
        $rawAnswers = array_filter($request->request->all('answers'), fn ($v) => \is_scalar($v) && '' !== trim((string) $v));
        $expected = \count(array_unique(array_map(fn ($q) => $q->getCategory(), $this->questionRepo->findBy(['testType' => $type]))));
        if ($rawAnswers && \count($rawAnswers) < $expected) {
            $this->addFlash('error', 'Certaines réponses ne nous sont pas parvenues. Vos réponses n\'ont pas été enregistrées : merci de recommencer.');

            return $this->redirectToRoute('app_personality_take', ['type' => strtolower($type)]);
        }
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
    #[Route('/{type}/resultats/{id}', name: 'result', requirements: ['id' => '\d+'], defaults: ['id' => null], methods: ['GET'])]
    public function result(string $type, ?int $id = null): Response
    {
        $type = strtoupper($type);
        if (isset(self::REPORT_SOURCES[$type]) && ($user = $this->getUser()) instanceof \App\Compte\Entity\User) {
            // Par ordre de préférence (le test DISC passe avant l'ancien style déduit du Big Five)
            foreach (self::REPORT_SOURCES[$type] as $source) {
                if ($a = $this->assessments->latest($user, $source)) {
                    return $this->redirectToRoute('app_assessment_report', ['id' => $a->getId()]);
                }
            }

            return $this->redirectToRoute('app_assessment_take', ['slug' => self::REPLACED[$type]]);
        }
        if (\in_array($type, self::REMOVED, true)) {
            return $this->redirectToRoute('app_personality_index', [], 301);
        }
        if (!isset(self::TESTS[$type])) {
            throw $this->createNotFoundException("Test inconnu : $type");
        }

        /** @var \App\Compte\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Dernière passation de ce test (requête directe : la collection de l'utilisateur peut être incomplète)
        $latestTest = $id
            ? ($this->em->getRepository(ProfileTest::class)->findOneBy(['id' => $id, 'user' => $user, 'type' => $type]) ?? throw $this->createNotFoundException())
            : $this->em->getRepository(ProfileTest::class)->findOneBy(['user' => $user, 'type' => $type], ['date' => 'DESC', 'id' => 'DESC']);

        if (!$latestTest) {
            $this->addFlash('info', 'Vous n\'avez pas encore passé ce test.');
            return $this->redirectToRoute('app_personality_take', ['type' => strtolower($type)]);
        }

        // Rapport illustré (Équilibre de Vie, Hygiène de Vie)
        if ($report = $this->selfReports->build($latestTest)) {
            return $this->render('personnalite/self_report.html.twig', $report + ['testMeta' => self::TESTS[$type], 'instrument' => ['name' => self::TESTS[$type]['name']], 'addedActions' => $this->em->getRepository(\App\Personnalite\Entity\TestAction::class)->addedBy($user),
                'history' => ['series' => strtolower($type), 'name' => self::TESTS[$type]['name'], 'entries' => $this->history->entries($user, strtolower($type))]]);
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
