<?php

namespace App\Rituels\Controller;

use App\Abonnement\Service\Entitlements;

use App\Rituels\Entity\Habit;
use App\Rituels\Entity\HabitLog;
use App\Rituels\Form\HabitType;
use App\Rituels\Enum\HabitFrequency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HabitController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/mes-plans/rituels', name: 'app_user_habits', methods: ['GET', 'POST'])]
    public function rituels(Request $request, EntityManagerInterface $em, Entitlements $entitlements): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $habit = new Habit();
        $habit->setUser($user);

        $form = $this->createForm(HabitType::class, $habit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Le nouveau rituel n'est pas encore dans la collection du compte (setUser ne l'y ajoute pas) : on compte les rituels existants, sans retrancher
            $entitlements->requireBelowLimit($user, 'habits', $user->getHabits()->count(), 'Limite de rituels de l\'offre gratuite atteinte.');
            $em->persist($habit);
            $em->flush();

            // Point 7 (26/11/2026) : un rituel créé est libre ; on ouvre aussitôt son enracinement pour le relier à un plan
            return $this->redirectToRoute('app_user_habits', ['relier' => $habit->getId()]);
        }

        // Get growth goals to let the user link their habits
        $growthGoals = [];
        if ($user->getPlanDeCroissance()) {
            foreach ($user->getPlanDeCroissance()->getGrowthGoals() as $gg) {
                $growthGoals[] = [
                    'id' => $gg->getId(),
                    'title' => $gg->getTitle(),
                    'axis' => $gg->getAxis()->value,
                    'axisLabel' => ['CHARACTER' => 'Caractère', 'RELATION' => 'Relation', 'COMPETENCE' => 'Compétence'][$gg->getAxis()->value] ?? $gg->getAxis()->value,
                ];
            }
        }

        $habits = $em->getRepository(Habit::class)->findByWithAllRelations($user);
        $planLinks = $this->planLinks($user);

        // Rattrapage bienveillant (livre, chapitre 22) : « ne manque jamais deux fois de suite »
        $yesterday = new \DateTime('yesterday');
        $doneYesterday = array_flip(array_map('intval', array_column($em->createQuery(
            'SELECT IDENTITY(l.habit) AS habit FROM App\Rituels\Entity\HabitLog l JOIN l.habit h WHERE h.user = :user AND l.date = :day AND l.status = true'
        )->setParameter('user', $user)->setParameter('day', $yesterday->format('Y-m-d'))->getArrayResult(), 'habit')));
        $missedYesterday = array_values(array_filter($habits, fn (Habit $h) => \App\Rituels\Enum\HabitFrequency::DAILY === $h->getFrequency()
            && $h->getCreatedAt() && $h->getCreatedAt() < $yesterday && !isset($doneYesterday[$h->getId()])));

        return $this->render('rituels/index.html.twig', [
            'missedYesterday' => $missedYesterday,
            'habits' => $habits,
            'growthGoals' => $growthGoals,
            'planLinks' => $planLinks,
            'linkToOpen' => $request->query->getInt('relier'),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/api/habits/{id}/log', name: 'api_habit_log', methods: ['POST'])]
    public function toggleLog(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $habit = $em->getRepository(Habit::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (!$habit) {
            return new JsonResponse(['error' => 'Habit not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $status = filter_var($data['status'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $today = new \DateTime('today');
        
        // Find existing log for today
        $habitLog = null;
        foreach ($habit->getLogs() as $log) {
            if ($log->getDate()->format('Y-m-d') === $today->format('Y-m-d')) {
                $habitLog = $log;
                break;
            }
        }

        if ($habitLog) {
            $habitLog->setStatus($status);
        } else {
            $habitLog = new HabitLog();
            $habitLog->setDate($today);
            $habitLog->setStatus($status);
            $habit->addLog($habitLog);
            $em->persist($habitLog);
        }

        $em->flush();

        return new JsonResponse(['success' => true, 'isCompleted' => $habit->isCompletedForCurrentPeriod()]);
    }

    #[Route('/api/habits/{id}/rooting', name: 'api_habit_rooting', methods: ['POST'])]
    public function saveRooting(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var Habit $habit */
        $habit = $em->getRepository(Habit::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (!$habit) {
            return new JsonResponse(['error' => 'Habit not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Update rooting details
        $habit->setImplementationWhen($data['implementationWhen'] ?? null);
        $habit->setImplementationThen($data['implementationThen'] ?? null);
        $habit->setSupportStrategy($data['supportStrategy'] ?? null);
        $habit->setRootingIntention($data['rootingIntention'] ?? null);
        $habit->setRootingAction($data['rootingAction'] ?? null);
        $habit->setRootingMoment($data['rootingMoment'] ?? null);
        $habit->setRootingAnchor($data['rootingAnchor'] ?? null);
        $habit->setRootingBenefit($data['rootingBenefit'] ?? null);
        $habit->setRootingFacilitator($data['rootingFacilitator'] ?? null);

        if (isset($data['frequency'])) {
            $freq = HabitFrequency::tryFrom($data['frequency']);
            if ($freq) {
                $habit->setFrequency($freq);
            }
        }
        if (isset($data['recurrenceDay'])) {
            $habit->setRecurrenceDay($data['recurrenceDay'] !== null ? (int)$data['recurrenceDay'] : null);
        }
        if (isset($data['recurrenceWeek'])) {
            $habit->setRecurrenceWeek($data['recurrenceWeek'] !== null ? (int)$data['recurrenceWeek'] : null);
        }
        if (isset($data['endDate']) && !empty($data['endDate'])) {
            $habit->setEndDate(new \DateTime($data['endDate']));
        } elseif (array_key_exists('endDate', $data)) {
            $habit->setEndDate(null);
        }

        if (\array_key_exists('link', $data)) {
            // Point 7 : un seul lien à la fois, vers un objectif de n'importe lequel des plans (ou aucun)
            $target = $this->resolveLink($user, (string) $data['link']);
            $habit->setLifeGoal(null)->setGrowthGoal(null)->setSkillGoal(null)->setHygieneDomain(null)->setLegacyGoal(null);
            match (true) {
                $target instanceof \App\PlanDeVie\Entity\LifeGoal => $habit->setLifeGoal($target),
                $target instanceof \App\PlanDeCroissance\Entity\GrowthGoal => $habit->setGrowthGoal($target),
                $target instanceof \App\PlanDeDeveloppement\Entity\SkillGoal => $habit->setSkillGoal($target),
                $target instanceof \App\PlanDHygiene\Entity\HygieneDomain => $habit->setHygieneDomain($target),
                $target instanceof \App\PlanDeTransmission\Entity\LegacyGoal => $habit->setLegacyGoal($target),
                default => null,
            };
        } elseif (isset($data['growthGoalId']) && !empty($data['growthGoalId'])) {
            $gg = $em->getRepository(\App\PlanDeCroissance\Entity\GrowthGoal::class)->findOneBy([
                'id' => (int)$data['growthGoalId'],
                'planDeCroissance' => $user->getPlanDeCroissance()
            ]);
            if ($gg) {
                $habit->setGrowthGoal($gg);
            }
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/habits/{id}', name: 'api_habit_delete', methods: ['DELETE'])]
    public function deleteHabit(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $habit = $em->getRepository(Habit::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (!$habit) {
            return new JsonResponse(['error' => 'Habit not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($habit);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /** Les objectifs des plans auxquels un rituel peut être relié (point 7) : clé « TYPE:id ». */
    private function planLinks(\App\Compte\Entity\User $user): array
    {
        $groups = [];
        $add = static function (string $label, string $prefix, iterable $items, callable $title) use (&$groups): void {
            $options = [];
            foreach ($items as $item) {
                if ('' !== trim((string) $title($item))) {
                    $options[] = ['value' => $prefix.':'.$item->getId(), 'label' => (string) $title($item)];
                }
            }
            if ([] !== $options) {
                $groups[] = ['label' => $label, 'options' => $options];
            }
        };
        $add('Plan de Vie', 'GOAL', $user->getPlanDeVie()?->getLifeGoals() ?? [], static fn ($g) => $g->getTitle());
        $add('Plan de Croissance', 'GROWTH', $user->getPlanDeCroissance()?->getGrowthGoals() ?? [], static fn ($g) => $g->getTitle());
        $add('Plan de Développement personnel', 'SKILL', $user->getPlanDeDeveloppementPersonnel()?->getSkillGoals() ?? [], static fn ($g) => $g->getTitle());
        $add('Plan d\'Hygiène de vie', 'HYGIENE', $user->getPlanDHygiene()?->getHygieneDomains() ?? [], static fn ($d) => $d->getName());
        $add('Plan de Transmission', 'LEGACY', $user->getPlanDeTransmission()?->getLegacyGoals() ?? [], static fn ($g) => $g->getTitle());

        return $groups;
    }

    /** Retrouve l'objectif choisi, seulement s'il appartient à l'un des plans de la personne. */
    private function resolveLink(\App\Compte\Entity\User $user, string $link): ?object
    {
        foreach ($this->planLinks($user) as $group) {
            if (\in_array($link, array_column($group['options'], 'value'), true)) {
                [$prefix, $id] = explode(':', $link);
                $class = [
                    'GOAL' => \App\PlanDeVie\Entity\LifeGoal::class,
                    'GROWTH' => \App\PlanDeCroissance\Entity\GrowthGoal::class,
                    'SKILL' => \App\PlanDeDeveloppement\Entity\SkillGoal::class,
                    'HYGIENE' => \App\PlanDHygiene\Entity\HygieneDomain::class,
                    'LEGACY' => \App\PlanDeTransmission\Entity\LegacyGoal::class,
                ][$prefix];

                return $this->entityManager->find($class, (int) $id);
            }
        }

        return null;
    }
}
