<?php

namespace App\PlanDIntendance\Service;

use App\Compte\Entity\User;
use App\PlanDeVie\Entity\InternalBalance;
use App\PlanDeVie\Entity\Season;
use App\PlanDeVie\Service\TestResultsForPlans;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\PlanDIntendance\Repository\StewardshipItemRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Inventaire du Plan d'Intendance : ce qui est déjà saisi, et ce que le Plan de Vie et les tests proposent
 * (« Le Plan de Vie alimente presque tous les autres plans », chapitre 18). Rien n'est à ressaisir.
 */
final class StewardshipInventory
{
    public function __construct(
        private readonly StewardshipItemRepository $items,
        private readonly TestResultsForPlans $testResults,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{byOrigin: array<string, list<StewardshipItem>>, priorities: list<StewardshipItem>, count: int, evaluated: int}
     */
    public function overview(User $user): array
    {
        $byOrigin = array_fill_keys(array_merge(array_keys(Stewardship::LIFE_PLAN), array_keys(Stewardship::DOMAINS)), []);
        $priorities = [];
        $all = $this->items->forUser($user);
        foreach ($all as $item) {
            $byOrigin[$item->getOrigin()][] = $item;
            if ($item->isPriority()) {
                $priorities[] = $item;
            }
        }

        return [
            'byOrigin' => $byOrigin,
            'priorities' => $priorities,
            'count' => \count($all),
            'evaluated' => \count(array_filter($all, fn (StewardshipItem $i) => null !== $i->getStatus())),
        ];
    }

    /**
     * Ce que le Plan de Vie et les tests ont mis en lumière, pas encore dans l'inventaire.
     *
     * @return array<string, list<string>> origine => libellés proposés
     */
    public function suggestions(User $user): array
    {
        $plan = $user->getPlanDeVie();
        $out = array_fill_keys(array_keys(Stewardship::LIFE_PLAN), []);

        if ($statement = trim((string) $plan?->getPurpose()?->getStatement())) {
            $out['VIE_BUT'][] = $statement;
        }
        foreach ($this->em->getRepository(InternalBalance::class)->findBy(['user' => $user, 'type' => 'TALENT']) as $talent) {
            $out['VIE_DON'][] = (string) $talent->getText();
        }
        foreach ($this->testResults->gifts($user)['suggested'] ?? [] as $gift) {
            $out['VIE_DON'][] = $gift;
        }
        foreach ($plan?->getPassions() ?? [] as $passion) {
            $out['VIE_PASSION'][] = (string) $passion->getName();
        }
        $tests = $this->testResults->forUser($user);
        foreach (['disc' => 'Profil DISC', 'type' => 'Type', 'enneagram' => 'Ennéagramme'] as $key => $prefix) {
            if (isset($tests[$key]['label'])) {
                $out['VIE_PERSONNALITE'][] = $prefix.' : '.$tests[$key]['label'];
            }
        }
        if ($vision = trim((string) $plan?->getVision()?->getContent())) {
            $out['VIE_VISION'][] = mb_strlen($vision) > 120 ? mb_substr($vision, 0, 117).'…' : $vision;
        }
        foreach ($plan?->getLifeGoals() ?? [] as $goal) {
            $out['VIE_VISION'][] = 'Objectif : '.$goal->getTitle();
        }
        $season = $this->em->getRepository(Season::class)->findOneBy(['user' => $user], ['startDate' => 'DESC']);
        if ($season && trim((string) $season->getTheme())) {
            $out['VIE_SAISON'][] = 'Saison « '.trim((string) $season->getTheme()).' »';
        }

        // Retire les doublons et ce qui est déjà dans l'inventaire
        $taken = [];
        foreach ($this->items->forUser($user) as $item) {
            $taken[$item->getOrigin().'|'.mb_strtolower($item->getLabel())] = true;
        }
        foreach ($out as $origin => $labels) {
            $labels = array_filter(array_map(fn ($l) => mb_substr(trim($l), 0, 255), $labels), fn ($l) => '' !== $l && !isset($taken[$origin.'|'.mb_strtolower($l)]));
            $out[$origin] = array_values(array_unique($labels));
        }

        return $out;
    }
}
