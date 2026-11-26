<?php

namespace App\Mobile\Controller;

use App\Social\Entity\AccountabilityCommitment;
use App\Social\Entity\AccountabilityNudge;
use App\Social\Service\AccountabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Redevabilité dans l'application mobile : suivi des engagements, chiffres, historique, demande de coup de main,
 * pause / reprise / arrêt. La création (aperçu et consentement) se fait sur le site : l'API donne l'adresse.
 */
#[Route('/api/mobile/v1/redevabilite')]
final class MobileAccountabilityController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(
        private readonly AccountabilityService $service,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'api_mobile_v1_accountability_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->currentMobileUser();

        return $this->jsonNoStore([
            'commitments' => array_map(fn (AccountabilityCommitment $c) => $this->serialize($c), $this->service->commitmentsOf($user)),
            'asPartner' => array_map(static fn (AccountabilityCommitment $c) => [
                'id' => $c->getId(),
                'ownerName' => $c->getOwner()->getDisplayName(),
                'targetLabel' => $c->getTargetLabel(),
            ], $this->service->commitmentsAsPartner($user)),
            'canCreate' => $this->service->canCreate($user),
            'createUrl' => $this->generateUrl('app_accountability_new', [], UrlGeneratorInterface::ABSOLUTE_URL),
            // Point 6 : invitation unique d'un Partenaire de Redevabilité (sur le site, avec l'aperçu et le consentement)
            'inviteUrl' => $this->generateUrl('app_partner_invite', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    /** « J'ai besoin d'un coup de main » : e-mail au partenaire, copie à la personne. Corps : { message? }. */
    #[Route('/{id}/coup-de-main', name: 'api_mobile_v1_accountability_help', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function help(int $id, Request $request): JsonResponse
    {
        $c = $this->owned($id);
        if (null === $c) {
            return $this->jsonError('Engagement introuvable.', Response::HTTP_NOT_FOUND);
        }
        $body = $this->decodeJson($request) ?? [];
        try {
            $this->service->requestNudge($c, $this->currentMobileUser(), isset($body['message']) ? (string) $body['message'] : null);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->jsonNoStore(['commitment' => $this->serialize($c)]);
    }

    #[Route('/{id}/{action}', name: 'api_mobile_v1_accountability_manage', requirements: ['id' => '\d+', 'action' => 'pause|reprendre|arreter'], methods: ['POST'])]
    public function manage(int $id, string $action): JsonResponse
    {
        $c = $this->owned($id);
        if (null === $c) {
            return $this->jsonError('Engagement introuvable.', Response::HTTP_NOT_FOUND);
        }
        $this->service->ownerAction($c, $this->currentMobileUser(), ['pause' => 'pause', 'reprendre' => 'resume', 'arreter' => 'stop'][$action]);

        return $this->jsonNoStore(['commitment' => $this->serialize($c)]);
    }

    private function owned(int $id): ?AccountabilityCommitment
    {
        $c = $this->em->find(AccountabilityCommitment::class, $id);

        return $c instanceof AccountabilityCommitment && $c->getOwner() === $this->currentMobileUser() ? $c : null;
    }

    /** @return array<string, mixed> */
    private function serialize(AccountabilityCommitment $c): array
    {
        $stats = $this->service->stats($c);
        $next = $this->service->nextNudgeAllowedAt($c);

        return [
            'id' => $c->getId(),
            'targetLabel' => $c->getTargetLabel(),
            'targetType' => $c->getTargetType(),
            'partnerName' => $c->getPartnerName(),
            // Même clé que PartnerDirectory::people (u<id> pour un membre, e<e-mail> sinon) : l'appli regroupe par personne
            'partnerKey' => null !== $c->getPartnerUser() ? 'u'.$c->getPartnerUser()->getId() : 'e'.mb_strtolower($c->getPartnerEmail()),
            'status' => $c->getStatus(),
            'createdAt' => $c->getCreatedAt()->format('Y-m-d'),
            'alerts' => [
                'habitDays' => $c->getAlertHabitDays(),
                'goalDays' => $c->getAlertGoalDays(),
                'inactiveDays' => $c->getAlertInactiveDays(),
            ],
            'nextHelpAllowedOn' => $next?->format('Y-m-d'),
            'stats' => [
                'messages' => $stats['nudges'],
                'responses' => $stats['responses'],
                'responseRate' => $stats['rate'],
                'avgResponseHours' => $stats['avgHours'],
                'resumed' => $stats['resumed'],
            ],
            'history' => array_map(static fn (AccountabilityNudge $n) => [
                'id' => $n->getId(),
                'reason' => $n->getReason(),
                'reasonLabel' => $n->getReasonLabel(),
                'sentAt' => $n->getSentAt()->format('Y-m-d'),
                'fact' => $n->getFact(),
                'responseLabel' => $n->getResponseActionLabel(),
                'responseNote' => $n->getResponseNote(),
                'respondedAt' => $n->getRespondedAt()?->format('Y-m-d'),
                'resumed' => null !== $n->getResumedAt(),
            ], $stats['last']),
        ];
    }
}
