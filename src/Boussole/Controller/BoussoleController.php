<?php

namespace App\Boussole\Controller;

use App\Boussole\Entity\BoussoleResult;
use App\Boussole\Repository\BoussoleResultRepository;
use App\Boussole\Service\BoussoleEngine;
use App\Boussole\Service\BoussoleRecorder;
use App\Compte\Entity\User;
use App\PlanDeVie\Service\PlanCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ma Boussole 360 sur le site : questionnaire public (sans compte), enregistrement dans le compte, historique.
 * Le questionnaire et l'affichage immédiat restent en JS (assets/js/boussole-engine.js) ; l'enregistrement
 * repasse toujours par le calcul serveur (BoussoleEngine).
 */
final class BoussoleController extends AbstractController
{
    public function __construct(
        private readonly BoussoleRecorder $recorder,
        private readonly BoussoleResultRepository $results,
        private readonly \App\Shared\Security\RateLimits $limits,
    ) {
    }

    #[Route('/boussole', name: 'app_public_boussole', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('shared/boussole.html.twig', [
            'connecte' => $user instanceof User,
            'derniere' => $user instanceof User ? $this->results->latestFor($user) : null,
        ]);
    }

    /**
     * Reçoit les 24 réponses brutes. Connecté : enregistrement immédiat. Visiteur : réponses gardées dans la
     * session, enregistrées après la création du compte ou la connexion (BoussolePendingSubscriber).
     */
    #[Route('/boussole/resultat', name: 'app_boussole_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        // Jeton CSRF (en-tête X-CSRF-Token, donné par la page), en plus du contrôle d'origine du site
        if (!$this->isCsrfTokenValid('boussole', (string) $request->headers->get('X-CSRF-Token', ''))) {
            return $this->noStore(['erreur' => 'La page a expiré. Merci de la recharger.'], Response::HTTP_FORBIDDEN);
        }
        $user = $this->getUser();
        // Pas d'envois en boucle : 10 passations par heure et par compte, 30 envois par heure et par adresse IP
        if (!$this->limits->allow('boussole_submit_ip', (string) $request->getClientIp())
            || ($user instanceof User && !$this->limits->allow('boussole_submit', (string) $user->getId()))) {
            return $this->noStore(['erreur' => 'Trop de passations en peu de temps. Merci de réessayer dans une heure.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode($request->getContent(), true);
        $answers = BoussoleEngine::normalizeAnswers(\is_array($payload) ? ($payload['reponses'] ?? null) : null);
        if (null === $answers) {
            return $this->noStore(['erreur' => 'Les 24 réponses (de 1 à 5) sont attendues.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($user instanceof User && $user->isVerified()) {
            $result = $this->recorder->record($user, $answers, BoussoleResult::SOURCE_WEB);

            return $this->noStore([
                'enregistre' => true,
                'coherence' => $result->getCoherence(),
                'historique' => $this->generateUrl('app_boussole_history'),
            ]);
        }

        $this->recorder->keepPending($request->getSession(), $answers);

        return $this->noStore([
            'enregistre' => false,
            'enAttente' => true,
            'inscription' => $this->generateUrl('app_register'),
            'connexion' => $this->generateUrl('app_login'),
        ]);
    }

    #[Route('/boussole/historique', name: 'app_boussole_history', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function history(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $history = $this->results->historyFor($user);

        return $this->render('boussole/history.html.twig', [
            'history' => $history,
            'latest' => $history[0] ?? null,
            'reading' => isset($history[0]) ? $this->recorder->evaluate($history[0]) : null,
            'previous' => $history[1] ?? null,
            'plans' => BoussoleEngine::plans(),
            'planRoutes' => array_map(fn (array $p) => $p['route'], PlanCatalog::PLANS),
        ]);
    }

    #[Route('/boussole/passation/{id}', name: 'app_boussole_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $result = $this->results->findOneForUser($id, $user);
        if (null === $result) {
            throw $this->createNotFoundException();
        }

        return $this->render('boussole/show.html.twig', [
            'result' => $result,
            'reading' => $this->recorder->evaluate($result),
            'plans' => BoussoleEngine::plans(),
            'planRoutes' => array_map(fn (array $p) => $p['route'], PlanCatalog::PLANS),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function noStore(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
