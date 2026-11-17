<?php

namespace App\Personnalite\Service;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Personnalite\Entity\TestAction;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeCroissance\Enum\GrowthAxis;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Transforme une action d'un rapport de test en rituel (chaque semaine, pendant 30 jours) ou en objectif du Plan de
 * Croissance (axe Caractère et conviction), et la garde en mémoire pour le rappel à 30 jours.
 */
final class TestActionService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Entitlements $entitlements)
    {
    }

    /** @throws \App\Abonnement\Exception\ProRequiredException si l'offre gratuite ne le permet pas */
    public function add(User $user, string $text, string $source, string $target): TestAction
    {
        $text = trim(mb_substr($text, 0, 255));
        if ('' === $text || !isset(TestAction::TARGETS[$target])) {
            throw new \InvalidArgumentException('Action inconnue.');
        }
        if ('habit' === $target) {
            $this->entitlements->requireBelowLimit($user, 'habits', $user->getHabits()->count(), 'Limite de rituels de l\'offre gratuite atteinte.');
            $habit = (new Habit())->setUser($user)->setTitle($text)->setFrequency(HabitFrequency::WEEKLY)
                ->setEndDate(new \DateTime('+30 days'))->setRootingIntention('Action choisie dans mon rapport '.$source.'.');
            $this->em->persist($habit);
        } else {
            $this->entitlements->require($user, 'croissance');
            $plan = $user->getPlanDeCroissance();
            if (!$plan) {
                $plan = (new \App\PlanDeCroissance\Entity\PlanDeCroissance())->setUser($user);
                $user->setPlanDeCroissance($plan);
                $this->em->persist($plan);
            }
            $goal = (new GrowthGoal())->setPlanDeCroissance($plan)->setTitle($text)->setAxis(GrowthAxis::CHARACTER)
                ->setSourceType('TEST')->setDeadline(new \DateTime('+30 days'))->setWhy('Action choisie dans mon rapport '.$source.'.');
            $this->em->persist($goal);
        }
        $action = new TestAction($user, $text, $source, $target);
        $this->em->persist($action);
        $this->em->flush();

        return $action;
    }
}
