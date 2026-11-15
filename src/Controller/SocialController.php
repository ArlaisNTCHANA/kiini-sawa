<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Connection;
use App\Entity\GoalSuggestion;
use App\Entity\Notification;
use App\Entity\Comment;
use App\Entity\GrowthGoal;
use App\Entity\PlanDeCroissance;
use App\Enum\NotificationType;
use App\Enum\GrowthAxis;
use App\Enum\ShareScope;
use App\Form\AddConnectionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SocialController extends AbstractController
{
    #[Route('/social', name: 'app_social')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Standard Symfony form rendering support
        $form = $this->createForm(AddConnectionType::class);

        // Load connections
        $connectionsRepo = $em->getRepository(Connection::class);
        
        // Requester connections (sent by me)
        $sentConnections = $connectionsRepo->findBy(['requester' => $user]);
        // Receiver connections (received by me)
        $receivedConnections = $connectionsRepo->findBy(['receiver' => $user]);

        $pendingReceived = [];
        $pendingSent = [];
        $activePartners = [];

        foreach ($receivedConnections as $c) {
            if ($c->getStatus() === 'PENDING') {
                $pendingReceived[] = [
                    'id' => $c->getId(),
                    'partnerId' => $c->getRequester()->getId(),
                    'partnerEmail' => $c->getRequester()->getEmail(),
                    'createdAt' => $c->getCreatedAt()->format('Y-m-d H:i'),
                ];
            } elseif ($c->getStatus() === 'ACCEPTED') {
                $activePartners[] = $this->formatActivePartner($c, $user, false);
            }
        }

        foreach ($sentConnections as $c) {
            if ($c->getStatus() === 'PENDING') {
                $pendingSent[] = [
                    'id' => $c->getId(),
                    'partnerId' => $c->getReceiver()->getId(),
                    'partnerEmail' => $c->getReceiver()->getEmail(),
                    'createdAt' => $c->getCreatedAt()->format('Y-m-d H:i'),
                ];
            } elseif ($c->getStatus() === 'ACCEPTED') {
                $activePartners[] = $this->formatActivePartner($c, $user, true);
            }
        }

        // Load goal suggestions
        $suggestions = $em->getRepository(GoalSuggestion::class)->findBy(
            ['toUser' => $user, 'status' => 'PENDING'],
            ['createdAt' => 'DESC']
        );
        $suggestionsJson = [];
        foreach ($suggestions as $s) {
            $suggestionsJson[] = [
                'id' => $s->getId(),
                'fromEmail' => $s->getFromUser()->getEmail(),
                'title' => $s->getTitle(),
                'description' => $s->getDescription(),
                'category' => $s->getCategory(),
                'createdAt' => $s->getCreatedAt()->format('Y-m-d'),
            ];
        }

        // Load comments (activity feed)
        $comments = $em->getRepository(Comment::class)->findBy(
            ['targetUser' => $user],
            ['createdAt' => 'DESC'],
            15
        );
        $commentsJson = [];
        foreach ($comments as $comment) {
            $commentsJson[] = [
                'id' => $comment->getId(),
                'authorEmail' => $comment->getAuthor()->getEmail(),
                'content' => $comment->getContent(),
                'section' => $comment->getSection(),
                'createdAt' => $comment->getCreatedAt()->format('d/m H:i'),
            ];
        }

        return $this->render('social/index.html.twig', [
            'form' => $form->createView(),
            'pendingReceived' => $pendingReceived,
            'pendingSent' => $pendingSent,
            'activePartners' => $activePartners,
            'suggestions' => $suggestionsJson,
            'comments' => $commentsJson,
        ]);
    }

    #[Route('/api/social/connection/add', name: 'api_social_connection_add', methods: ['POST'])]
    public function addConnection(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['success' => false, 'error' => 'L\'adresse email est manquante.'], Response::HTTP_BAD_REQUEST);
        }

        if ($email === $user->getEmail()) {
            return new JsonResponse(['success' => false, 'error' => 'Vous ne pouvez pas vous connecter avec vous-même.'], Response::HTTP_BAD_REQUEST);
        }

        $receiver = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$receiver) {
            return new JsonResponse(['success' => false, 'error' => 'Aucun utilisateur trouvé avec cette adresse email.'], Response::HTTP_NOT_FOUND);
        }

        // Check if connection already exists
        $connRepo = $em->getRepository(Connection::class);
        $existing = $connRepo->findOneBy(['requester' => $user, 'receiver' => $receiver]);
        if (!$existing) {
            $existing = $connRepo->findOneBy(['requester' => $receiver, 'receiver' => $user]);
        }

        if ($existing) {
            return new JsonResponse(['success' => false, 'error' => 'Une demande de connexion existe déjà avec cet utilisateur.'], Response::HTTP_BAD_REQUEST);
        }

        // Create connection
        $connection = new Connection();
        $connection->setRequester($user);
        $connection->setReceiver($receiver);
        $connection->setStatus('PENDING');
        $connection->setType('FRIEND');
        $connection->setShareScope(ShareScope::TITLES_ONLY);

        $em->persist($connection);

        // Notification for receiver
        $notif = new Notification();
        $notif->setUser($receiver);
        $notif->setType(NotificationType::CONNECTION_REQUEST);
        $notif->setTitle('Nouvelle demande de connexion');
        $notif->setMessage(sprintf('%s vous a envoyé une demande de connexion.', $user->getEmail()));
        $notif->setActionUrl('/social');
        $em->persist($notif);

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/social/connection/{id}/respond', name: 'api_social_connection_respond', methods: ['POST'])]
    public function respondConnection(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $connection = $em->getRepository(Connection::class)->find($id);
        if (!$connection || $connection->getReceiver() !== $user) {
            return new JsonResponse(['success' => false, 'error' => 'Invitation introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;

        if ($action === 'ACCEPT') {
            $connection->setStatus('ACCEPTED');

            // Notify requester
            $notif = new Notification();
            $notif->setUser($connection->getRequester());
            $notif->setType(NotificationType::CONNECTION_ACCEPTED);
            $notif->setTitle('Demande de connexion acceptée');
            $notif->setMessage(sprintf('%s a accepté votre demande de connexion.', $user->getEmail()));
            $notif->setActionUrl('/social');
            $em->persist($notif);

            $em->flush();
            return new JsonResponse(['success' => true]);
        } elseif ($action === 'REJECT') {
            $em->remove($connection);
            $em->flush();
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'error' => 'Action invalide.'], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/api/social/connection/{id}/permissions', name: 'api_social_connection_permissions', methods: ['POST'])]
    public function updatePermissions(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $connection = $em->getRepository(Connection::class)->find($id);
        if (!$connection || ($connection->getRequester() !== $user && $connection->getReceiver() !== $user)) {
            return new JsonResponse(['success' => false, 'error' => 'Connexion introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        if ($connection->getRequester() === $user) {
            $connection->setRequesterShareLifePlan((bool)($data['lifePlan'] ?? false));
            $connection->setRequesterShareGrowthPlan((bool)($data['growthPlan'] ?? false));
            $connection->setRequesterShareHabits((bool)($data['habits'] ?? false));
            $connection->setRequesterShareReviews((bool)($data['reviews'] ?? false));
            $connection->setRequesterShareDiscovery((bool)($data['discovery'] ?? false));
            $connection->setRequesterShareCroissance((bool)($data['croissance'] ?? false));
            $connection->setRequesterShareDeveloppement((bool)($data['developpement'] ?? false));
            $connection->setRequesterShareHygiene((bool)($data['hygiene'] ?? false));
            $connection->setRequesterShareTransmission((bool)($data['transmission'] ?? false));
            $connection->setRequesterShareBilan((bool)($data['bilan'] ?? false));
        } else {
            $connection->setReceiverShareLifePlan((bool)($data['lifePlan'] ?? false));
            $connection->setReceiverShareGrowthPlan((bool)($data['growthPlan'] ?? false));
            $connection->setReceiverShareHabits((bool)($data['habits'] ?? false));
            $connection->setReceiverShareReviews((bool)($data['reviews'] ?? false));
            $connection->setReceiverShareDiscovery((bool)($data['discovery'] ?? false));
            $connection->setReceiverShareCroissance((bool)($data['croissance'] ?? false));
            $connection->setReceiverShareDeveloppement((bool)($data['developpement'] ?? false));
            $connection->setReceiverShareHygiene((bool)($data['hygiene'] ?? false));
            $connection->setReceiverShareTransmission((bool)($data['transmission'] ?? false));
            $connection->setReceiverShareBilan((bool)($data['bilan'] ?? false));
        }

        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/social/suggestion/{id}/respond', name: 'api_social_suggestion_respond', methods: ['POST'])]
    public function respondSuggestion(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $suggestion = $em->getRepository(GoalSuggestion::class)->find($id);
        if (!$suggestion || $suggestion->getToUser() !== $user) {
            return new JsonResponse(['success' => false, 'error' => 'Suggestion introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;

        if ($action === 'ACCEPT') {
            $suggestion->setStatus('ACCEPTED');

            // Create GrowthGoal
            $plan = $user->getPlanDeCroissance();
            if (!$plan) {
                $plan = new PlanDeCroissance();
                $plan->setUser($user);
                $em->persist($plan);
            }

            $axis = GrowthAxis::tryFrom($suggestion->getCategory()) ?? GrowthAxis::COMPETENCE;

            $goal = new GrowthGoal();
            $goal->setPlanDeCroissance($plan);
            $goal->setTitle($suggestion->getTitle());
            $goal->setAxis($axis);
            $goal->setStrategy($suggestion->getDescription() ?? '');
            $goal->setSourceType('SUGGESTION');
            $goal->setSourceId($suggestion->getId());
            $goal->setProgress(0);

            $em->persist($goal);
            $em->flush();

            return new JsonResponse(['success' => true]);
        } elseif ($action === 'REJECT') {
            $suggestion->setStatus('REJECTED');
            $em->flush();
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'error' => 'Action invalide.'], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/api/social/partner/{id}/shared-data', name: 'api_social_partner_shared_data', methods: ['GET'])]
    public function getPartnerSharedData(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $partner = $em->getRepository(User::class)->find($id);
        if (!$partner) {
            return new JsonResponse(['success' => false, 'error' => 'Partenaire introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $connRepo = $em->getRepository(Connection::class);
        $connection = $connRepo->findOneBy(['requester' => $user, 'receiver' => $partner, 'status' => 'ACCEPTED']);
        $isRequester = true;
        if (!$connection) {
            $connection = $connRepo->findOneBy(['requester' => $partner, 'receiver' => $user, 'status' => 'ACCEPTED']);
            $isRequester = false;
        }

        if (!$connection) {
            return new JsonResponse(['success' => false, 'error' => 'Vous n\'êtes pas connecté avec cet utilisateur.'], Response::HTTP_FORBIDDEN);
        }

        // Determine what partner shares with current user
        $sharesLife = $isRequester ? $connection->isReceiverShareLifePlan() : $connection->isRequesterShareLifePlan();
        $sharesGrowth = $isRequester ? $connection->isReceiverShareGrowthPlan() : $connection->isRequesterShareGrowthPlan();

        $sharedData = [
            'partnerEmail' => $partner->getEmail(),
            'lifePlan' => null,
            'growthPlan' => null,
        ];

        if ($sharesLife && $partner->getPlanDeVie()) {
            $pv = $partner->getPlanDeVie();
            $goals = [];
            foreach ($pv->getLifeGoals() as $g) {
                $goals[] = [
                    'id' => $g->getId(),
                    'title' => $g->getTitle(),
                    'category' => $g->getCategory(),
                    'status' => $g->getStatus(),
                ];
            }
            $sharedData['lifePlan'] = [
                'purpose' => $pv->getPurpose() ? $pv->getPurpose()->getStatement() : null,
                'vision' => $pv->getVision() ? $pv->getVision()->getContent() : null,
                'goals' => $goals,
            ];
        }

        if ($sharesGrowth && $partner->getPlanDeCroissance()) {
            $pc = $partner->getPlanDeCroissance();
            $goals = [];
            foreach ($pc->getGrowthGoals() as $g) {
                $goals[] = [
                    'id' => $g->getId(),
                    'title' => $g->getTitle(),
                    'axis' => $g->getAxis()->value,
                    'progress' => $g->getProgress(),
                    'strategy' => $g->getStrategy(),
                ];
            }
            $sharedData['growthPlan'] = [
                'goals' => $goals,
            ];
        }

        return new JsonResponse(['success' => true, 'sharedData' => $sharedData]);
    }

    private function formatActivePartner(Connection $c, User $currentUser, bool $isCurrentUserRequester): array
    {
        $partner = $isCurrentUserRequester ? $c->getReceiver() : $c->getRequester();
        
        // What do I share with them?
        $myShare = [
            'lifePlan' => $isCurrentUserRequester ? $c->isRequesterShareLifePlan() : $c->isReceiverShareLifePlan(),
            'growthPlan' => $isCurrentUserRequester ? $c->isRequesterShareGrowthPlan() : $c->isReceiverShareGrowthPlan(),
            'habits' => $isCurrentUserRequester ? $c->isRequesterShareHabits() : $c->isReceiverShareHabits(),
            'reviews' => $isCurrentUserRequester ? $c->isRequesterShareReviews() : $c->isReceiverShareReviews(),
            'discovery' => $isCurrentUserRequester ? $c->isRequesterShareDiscovery() : $c->isReceiverShareDiscovery(),
            'croissance' => $isCurrentUserRequester ? $c->isRequesterShareCroissance() : $c->isReceiverShareCroissance(),
            'developpement' => $isCurrentUserRequester ? $c->isRequesterShareDeveloppement() : $c->isReceiverShareDeveloppement(),
            'hygiene' => $isCurrentUserRequester ? $c->isRequesterShareHygiene() : $c->isReceiverShareHygiene(),
            'transmission' => $isCurrentUserRequester ? $c->isRequesterShareTransmission() : $c->isReceiverShareTransmission(),
            'bilan' => $isCurrentUserRequester ? $c->isRequesterShareBilan() : $c->isReceiverShareBilan(),
        ];

        // What do they share with me?
        $partnerShare = [
            'lifePlan' => $isCurrentUserRequester ? $c->isReceiverShareLifePlan() : $c->isRequesterShareLifePlan(),
            'growthPlan' => $isCurrentUserRequester ? $c->isReceiverShareGrowthPlan() : $c->isRequesterShareGrowthPlan(),
        ];

        return [
            'connectionId' => $c->getId(),
            'partnerId' => $partner->getId(),
            'partnerEmail' => $partner->getEmail(),
            'myShare' => $myShare,
            'partnerShare' => $partnerShare,
        ];
    }
}
