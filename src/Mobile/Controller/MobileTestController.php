<?php

namespace App\Mobile\Controller;

use App\Compte\Entity\User;
use App\Mobile\Service\MobileTestSerializer;
use App\Personnalite\Entity\Assessment;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\Personnalite\Repository\AssessmentRepository;
use App\Personnalite\Repository\ProfileTestRepository;
use App\Personnalite\Service\PersonalityScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mobile/v1/tests')]
#[IsGranted('ROLE_USER')]
final class MobileTestController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(
        private readonly MobileTestSerializer $serializer,
        private readonly AssessmentService $assessmentService,
        private readonly AssessmentRepository $assessmentRepository,
        private readonly ProfileTestRepository $profileTestRepository,
        private readonly PersonalityScoreService $scoreService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Catalogue des tests disponibles avec statut d'avancement pour l'utilisateur.
     */
    #[Route('', name: 'mobile_tests_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->currentMobileUser();
        $data = $this->serializer->serializeCatalog($user);

        return $this->jsonNoStore($data);
    }

    /**
     * Synthèse globale croisée « Mon profil KIINI SAWA ».
     */
    #[Route('/synthese', name: 'mobile_tests_synthesis', methods: ['GET'])]
    public function synthesis(): JsonResponse
    {
        $user = $this->currentMobileUser();
        $data = $this->serializer->serializeSynthesis($user);

        return $this->jsonNoStore($data);
    }

    /**
     * Questions d'un test spécifique pour passation.
     */
    #[Route('/{slug}/questions', name: 'mobile_tests_questions', methods: ['GET'])]
    public function questions(string $slug): JsonResponse
    {
        $user = $this->currentMobileUser();

        try {
            $data = $this->serializer->serializeQuestions($user, $slug);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        return $this->jsonNoStore($data);
    }

    /**
     * Soumission des réponses d'un test.
     */
    #[Route('/{slug}/reponses', name: 'mobile_tests_submit', methods: ['POST'])]
    public function submit(string $slug, Request $request): JsonResponse
    {
        $user = $this->currentMobileUser();
        $slug = strtolower($slug);
        $payload = $this->decodeJson($request) ?? [];

        $answers = $payload['answers'] ?? [];
        if (!\is_array($answers) || empty($answers)) {
            return $this->jsonError('Le tableau answers est obligatoire et ne peut pas être vide.', Response::HTTP_BAD_REQUEST);
        }

        $durationSeconds = isset($payload['durationSeconds']) ? (int) $payload['durationSeconds'] : null;

        // Auto-évaluations (EQUILIBRE / HYGIENE)
        if (\in_array($slug, ['equilibre', 'hygiene'], true)) {
            $type = strtoupper($slug);
            $calculated = $this->scoreService->calculate($type, $answers);

            $profileTest = new ProfileTest();
            $profileTest->setUser($user);
            $profileTest->setType($type);
            $profileTest->setResult($calculated['result']);
            $profileTest->setInsights($calculated['insights']);
            $profileTest->setDate(new \DateTime());

            $this->em->persist($profileTest);
            $this->em->flush();

            return $this->jsonNoStore([
                'ok' => true,
                'id' => $profileTest->getId(),
                'slug' => $slug,
                'message' => 'Auto-évaluation enregistrée avec succès.',
            ], Response::HTTP_CREATED);
        }

        // Questionnaires psychométriques (DISC, Big Five, Ennéagramme, Dons...)
        if (!Instruments::exists($slug)) {
            throw new NotFoundHttpException(sprintf('Test inconnu : %s', $slug));
        }

        try {
            $assessment = $this->assessmentService->record($user, $slug, $answers, $durationSeconds);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $report = $this->serializer->serializePsychometricReport($assessment);

        return $this->jsonNoStore([
            'ok' => true,
            'id' => $assessment->getId(),
            'slug' => $slug,
            'headline' => $report['headline'] ?? '',
            'report' => $report,
        ], Response::HTTP_CREATED);
    }

    /**
     * Rapport d'un test spécifique (le plus récent ou par son identifiant).
     */
    #[Route('/{slug}/rapport', name: 'mobile_tests_report', methods: ['GET'])]
    public function report(string $slug, Request $request): JsonResponse
    {
        $user = $this->currentMobileUser();
        $slug = strtolower($slug);
        $id = $request->query->getInt('id');

        // Auto-évaluation
        if (\in_array($slug, ['equilibre', 'hygiene'], true)) {
            $type = strtoupper($slug);
            $profileTest = $id > 0
                ? $this->profileTestRepository->findOneBy(['id' => $id, 'user' => $user, 'type' => $type])
                : $this->profileTestRepository->findOneBy(['user' => $user, 'type' => $type], ['date' => 'DESC', 'id' => 'DESC']);

            if (!$profileTest) {
                return $this->jsonError('Aucun résultat trouvé pour cette auto-évaluation.', Response::HTTP_NOT_FOUND);
            }

            return $this->jsonNoStore($this->serializer->serializeAutoEvaluationReport($profileTest));
        }

        // Questionnaire psychométrique
        if (!Instruments::exists($slug)) {
            throw new NotFoundHttpException(sprintf('Test inconnu : %s', $slug));
        }

        $assessment = $id > 0
            ? $this->assessmentRepository->findOneBy(['id' => $id, 'user' => $user, 'instrument' => $slug])
            : $this->assessmentRepository->latest($user, $slug);

        if (!$assessment) {
            return $this->jsonError('Aucun résultat trouvé pour ce test.', Response::HTTP_NOT_FOUND);
        }

        return $this->jsonNoStore($this->serializer->serializePsychometricReport($assessment));
    }
}
