<?php

namespace App\PlanDeCroissance\Controller;

use App\Compte\Entity\User;
use App\PlanDeCroissance\Entity\GrowthAction;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeCroissance\Entity\PlanDeCroissance;
use App\PlanDeCroissance\Enum\GrowthAxis;
use App\PlanDeVie\Entity\InternalBalance;
use App\PlanDeVie\Entity\Value;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Axes de croissance nés d'une croyance limitante, d'une conviction ou d'une valeur :
 * stratégie, objectifs soutenus, actions et rituels d'ancrage.
 *
 * Les actions et les rituels existants sont mis à jour et non recréés :
 * l'historique des rituels (journal de suivi, séries) est conservé.
 */
class GrowthAxisController extends AbstractController
{
    public const SOURCE_TYPES = ['LIMITING_BELIEF', 'CONVICTION', 'COUNTER_VALUE', 'DECLARED_VALUE'];

    #[Route('/plan-de-croissance/axe/{sourceType}/{sourceId}', name: 'app_plan_de_croissance_axis_save', requirements: ['sourceType' => 'LIMITING_BELIEF|CONVICTION|COUNTER_VALUE|DECLARED_VALUE', 'sourceId' => '\d+'], methods: ['POST'])]
    public function save(string $sourceType, int $sourceId, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $back = $this->redirectToRoute('app_plan_de_croissance', ['tab' => 'SYSTEM']);

        $sourceText = match ($sourceType) {
            'LIMITING_BELIEF', 'CONVICTION' => $this->ownedInternalBalance($em, $user, $sourceId)?->getText(),
            default => $this->ownedValue($em, $user, $sourceId)?->getName(),
        };
        if (!$sourceText) {
            return $back;
        }

        $plan = $user->getPlanDeCroissance();
        if (!$plan) {
            $plan = (new PlanDeCroissance())->setUser($user);
            $em->persist($plan);
        }
        $goal = $plan->getId() ? $em->getRepository(GrowthGoal::class)->findOneBy(['planDeCroissance' => $plan, 'sourceType' => $sourceType, 'sourceId' => $sourceId]) : null;
        if (!$goal) {
            $goal = (new GrowthGoal())->setSourceType($sourceType)->setSourceId($sourceId)->setAxis(GrowthAxis::CHARACTER)->setProgress(0);
            $plan->addGrowthGoal($goal);
            $em->persist($goal);
        }

        $payload = $request->request;
        $goal->setTitle($sourceText);
        $goal->setStrategy($payload->getString('strategy'));
        $status = $payload->getString('status', 'build');
        $goal->setSubtype(\in_array($status, ['build', 'consolidate', 'leverage', 'absence'], true) ? $status : 'build');

        $owned = [];
        foreach ($user->getPlanDeVie()?->getLifeGoals() ?? [] as $lg) {
            $owned[$lg->getId()] = $lg;
        }
        $wanted = array_filter(array_map(fn ($id) => $owned[(int) $id] ?? null, $payload->all('life_goals')));
        foreach ($goal->getSupportedLifeGoals()->toArray() as $lg) {
            if (!\in_array($lg, $wanted, true)) {
                $goal->removeSupportedLifeGoal($lg);
            }
        }
        foreach ($wanted as $lg) {
            $goal->addSupportedLifeGoal($lg);
        }

        $this->syncActions($em, $goal, $payload->all('action_ids'), $payload->all('action_titles'), $payload->all('action_deadlines'));
        $this->syncHabits($em, $user, $goal, $payload->all('habit_ids'), $payload->all('habit_titles'), $payload->all('habit_frequencies'));

        $em->flush();

        return $back;
    }

    private function syncActions(EntityManagerInterface $em, GrowthGoal $goal, array $ids, array $titles, array $deadlines): void
    {
        [$ids, $titles, $deadlines] = [array_values($ids), array_values($titles), array_values($deadlines)];
        $existing = [];
        foreach ($goal->getActions() as $a) {
            $existing[$a->getId()] = $a;
        }
        $kept = [];
        foreach ($titles as $i => $title) {
            $title = trim((string) $title);
            if ('' === $title) {
                continue;
            }
            $action = $this->match($existing, $kept, $ids[$i] ?? null, $title, fn (GrowthAction $a) => $a->getTitle());
            if (!$action) {
                $action = (new GrowthAction())->setStatus('NOT_STARTED');
                $goal->addAction($action);
                $em->persist($action);
            }
            $action->setTitle($title);
            $date = !empty($deadlines[$i]) ? \DateTime::createFromFormat('!Y-m-d', $deadlines[$i]) : null;
            if ($date) {
                $action->setDeadline($date);
            }
        }
        foreach ($existing as $id => $a) {
            if (!isset($kept[$id])) {
                $goal->removeAction($a);
                $em->remove($a);
            }
        }
    }

    private function syncHabits(EntityManagerInterface $em, User $user, GrowthGoal $goal, array $ids, array $titles, array $frequencies): void
    {
        [$ids, $titles, $frequencies] = [array_values($ids), array_values($titles), array_values($frequencies)];
        $existing = [];
        foreach ($goal->getHabits() as $h) {
            $existing[$h->getId()] = $h;
        }
        $kept = [];
        foreach ($titles as $i => $title) {
            $title = trim((string) $title);
            if ('' === $title) {
                continue;
            }
            $habit = $this->match($existing, $kept, $ids[$i] ?? null, $title, fn (Habit $h) => $h->getTitle());
            if (!$habit) {
                $habit = (new Habit())->setUser($user);
                $goal->addHabit($habit);
                $em->persist($habit);
            }
            $habit->setTitle($title);
            $habit->setFrequency(HabitFrequency::tryFrom((string) ($frequencies[$i] ?? '')) ?? HabitFrequency::DAILY);
        }
        // Un rituel retiré de l'axe n'est pas supprimé : il est détaché, son historique reste dans « Rituels »
        foreach ($existing as $id => $h) {
            if (!isset($kept[$id])) {
                $goal->removeHabit($h);
            }
        }
    }

    /** Élément existant correspondant (par identifiant, sinon par libellé), marqué comme conservé. */
    private function match(array $existing, array &$kept, mixed $id, string $title, callable $label): ?object
    {
        if (is_numeric($id) && isset($existing[(int) $id]) && !isset($kept[(int) $id])) {
            $kept[(int) $id] = true;

            return $existing[(int) $id];
        }
        foreach ($existing as $eid => $e) {
            if (!isset($kept[$eid]) && mb_strtolower($label($e)) === mb_strtolower($title)) {
                $kept[$eid] = true;

                return $e;
            }
        }

        return null;
    }

    private function ownedInternalBalance(EntityManagerInterface $em, User $user, int $id): ?InternalBalance
    {
        $ib = $em->getRepository(InternalBalance::class)->find($id);

        return $ib && $ib->getUser() === $user ? $ib : null;
    }

    private function ownedValue(EntityManagerInterface $em, User $user, int $id): ?Value
    {
        $value = $em->getRepository(Value::class)->find($id);

        return $value && null !== $user->getPlanDeVie() && $value->getPlanDeVie() === $user->getPlanDeVie() ? $value : null;
    }
}
