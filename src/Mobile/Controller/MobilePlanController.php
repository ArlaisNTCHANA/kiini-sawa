<?php

namespace App\Mobile\Controller;

use App\Abonnement\Service\Entitlements;
use App\Mobile\Service\MobilePlanSerializer;
use App\PlanDeVie\Service\PlanCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Consultation des 6 plans et de la progression pour l'application mobile.
 *
 * Le Plan de Vie est gratuit pour tous. Les plans réservés à l'offre PRO
 * renvoient une erreur 403 pro_required (sans URL ni prix, conformité Apple).
 */
#[Route('/api/mobile/v1/plans')]
class MobilePlanController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(
        private readonly MobilePlanSerializer $serializer,
        private readonly Entitlements $entitlements,
    ) {
    }

    #[Route('', name: 'api_mobile_plans_overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        $user = $this->currentMobileUser();

        return $this->jsonNoStore($this->serializer->serializeOverview($user));
    }

    #[Route('/{key}', name: 'api_mobile_plans_show', methods: ['GET'])]
    public function show(string $key): JsonResponse
    {
        $user = $this->currentMobileUser();
        $upperKey = strtoupper($key);

        if (!isset(PlanCatalog::PLANS[$upperKey])) {
            return $this->jsonError(sprintf('Plan « %s » introuvable.', $key), Response::HTTP_NOT_FOUND, 'plan_not_found');
        }

        $feature = Entitlements::PLAN_FEATURES[$upperKey] ?? null;
        if (null !== $feature) {
            $this->entitlements->require($user, $feature);
        }

        return $this->jsonNoStore([
            'plan' => $this->serializer->serializePlan($user, $upperKey),
        ]);
    }
}
