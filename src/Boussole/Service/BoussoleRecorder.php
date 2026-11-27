<?php

namespace App\Boussole\Service;

use App\Boussole\Entity\BoussoleResult;
use App\Boussole\Repository\BoussoleResultRepository;
use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Enregistre les passations de la Boussole 360.
 *
 * - Connecté : la passation est recalculée côté serveur puis enregistrée tout de suite.
 * - Visiteur : les réponses sont gardées dans la session (clé PENDING_KEY) ; elles sont enregistrées dans le compte
 *   dès que la personne est connectée (après inscription ou connexion), par BoussolePendingSubscriber.
 */
final class BoussoleRecorder
{
    public const PENDING_KEY = 'boussole_reponses_en_attente';
    /** Une même passation envoyée deux fois de suite (double clic, rechargement) n'est pas dupliquée. */
    private const DUPLICATE_WINDOW_SECONDS = 600;

    public function __construct(
        private readonly BoussoleEngine $engine,
        private readonly EntityManagerInterface $em,
        private readonly BoussoleResultRepository $results,
    ) {
    }

    /**
     * @param list<int> $answers réponses déjà validées par BoussoleEngine::normalizeAnswers()
     */
    public function record(User $user, array $answers, string $source): BoussoleResult
    {
        $latest = $this->results->latestFor($user);
        if (null !== $latest && $latest->getAnswers() === $answers
            && $latest->getCreatedAt() > new \DateTimeImmutable('-'.self::DUPLICATE_WINDOW_SECONDS.' seconds')) {
            return $latest;
        }

        $result = new BoussoleResult($user, $answers, $this->engine->evaluate($answers), $source);
        $this->em->persist($result);
        $this->em->flush();

        return $result;
    }

    /** @param list<int> $answers */
    public function keepPending(SessionInterface $session, array $answers): void
    {
        $session->set(self::PENDING_KEY, $answers);
        $this->count('visitors');
    }

    public function hasPending(SessionInterface $session): bool
    {
        return null !== BoussoleEngine::normalizeAnswers($session->get(self::PENDING_KEY));
    }

    /** Enregistre la passation en attente dans le compte, s'il y en a une. */
    public function flushPending(SessionInterface $session, User $user): ?BoussoleResult
    {
        if (!$session->has(self::PENDING_KEY)) {
            return null;
        }
        $answers = BoussoleEngine::normalizeAnswers($session->get(self::PENDING_KEY));
        $session->remove(self::PENDING_KEY);

        if (null === $answers) {
            return null;
        }
        $created = $user->getCreatedAt();
        $this->count(null !== $created && $created > new \DateTimeImmutable('-1 day') ? 'new_members' : 'returning_members');

        return $this->record($user, $answers, BoussoleResult::SOURCE_WEB);
    }

    /** Compteur anonyme du jour (BoussoleDaily) ; jamais bloquant pour le visiteur. */
    private function count(string $column): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                'INSERT INTO boussole_daily (day, visitors, new_members, returning_members) VALUES (CURRENT_DATE, 0, 0, 0) ON DUPLICATE KEY UPDATE day = day');
            $this->em->getConnection()->executeStatement('UPDATE boussole_daily SET '.$column.' = '.$column.' + 1 WHERE day = CURRENT_DATE');
        } catch (\Throwable) {
        }
    }

    /**
     * Résultat complet (lecture recalculée) d'une passation enregistrée.
     *
     * @return array<string, mixed>
     */
    public function evaluate(BoussoleResult $result): array
    {
        return $this->engine->evaluate($result->getAnswers());
    }
}
