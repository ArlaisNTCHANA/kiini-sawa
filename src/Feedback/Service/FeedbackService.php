<?php

namespace App\Feedback\Service;

use App\Compte\Entity\User;
use App\Feedback\Entity\CommunityFeedback;
use App\Feedback\Entity\FeedbackRequest;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Contenu\Service\SiteTexts;
use Doctrine\ORM\EntityManagerInterface;

/**
 * « Feedback nourricier » (chapitre 14 du livre) : le regard des proches. Le membre partage un lien ; chacun choisit
 * les qualités qu'il voit chez cette personne, ce qu'elle fait de mieux et un point où elle gagnerait à grandir.
 * Anonyme par défaut ; le détail n'apparaît qu'à partir de 3 réponses pour protéger l'anonymat.
 */
final class FeedbackService
{
    public const MIN_FOR_DETAIL = 3;
    public const MAX_RESPONSES = 50;
    public const MAX_QUALITIES = 5;
    /** Durée d'un lien, puis d'une prolongation (jours) ; un lien ne court jamais plus de 3 semaines devant lui. */
    public const DURATION_DAYS = 7;
    public const MAX_AHEAD_DAYS = 21;
    /** Rappel au membre si moins de MIN_FOR_DETAIL réponses après ce nombre de jours. */
    public const NUDGE_AFTER_DAYS = 3;

    /** Qualités en noms : justes pour tout le monde (l'application ne connaît pas le sexe des personnes). */
    public const QUALITIES = ['Fiabilité', 'Générosité', 'Écoute', 'Courage', 'Créativité', 'Sens de l\'organisation', 'Patience', 'Encouragement',
        'Honnêteté', 'Humilité', 'Joie', 'Persévérance', 'Sagesse', 'Bienveillance', 'Sens du rassemblement', 'Ardeur au travail', 'Fidélité', 'Calme',
        'Enthousiasme', 'Vision', 'Serviabilité', 'Humour', 'Discipline', 'Inspiration'];

    /** Anciennes qualités (adjectifs au masculin, avant le 24/11/2026) → nom correspondant, pour les réponses déjà reçues. */
    public const LEGACY_QUALITIES = ['Fiable' => 'Fiabilité', 'Généreux' => 'Générosité', 'À l\'écoute' => 'Écoute', 'Courageux' => 'Courage',
        'Créatif' => 'Créativité', 'Organisé' => 'Sens de l\'organisation', 'Patient' => 'Patience', 'Encourageant' => 'Encouragement',
        'Honnête' => 'Honnêteté', 'Humble' => 'Humilité', 'Joyeux' => 'Joie', 'Persévérant' => 'Persévérance', 'Sage' => 'Sagesse',
        'Bienveillant' => 'Bienveillance', 'Rassembleur' => 'Sens du rassemblement', 'Travailleur' => 'Ardeur au travail', 'Fidèle' => 'Fidélité',
        'Enthousiaste' => 'Enthousiasme', 'Visionnaire' => 'Vision', 'Serviable' => 'Serviabilité', 'Drôle' => 'Humour', 'Discipliné' => 'Discipline',
        'Inspirant' => 'Inspiration'];

    /** « Vous connaissez … par » : tournures neutres (ni « Conjoint(e) » ni « Ami(e) »). Les clés ne changent pas. */
    public const RELATIONSHIPS = ['famille' => 'La famille', 'conjoint' => 'Le couple', 'ami' => 'L\'amitié', 'collegue' => 'Le travail',
        'eglise' => 'L\'Église, la communauté', 'mentor' => 'Un accompagnement (mentorat, responsabilité)', 'autre' => 'Autre'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Notifier $notifier,
        private readonly SiteTexts $texts,
    ) {
    }

    /** La demande en cours : ni fermée, ni expirée. */
    public function activeRequest(User $user): ?FeedbackRequest
    {
        $latest = $this->latestRequest($user);

        return $latest && $latest->isOpen() ? $latest : null;
    }

    /** La dernière demande, quel que soit son état (ses résultats restent visibles après la fin du lien). */
    public function latestRequest(User $user): ?FeedbackRequest
    {
        return $this->em->getRepository(FeedbackRequest::class)->findOneBy(['user' => $user], ['id' => 'DESC']);
    }

    /** @return list<FeedbackRequest> toutes les demandes du membre, de la plus récente à la plus ancienne */
    public function requestsOf(User $user): array
    {
        return $this->em->getRepository(FeedbackRequest::class)->findBy(['user' => $user], ['id' => 'DESC']);
    }

    public function findByToken(string $token): ?FeedbackRequest
    {
        return $this->em->getRepository(FeedbackRequest::class)->findOneBy(['token' => $token]);
    }

    public function findOpen(string $token): ?FeedbackRequest
    {
        $request = $this->findByToken($token);

        return $request && $request->isOpen() ? $request : null;
    }

    public function open(User $user, bool $forceRealName): FeedbackRequest
    {
        if ($existing = $this->activeRequest($user)) {
            $existing->setForceRealName($forceRealName);
            $this->em->flush();

            return $existing;
        }
        $request = (new FeedbackRequest())->setUser($user)->setToken(bin2hex(random_bytes(12)))->setForceRealName($forceRealName)->setIsActive(true)
            ->setExpiresAt(new \DateTimeImmutable('+'.self::DURATION_DAYS.' days'));
        $this->em->persist($request);
        $this->em->flush();

        return $request;
    }

