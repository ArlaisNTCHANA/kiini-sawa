<?php

namespace App\Mobile\Controller;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Couple\Repository\CoupleRepository;
use App\Defis\Entity\Challenge;
use App\Defis\Entity\ChallengeParticipant;
use App\Defis\Repository\ChallengeRepository;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Social\Entity\Connection;
use App\Social\Enum\ShareScope;
use App\Social\Repository\ConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MobileCommunityController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConnectionRepository $connectionRepo,
        private readonly ChallengeRepository $challengeRepo,
        private readonly CoupleRepository $coupleRepo,
        private readonly Notifier $notifier,
        private readonly Entitlements $entitlements,
    ) {
    }

    #[Route('/api/mobile/v1/partenaires', name: 'api_mobile_v1_partners_list', methods: ['GET'])]
    public function listPartners(): JsonResponse
    {
        $user = $this->currentMobileUser();

        $active = [];
        $pendingReceived = [];
        $pendingSent = [];

        // Invitations créées par l'utilisateur
        foreach ($user->getConnections() as $conn) {
            if ('ACCEPTED' === $conn->getStatus()) {
                $partner = $conn->getReceiver();
                $active[] = [
                    'id' => $conn->getId(),
                    'partnerName' => $partner->getDisplayName(),
                    'partnerEmail' => $partner->getEmail(),
                    'type' => $conn->getType(),
                    'isInitiator' => true,
                ];
            } elseif ('PENDING' === $conn->getStatus()) {
                $partner = $conn->getReceiver();
                $pendingSent[] = [
                    'id' => $conn->getId(),
                    'partnerName' => $partner->getDisplayName(),
                    'partnerEmail' => $partner->getEmail(),
                    'date' => $conn->getCreatedAt()->format('Y-m-d'),
                ];
            }
        }

        // Invitations reçues par l'utilisateur
        foreach ($user->getConnectedTo() as $conn) {
            if ('ACCEPTED' === $conn->getStatus()) {
                $partner = $conn->getRequester();
                $active[] = [
                    'id' => $conn->getId(),
                    'partnerName' => $partner->getDisplayName(),
                    'partnerEmail' => $partner->getEmail(),
                    'type' => $conn->getType(),
                    'isInitiator' => false,
                ];
            } elseif ('PENDING' === $conn->getStatus()) {
                $partner = $conn->getRequester();
                $pendingReceived[] = [
                    'id' => $conn->getId(),
                    'partnerName' => $partner->getDisplayName(),
                    'partnerEmail' => $partner->getEmail(),
                    'date' => $conn->getCreatedAt()->format('Y-m-d'),
                ];
            }
        }

        $totalActive = \count($active);
        $limit = $this->entitlements->limit($user, 'connections');
        $isLimitReached = null !== $limit && $totalActive >= $limit;

        return $this->jsonNoStore([
            'totalActive' => $totalActive,
            'limit' => $limit,
            'isLimitReached' => $isLimitReached,
            'active' => $active,
            'pendingReceived' => $pendingReceived,
            'pendingSent' => $pendingSent,
        ]);
    }

    #[Route('/api/mobile/v1/partenaires/invitation', name: 'api_mobile_v1_partners_invite', methods: ['POST'])]
    public function invitePartner(Request $request): JsonResponse
    {
        $user = $this->currentMobileUser();
        $payload = $this->decodeJson($request);

        if (null === $payload) {
            return $this->jsonError('Corps JSON attendu.', Response::HTTP_BAD_REQUEST, 'INVALID_JSON');
        }

        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonError('Adresse email invalide.', Response::HTTP_UNPROCESSABLE_ENTITY, 'INVALID_EMAIL');
        }

        if ($email === mb_strtolower($user->getEmail())) {
            return $this->jsonError('Vous ne pouvez pas vous inviter vous-même.', Response::HTTP_UNPROCESSABLE_ENTITY, 'SELF_INVITE');
        }

        $activeCount = \count(array_filter(
            array_merge($user->getConnections()->toArray(), $user->getConnectedTo()->toArray()),
            fn ($c) => 'REJECTED' !== $c->getStatus()
        ));

        $limit = $this->entitlements->limit($user, 'connections');
        if (null !== $limit && $activeCount >= $limit) {
            return $this->jsonError(
                'Limite de partenaires atteinte pour votre formule actuelle.',
                Response::HTTP_FORBIDDEN,
                'PARTNER_LIMIT_REACHED'
            );
        }

        $receiver = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$receiver) {
            // Même réponse que si succès pour ne pas divulguer l'existence d'un compte
            return $this->jsonNoStore(['success' => true]);
        }

        // Vérification de connexion existante
        $existing = $this->connectionRepo->findOneBy(['requester' => $user, 'receiver' => $receiver]);
        if (!$existing) {
            $existing = $this->connectionRepo->findOneBy(['requester' => $receiver, 'receiver' => $user]);
        }

        if ($existing) {
            return $this->jsonError('Une demande existe déjà avec cet utilisateur.', Response::HTTP_CONFLICT, 'ALREADY_EXISTS');
        }

        $connection = new Connection();
        $connection->setRequester($user);
        $connection->setReceiver($receiver);
        $connection->setStatus('PENDING');
        $connection->setType('FRIEND');
        $connection->setShareScope(ShareScope::TITLES_ONLY);

        $this->em->persist($connection);
        $this->em->flush();

        $this->notifier->notify(
            $receiver,
            NotificationType::CONNECTION_REQUEST,
            'Nouvelle demande de partenariat',
            sprintf('%s souhaite devenir votre partenaire de redevabilité.', $user->getDisplayName()),
            '/social'
        );

        // 200 comme pour une adresse sans compte : la réponse ne révèle pas si un compte existe (même règle que le site)
        return $this->jsonNoStore(['success' => true]);
    }

    #[Route('/api/mobile/v1/partenaires/{id}/repondre', name: 'api_mobile_v1_partners_respond', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function respondPartner(int $id, Request $request): JsonResponse
    {
        $user = $this->currentMobileUser();
        $connection = $this->connectionRepo->find($id);

        if (!$connection || $connection->getReceiver() !== $user) {
            return $this->jsonError('Demande introuvable.', Response::HTTP_NOT_FOUND, 'NOT_FOUND');
        }

        $payload = $this->decodeJson($request);
        $action = strtoupper(trim((string) ($payload['action'] ?? '')));

        if ('ACCEPT' === $action) {
            $connection->setStatus('ACCEPTED');
            $this->em->flush();

            $this->notifier->notify(
                $connection->getRequester(),
                NotificationType::CONNECTION_ACCEPTED,
                'Partenariat accepté',
                sprintf('%s a accepté votre demande de partenariat.', $user->getDisplayName()),
                '/social'
            );

            return $this->jsonNoStore(['success' => true, 'status' => 'ACCEPTED']);
        }

        if ('REJECT' === $action) {
            // Comme sur le site : la demande refusée est supprimée, une nouvelle invitation reste possible plus tard
            $this->em->remove($connection);
            $this->em->flush();

            return $this->jsonNoStore(['success' => true, 'status' => 'REJECTED']);
        }

        return $this->jsonError('Action invalide (ACCEPT ou REJECT attendu).', Response::HTTP_BAD_REQUEST, 'INVALID_ACTION');
    }

    #[Route('/api/mobile/v1/defis', name: 'api_mobile_v1_challenges_list', methods: ['GET'])]
    public function listChallenges(): JsonResponse
    {
        $user = $this->currentMobileUser();

        $challenges = $this->challengeRepo->createQueryBuilder('c')
            ->orderBy('c.startDate', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $userParticipations = [];
        $participations = $this->em->getRepository(ChallengeParticipant::class)->findBy(['user' => $user]);
        foreach ($participations as $p) {
            $userParticipations[$p->getChallenge()->getId()] = [
                'participantId' => $p->getId(),
                'status' => $p->getStatus(),
                'progress' => $p->getProgress(),
            ];
        }

        $items = array_map(function (Challenge $c) use ($userParticipations) {
            $part = $userParticipations[$c->getId()] ?? null;

            return [
                'id' => $c->getId(),
                'slug' => $c->getSlug(),
                'title' => $c->getTitle(),
                'description' => $c->getDescription(),
                'type' => $c->getType(),
                'targetValue' => $c->getTargetValue(),
                'frequency' => $c->getFrequency(),
                'startDate' => $c->getStartDate()?->format('Y-m-d'),
                'endDate' => $c->getEndDate()?->format('Y-m-d'),
                'isParticipating' => null !== $part,
                'userProgress' => $part ? $part['progress'] : 0,
            ];
        }, $challenges);

        return $this->jsonNoStore([
            'total' => \count($items),
            'challenges' => $items,
        ]);
    }

    #[Route('/api/mobile/v1/couple', name: 'api_mobile_v1_couple_get', methods: ['GET'])]
    public function getCouple(): JsonResponse
    {
        $user = $this->currentMobileUser();
        $couple = $this->coupleRepo->findFor($user);

        if (null === $couple) {
            return $this->jsonNoStore([
                'hasCouple' => false,
            ]);
        }

        $partner = $couple->partnerOf($user);

        return $this->jsonNoStore([
            'hasCouple' => true,
            'id' => $couple->getId(),
            'isActive' => $couple->isActive(),
            'partnerName' => $partner?->getDisplayName() ?? $couple->getInvitedEmail(),
            'partnerEmail' => $partner?->getEmail() ?? $couple->getInvitedEmail(),
            'isInitiator' => $couple->getPartnerA() === $user,
            'inviteCode' => $couple->isActive() ? null : $couple->getInviteCode(),
            'vision' => $couple->getVision(),
            'verse' => $couple->getVerse(),
            'createdAt' => $couple->getCreatedAt()->format('Y-m-d'),
        ]);
    }
}
