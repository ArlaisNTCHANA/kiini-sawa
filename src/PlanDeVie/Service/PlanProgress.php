<?php

namespace App\PlanDeVie\Service;

use App\Compte\Entity\User;
use App\PlanDeCroissance\Enum\GrowthAxis;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\PlanDIntendance\Entity\StewardshipRhythm;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Où en est chaque plan : quelques étapes simples et honnêtes (écrire, relier, évaluer), tirées du livre.
 * Sert au cercle des plans de « Mes plans » (progression, prochaine étape). Ce n'est pas un score de qualité :
 * un plan « à 100 % » a franchi ses étapes de départ, pas atteint ses objectifs.
 */
final class PlanProgress
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, array{percent: int, done: int, total: int, steps: list<array{label: string, done: bool, route: string, params: array}>, next: ?array{label: string, route: string, params: array}}>
     */
    public function forUser(User $user): array
    {
        $vie = $user->getPlanDeVie();
        $growth = $user->getPlanDeCroissance()?->getGrowthGoals()->toArray() ?? [];
        $character = array_filter($growth, fn ($g) => GrowthAxis::CHARACTER === $g->getAxis());
        $relation = array_filter($growth, fn ($g) => GrowthAxis::RELATION === $g->getAxis());
        $skills = array_filter($growth, fn ($g) => GrowthAxis::COMPETENCE === $g->getAxis());
        $linked = fn (array $list) => [] !== array_filter($list, fn ($g) => $g->getSupportedLifeGoals()->count() > 0);
        $hygiene = $user->getPlanDHygiene();
        $domains = $hygiene?->getHygieneDomains()->toArray() ?? [];
        $legacy = $user->getPlanDeTransmission()?->getLegacyGoals()->toArray() ?? [];
        $items = $this->em->getRepository(StewardshipItem::class)->findBy(['user' => $user]);
        $rhythm = $this->em->getRepository(StewardshipRhythm::class)->findOneBy(['user' => $user]);

        $wizard = fn (int $step, string $plan = '') => ['app_plan_wizard', ['step' => $step, 'single' => 1] + ('' !== $plan ? ['plan' => $plan] : [])];

        $plans = [
            'VIE' => [
                ['Écrire mon But de Dieu', '' !== trim((string) $vie?->getPurpose()?->getStatement()), ...$wizard(1)],
                ['Nommer au moins trois valeurs', ($vie?->getValues()->count() ?? 0) >= 3, ...$wizard(2)],
                ['Écrire ma vision', '' !== trim((string) $vie?->getVision()?->getContent()), ...$wizard(6)],
                ['Poser mon premier objectif', ($vie?->getLifeGoals()->count() ?? 0) >= 1, ...$wizard(7)],
            ],
            'CROISSANCE' => [
                ['Choisir un chantier de caractère', [] !== $character, ...$wizard(9, 'CROISSANCE')],
                ['Cartographier un réseau', [] !== $relation, ...$wizard(10, 'CROISSANCE')],
                ['Relier un chantier à un objectif', $linked($character) || $linked($relation), ...$wizard(9, 'CROISSANCE')],
            ],
            'DEVELOPPEMENT' => [
                ['Choisir une compétence prioritaire', [] !== $skills, ...$wizard(8, 'DEVELOPPEMENT')],
                ['Choisir trois compétences', \count($skills) >= 3, ...$wizard(8, 'DEVELOPPEMENT')],
                ['Relier une compétence à un objectif', $linked($skills), ...$wizard(8, 'DEVELOPPEMENT')],
            ],
            'HYGIENE' => [
                ['Choisir un domaine à soutenir', [] !== $domains, ...$wizard(12, 'HYGIENE')],
                ['Faire mon premier bilan du jour', ($hygiene?->getDailyLogs()->filter(fn ($l) => null !== $l->getMoodLevel() || null !== $l->getEnergyLevel())->count() ?? 0) >= 1, 'app_daily_review', []],
                ['Relier un domaine à un objectif', [] !== array_filter($domains, fn ($d) => $d->getSupportedLifeGoals()->count() > 0), ...$wizard(12, 'HYGIENE')],
            ],
            'INTENDANCE' => [
                ['Dresser mon inventaire (trois éléments)', \count($items) >= 3, 'app_plan_d_intendance', []],
                ['Dire où en est chaque élément', [] !== $items && [] === array_filter($items, fn ($i) => null === $i->getStatus()), 'app_plan_d_intendance', []],
                ['Décider pour un élément prioritaire', [] !== array_filter($items, fn ($i) => $i->isPriority() && null !== $i->getDecision()), 'app_plan_d_intendance', []],
                ['Faire mon premier compte rendu', null !== $rhythm?->getLastReviewAt(), 'app_plan_d_intendance', []],
            ],
            'TRANSMISSION' => [
                ['Écrire ma phrase d\'éternité', '' !== trim((string) $user->getPlanDeTransmission()?->getEternityPhrase()), ...$wizard(11, 'TRANSMISSION')],
                ['Poser un projet de legs', [] !== $legacy, ...$wizard(11, 'TRANSMISSION')],
                ['Relier un legs à un objectif', [] !== array_filter($legacy, fn ($l) => $l->getSupportedLifeGoals()->count() > 0), ...$wizard(11, 'TRANSMISSION')],
            ],
        ];

        $out = [];
        foreach ($plans as $key => $rows) {
            $steps = array_map(fn ($r) => ['label' => $r[0], 'done' => (bool) $r[1], 'route' => $r[2], 'params' => $r[3]], $rows);
            $done = \count(array_filter($steps, fn ($s) => $s['done']));
            $next = null;
            foreach ($steps as $s) {
                if (!$s['done']) {
                    $next = ['label' => $s['label'], 'route' => $s['route'], 'params' => $s['params']];
                    break;
                }
            }
            $out[$key] = ['percent' => (int) round(100 * $done / \count($steps)), 'done' => $done, 'total' => \count($steps), 'steps' => $steps, 'next' => $next];
        }

        return $out;
    }
}
