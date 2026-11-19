<?php

namespace App\Mobile\Controller;

use App\Rapide\Service\QuickDay;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Quotidien hors connexion pour l'application mobile (réutilise le contrat idempotent de QuickDay).
 */
#[Route('/api/mobile/v1')]
class MobileQuickDayController extends AbstractController
{
    use MobileJsonTrait;

    #[Route('/aujourdhui', name: 'api_mobile_today', methods: ['GET'])]
    public function today(QuickDay $quick): JsonResponse
    {
        return $this->jsonNoStore($quick->snapshot($this->currentMobileUser()));
    }

    #[Route('/sync', name: 'api_mobile_sync', methods: ['POST'])]
    public function sync(Request $request, QuickDay $quick): JsonResponse
    {
        $user = $this->currentMobileUser();
        $data = $this->decodeJson($request);
        if (null === $data || !\is_array($data['ops'] ?? null)) {
            return $this->jsonError('Requête invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (\array_key_exists('account', $data) && (!\is_string($data['account']) || !hash_equals($quick->accountKey($user), $data['account']))) {
            return $this->jsonError('Ces saisies viennent d\'un autre compte.', Response::HTTP_CONFLICT, 'account');
        }

        $results = $quick->apply($user, $data['ops']);

        return $this->jsonNoStore([
            'results' => $results,
            'snapshot' => $quick->snapshot($user),
        ]);
    }
}
