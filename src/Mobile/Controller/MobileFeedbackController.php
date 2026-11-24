<?php

namespace App\Mobile\Controller;

use App\Compte\Entity\User;
use App\Compte\Entity\UserProfile;
use App\Feedback\Entity\FeedbackRequest;
use App\Feedback\Service\FeedbackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Feedback nourricier dans l'application mobile : le même lien que sur le site (une semaine, prolongeable), les messages
 * prêts à partager (WhatsApp…), la synthèse et les demandes passées. Les proches répondent toujours sur le site.
 */
#[Route('/api/mobile/v1/feedback')]
final class MobileFeedbackController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(private readonly FeedbackService $feedback, private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'api_mobile_v1_feedback', methods: ['GET'])]
    public function show(): JsonResponse
    {
        return $this->jsonNoStore($this->state($this->currentMobileUser()));
    }

    /** Créer un lien (ou un nouveau lien après la fin du précédent). Corps : { forceRealName?, firstName? }. */
    #[Route('', name: 'api_mobile_v1_feedback_open', methods: ['POST'])]
    public function open(Request $request): JsonResponse
    {
        $user = $this->currentMobileUser();
        $body = $this->decodeJson($request) ?? [];
        if ('' === trim((string) $user->getUserProfile()?->getFirstName())) {
            $firstName = mb_substr(trim(strip_tags((string) ($body['firstName'] ?? ''))), 0, 60);
            if ('' === $firstName) {
                return $this->jsonError('Indiquez votre prénom : c\'est lui que vos proches verront.', Response::HTTP_UNPROCESSABLE_ENTITY, 'first_name_required');
            }
            $profile = $user->getUserProfile() ?? (new UserProfile())->setUser($user);
            $profile->setFirstName($firstName);
            $user->setUserProfile($profile);
            $this->em->persist($profile);
            $this->em->flush();
        }
        $this->feedback->open($user, (bool) ($body['forceRealName'] ?? false));

        return $this->jsonNoStore($this->state($user), Response::HTTP_CREATED);
    }

    #[Route('/prolonger', name: 'api_mobile_v1_feedback_extend', methods: ['POST'])]
    public function extend(): JsonResponse
    {
        $user = $this->currentMobileUser();
        $current = $this->feedback->latestRequest($user);
        if (null === $current || !$this->feedback->extend($current)) {
            return $this->jsonError('Ce lien ne peut pas être prolongé davantage. Vous pouvez en créer un nouveau.', Response::HTTP_CONFLICT);
        }

        return $this->jsonNoStore($this->state($user));
    }

    #[Route('/fermer', name: 'api_mobile_v1_feedback_close', methods: ['POST'])]
    public function close(): JsonResponse
    {
        $user = $this->currentMobileUser();
        if ($open = $this->feedback->activeRequest($user)) {
            $this->feedback->close($open);
        }

        return $this->jsonNoStore($this->state($user));
    }

    #[Route('/demandes/{id}', name: 'api_mobile_v1_feedback_past', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function past(int $id): JsonResponse
    {
        $request = $this->em->find(FeedbackRequest::class, $id);
        if (null === $request || $request->getUser() !== $this->currentMobileUser()) {
            return $this->jsonError('Demande introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->jsonNoStore(['createdAt' => $request->getCreatedAt()?->format(\DATE_ATOM), 'summary' => $this->summary($request)]);
    }

    /** @return array<string, mixed> */
    private function state(User $user): array
    {
        $requests = $this->feedback->requestsOf($user);
        $current = $requests[0] ?? null;
        $data = [
            'request' => null,
            'messages' => null,
            'summary' => null,
            'past' => array_map(fn (FeedbackRequest $r) => ['id' => $r->getId(), 'createdAt' => $r->getCreatedAt()?->format(\DATE_ATOM), 'count' => $this->feedback->responseCount($r)], \array_slice($requests, 1)),
            'needsFirstName' => '' === trim((string) $user->getUserProfile()?->getFirstName()),
            'minForDetail' => FeedbackService::MIN_FOR_DETAIL,
            'durationDays' => FeedbackService::DURATION_DAYS,
        ];
        if (null === $current) {
            return $data;
        }
        $url = $this->generateUrl('app_feedback_form', ['token' => $current->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        $data['request'] = [
            'id' => $current->getId(),
            'url' => $url,
            'open' => $current->isOpen(),
            'closed' => !$current->isActive(),
            'forceRealName' => $current->isForceRealName(),
            'expiresAt' => $current->getExpiresAt()?->format(\DATE_ATOM),
            'expiresLabel' => FeedbackService::frenchDate($current->getExpiresAt()),
            'canExtend' => $this->feedback->canExtend($current),
            'createdAt' => $current->getCreatedAt()?->format(\DATE_ATOM),
        ];
        if ($current->isOpen()) {
            $data['messages'] = ['tu' => $this->feedback->shareMessage($current, $url, 'tu'), 'vous' => $this->feedback->shareMessage($current, $url, 'vous')];
        }
        $data['summary'] = $this->summary($current);

        return $data;
    }

    /** @return array<string, mixed> */
    private function summary(FeedbackRequest $request): array
    {
        $s = $this->feedback->summary($request);

        return [
            'count' => $s['count'],
            'detail' => $s['detail'],
            'qualities' => array_map(static fn (string $q, int $n) => ['label' => $q, 'count' => $n], array_keys($s['qualities']), array_values($s['qualities'])),
            'words' => array_map(static fn (string $w, int $n) => ['label' => $w, 'count' => $n], array_keys($s['words']), array_values($s['words'])),
            'notWords' => array_map(static fn (string $w, int $n) => ['label' => $w, 'count' => $n], array_keys($s['notWords']), array_values($s['notWords'])),
            'strengths' => $s['strengths'],
            'improvements' => $s['improvements'],
        ];
    }
}