    /** Une semaine de plus (à partir d'aujourd'hui si le lien a déjà expiré). Impossible pour une demande fermée. */
    public function extend(FeedbackRequest $request): bool
    {
        if (!$request->isActive() || !$this->canExtend($request)) {
            return false;
        }
        $now = new \DateTimeImmutable();
        $from = $request->getExpiresAt() && $request->getExpiresAt() > $now ? $request->getExpiresAt() : $now;
        $request->setExpiresAt($from->modify('+'.self::DURATION_DAYS.' days'));
        $this->em->flush();

        return true;
    }

    public function canExtend(FeedbackRequest $request): bool
    {
        $now = new \DateTimeImmutable();
        $from = $request->getExpiresAt() && $request->getExpiresAt() > $now ? $request->getExpiresAt() : $now;

        return $request->isActive() && $from->modify('+'.self::DURATION_DAYS.' days') <= $now->modify('+'.self::MAX_AHEAD_DAYS.' days');
    }

    public function close(FeedbackRequest $request): void
    {
        $request->setIsActive(false);
        $this->em->flush();
    }

    /**
     * Le message prêt à envoyer (WhatsApp, SMS, e-mail), en « tu » ou en « vous ».
     */
    public function shareMessage(FeedbackRequest $request, string $url, string $tone = 'tu'): string
    {
        $tone = 'vous' === $tone ? 'vous' : 'tu';
        $defaults = FeedbackTexts::defaults();
        $anonymous = $request->isForceRealName() ? '' : $this->text('feedback.message.'.$tone.'.anonyme');

        return strtr($this->texts->get('feedback.message.'.$tone, $defaults['feedback.message.'.$tone]), [
            '{anonymat}' => $anonymous,
            '{date}' => self::frenchDate($request->getExpiresAt()),
            '{lien}' => $url,
        ]);
    }

    public function text(string $code, array $vars = []): string
    {
        $value = $this->texts->get($code, FeedbackTexts::defaults()[$code] ?? '');
        foreach ($vars as $k => $v) {
            $value = str_replace('{'.$k.'}', (string) $v, $value);
        }

        return $value;
    }

    /** « mardi 1er décembre » ; « dans une semaine » pour une ancienne demande sans date. */
    public static function frenchDate(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return 'dans une semaine';
        }
        $day = (int) $date->format('j');
        $weekday = (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'EEEE'))->format($date);
        $month = (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMMM'))->format($date);

        return sprintf('%s %s %s', $weekday, 1 === $day ? '1er' : (string) $day, $month);
    }

    /**
     * Tâche quotidienne : prévenir le membre quand son lien arrive à son terme, et l'encourager à relancer ses proches
     * si peu de réponses sont arrivées après quelques jours. Sans doublon (clé de notification).
     *
     * @return array{fin: int, relance: int}
     */
    public function runDailyChecks(\DateTimeImmutable $today): array
    {
        $sent = ['fin' => 0, 'relance' => 0];
        $now = $today->setTime((int) date('H'), (int) date('i'));
        $repo = $this->em->getRepository(FeedbackRequest::class);

        $ended = $repo->createQueryBuilder('r')->where('r.isActive = true')->andWhere('r.expiresAt <= :now')->andWhere('r.expiresAt > :since')
            ->setParameter('now', $now)->setParameter('since', $now->modify('-3 days'))->getQuery()->getResult();
        foreach ($ended as $request) {
            $count = $this->responseCount($request);
            if ($this->notifier->notify($request->getUser(), NotificationType::FEEDBACK_RECEIVED, $this->text('feedback.notif.fin.titre'),
                $this->text('feedback.notif.fin.texte', ['nombre' => $count]), '/mes-tests/feedback', 'feedback-fin-'.$request->getId().'-'.$request->getExpiresAt()->format('Ymd'))) {
                ++$sent['fin'];
            }
        }

        $waiting = $repo->createQueryBuilder('r')->where('r.isActive = true')->andWhere('r.expiresAt > :now')->andWhere('r.createdAt <= :before')
            ->setParameter('now', $now)->setParameter('before', $now->modify('-'.self::NUDGE_AFTER_DAYS.' days'))->getQuery()->getResult();
        foreach ($waiting as $request) {
            $count = $this->responseCount($request);
            if ($count >= self::MIN_FOR_DETAIL) {
                continue;
            }
            if ($this->notifier->notify($request->getUser(), NotificationType::FEEDBACK_RECEIVED, $this->text('feedback.notif.relance.titre'),
                $this->text('feedback.notif.relance.texte', ['nombre' => $count, 'seuil' => self::MIN_FOR_DETAIL]), '/mes-tests/feedback', 'feedback-relance-'.$request->getId())) {
                ++$sent['relance'];
            }
        }

        return $sent;
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
        if (!$request->isOpen()) { $errors[] = 'Ce lien n\'accepte plus de réponses.'; }
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
        $this->notifier->notify($request->getUser(), NotificationType::FEEDBACK_RECEIVED, $this->text('feedback.notif.reponse.titre'),
            $count >= self::MIN_FOR_DETAIL || $request->isForceRealName() ? $this->text('feedback.notif.reponse.detail')
                : $this->text('feedback.notif.reponse.attente', ['nombre' => $count, 'seuil' => self::MIN_FOR_DETAIL]),
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
                $q = self::LEGACY_QUALITIES[$q] ?? $q;
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
