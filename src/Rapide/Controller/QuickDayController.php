<?php

namespace App\Rapide\Controller;

use App\Compte\Entity\User;
use App\Rapide\Service\QuickDay;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mode rapide : « Aujourd'hui en un geste », utilisable sans connexion réseau (application installable).
 *  - /rapide : la coquille de la page, identique pour tout le monde (aucune donnée personnelle) : le téléphone peut la garder ;
 *  - /api/rapide/aujourdhui : l'état du jour (JSON, réservé à la personne connectée) ;
 *  - /api/rapide/sync : reçoit les saisies faites hors ligne.
 */
final class QuickDayController extends AbstractController
{
    #[Route('/rapide', name: 'app_quick', methods: ['GET'])]
    public function page(): Response
    {
        $response = $this->render('rapide/index.html.twig', ['journalTypes' => QuickDay::JOURNAL_TYPES]);
        $response->setPublic();
        $response->setMaxAge(0);

        return $response;
    }

    #[Route('/api/rapide/aujourdhui', name: 'api_quick_today', methods: ['GET'])]
    public function today(QuickDay $quick): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        return $this->noStore(new JsonResponse($quick->snapshot($user)));
    }

    #[Route('/api/rapide/sync', name: 'api_quick_sync', methods: ['POST'])]
    public function sync(Request $request, QuickDay $quick): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data) || !\is_array($data['ops'] ?? null)) {
            return new JsonResponse(['error' => 'Requête invalide.'], Response::HTTP_BAD_REQUEST);
        }
        // Les saisies portent l'identifiant du compte qui les a faites : si un autre compte est connecté, rien n'est appliqué
        // (ancien client sans identifiant : accepté pour ne pas perdre ses saisies en attente)
        if (isset($data['account']) && (!\is_string($data['account']) || !hash_equals($quick->accountKey($user), $data['account']))) {
            return $this->noStore(new JsonResponse(['error' => 'Ces saisies viennent d\'un autre compte.', 'code' => 'account'], Response::HTTP_CONFLICT));
        }
        $results = $quick->apply($user, $data['ops']);

        return $this->noStore(new JsonResponse(['results' => $results, 'snapshot' => $quick->snapshot($user)]));
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(['error' => 'Connexion requise.'], Response::HTTP_UNAUTHORIZED);
    }

    private function noStore(JsonResponse $r): JsonResponse
    {
        $r->headers->set('Cache-Control', 'no-store, private');

        return $r;
    }
}
