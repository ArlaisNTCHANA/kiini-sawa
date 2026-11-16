<?php

namespace App\Feedback\Service;

use App\Compte\Entity\User;
use App\Feedback\Entity\CommunityFeedback;
use App\Feedback\Entity\FeedbackRequest;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;

/**
 * « Feedback nourricier » (chapitre 14 du livre) : le regard des proches. Le membre partage un lien ; chacun choisit
 * les qualités qu'il voit en lui, dit ce qu'il fait de mieux et un point où il gagnerait à grandir.
 * Anonyme par défaut ; le détail n'apparaît qu'à partir de 3 réponses pour protéger l'anonymat.
 */
final class FeedbackService
{
    public const MIN_FOR_DETAIL = 3;
    public const MAX_RESPONSES = 50;
    public const MAX_QUALITIES = 5;

    public const QUALITIES = ['Fiable', 'Généreux', 'À l\'écoute', 'Courageux', 'Créatif', 'Organisé', 'Patient', 'Encourageant',
        'Honnête', 'Humble', 'Joyeux', 'Persévérant', 'Sage', 'Bienveillant', 'Rassembleur', 'Travailleur', 'Fidèle', 'Calme',
        'Enthousiaste', 'Visionnaire', 'Serviable', 'Drôle', 'Discipliné', 'Inspirant'];

    public const RELATIONSHIPS = ['famille' => 'Famille', 'conjoint' => 'Conjoint(e)', 'ami' => 'Ami(e)', 'collegue' => 'Collègue',
        'eglise' => 'Église, communauté', 'mentor' => 'Mentor, responsable', 'autre' => 'Autre'];

    public function __construct(private readonly EntityManagerInterface $em, private readonly Notifier $notifier)
    {
    }

    public function activeRequest(User $user): ?FeedbackRequest
    {
        return $this->em->getRepository(FeedbackRequest::class)->findOneBy(['user' => $user, 'isActive' => true], ['id' => 'DESC']);
    }

    public function findOpen(string $token): ?FeedbackRequest
    {
        return $this->em->getRepository(FeedbackRequest::class)->findOneBy(['token' => $token, 'isActive' => true]);
    }

    public function open(User $user, bool $forceRealName): FeedbackRequest
    {
        if ($existing = $this->activeRequest($user)) {
            return $existing->setForceRealName($forceRealName);
        }
        $request = (new FeedbackRequest())->setUser($user)->setToken(bin2hex(random_bytes(12)))->setForceRealName($forceRealName)->setIsActive(true);
        $this->em->persist($request);
        $this->em->flush();

        return $request;
    }

    public function close(FeedbackRequest $request): void
    {
        $request->setIsActive(false);
        $this->em->flush();
    }

    public function responseCount(FeedbackRequest $request): int
    {
        return $this->em->getRepository(CommunityFeedback::class)->count(['feedbackRequest' => $request]);
    }

    /**
     * @param array{relationship?: string, qualities?: list<string>, strengths?: string, improvements?: string, name?: string} $data
     *
     * @return list<string> erreurs (vide = enregistré)
     */
    public function submit(FeedbackRequest $request, array $data): array
    {
        $qualities = array_values(array_intersect(self::QUALITIES, (array) ($data['qualities'] ?? [])));
        $strengths = mb_substr(trim((string) ($data['strengths'] ?? '')), 0, 2000);
        $improvements = mb_substr(trim((string) ($data['improvements'] ?? '')), 0, 2000);
        $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 100);
        $relationship = isset(self::RELATIONSHIPS[$data['relationship'] ?? '']) ? $data['relationship'] : 'autre';

        $errors = [];
        if ([] === $qualities) { $errors[] = 'Choisissez au moins une qualité.'; }
        if (\count($qualities) > self::MAX_QUALITIES) { $errors[] = 'Choisissez au plus '.self::MAX_QUALITIES.' qualités.'; }
        if ('' === $strengths) { $errors[] = 'Dites ce que cette personne fait de mieux.'; }
        if ($request->isForceRealName() && '' === $name) { $errors[] = 'Indiquez votre prénom.'; }
        if ($this->responseCount($request) >= self::MAX_RESPONSES) { $errors[] = 'Cette demande a reçu le nombre maximal de réponses.'; }
        if ([] !== $errors) {
            return $errors;
        }

        $feedback = (new CommunityFeedback())->setUser($request->getUser())->setFeedbackRequest($request)
            ->setRelationship($relationship)->setQualities(json_encode($qualities, \JSON_UNESCAPED_UNICODE))
            ->setStrengths($strengths)->setImprovements('' !== $improvements ? $improvements : null)
            ->setIsAnonymous('' === $name)->setRespondentName('' !== $name ? $name : null);
        $this->em->persist($feedback);
        $this->em->flush();

        $count = $this->responseCount($request);
        $this->notifier->notify($request->getUser(), NotificationType::FEEDBACK_RECEIVED, 'Un nouveau regard sur vous',
            $count >= self::MIN_FOR_DETAIL ? 'Quelqu\'un a répondu à votre demande de feedback. Découvrez ce que vos proches voient en vous.'
                : sprintf('Quelqu\'un a répondu à votre demande de feedback (%d sur %d avant de voir le détail).', $count, self::MIN_FOR_DETAIL),
            '/mes-tests/feedback');

        return [];
    }

    /**
     * Synthèse : qualités les plus citées, forces, pistes de croissance, répartition des liens.
     *
     * @return array{count: int, detail: bool, qualities: array<string, int>, strengths: list<array{text: string, from: ?string}>, improvements: list<array{text: string, from: ?string}>, relationships: array<string, int>}
     */
    public function summary(FeedbackRequest $request): array
    {
        $rows = $this->em->getRepository(CommunityFeedback::class)->findBy(['feedbackRequest' => $request], ['createdAt' => 'ASC']);
        $qualities = $relationships = [];
        $strengths = $improvements = [];
        foreach ($rows as $f) {
            foreach ((array) json_decode((string) $f->getQualities(), true) as $q) {
                $qualities[$q] = ($qualities[$q] ?? 0) + 1;
            }
            $rel = self::RELATIONSHIPS[$f->getRelationship()] ?? 'Autre';
            $relationships[$rel] = ($relationships[$rel] ?? 0) + 1;
            $from = $f->isAnonymous() ? null : $f->getRespondentName();
            if ($f->getStrengths()) { $strengths[] = ['text' => $f->getStrengths(), 'from' => $from]; }
            if ($f->getImprovements()) { $improvements[] = ['text' => $f->getImprovements(), 'from' => $from]; }
        }
        arsort($qualities);
        // Ordre mélangé pour ne pas pouvoir relier une réponse à son auteur par l'heure d'arrivée
        shuffle($strengths);
        shuffle($improvements);
        $detail = \count($rows) >= self::MIN_FOR_DETAIL || $request->isForceRealName();

        return ['count' => \count($rows), 'detail' => $detail, 'qualities' => $detail ? $qualities : [],
            'strengths' => $detail ? $strengths : [], 'improvements' => $detail ? $improvements : [], 'relationships' => $relationships];
    }
}
