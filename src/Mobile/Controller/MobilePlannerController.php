<?php

namespace App\Mobile\Controller;

use App\PlanDeCroissance\Entity\GrowthGoal;
use App\Planner\Entity\PlannerTask;
use App\Planner\Repository\PlannerTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MobilePlannerController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlannerTaskRepository $tasks,
    ) {
    }

    #[Route('/api/mobile/v1/agenda', name: 'api_mobile_v1_planner_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->currentMobileUser();

        $startStr = $request->query->get('start') ?? $request->query->get('date') ?? 'today';
        $endStr = $request->query->get('end') ?? $startStr;

        try {
            $startDate = new \DateTime($startStr);
            $startDate->setTime(0, 0, 0);

            $endDate = new \DateTime($endStr);
            $endDate->setTime(23, 59, 59);
        } catch (\Exception) {
            return $this->jsonError('Format de date invalide.', Response::HTTP_BAD_REQUEST, 'INVALID_DATE');
        }

        if ($endDate < $startDate) {
            return $this->jsonError('La date de fin précède la date de début.', Response::HTTP_BAD_REQUEST, 'INVALID_DATE_RANGE');
        }

        $items = $this->tasks->createQueryBuilder('t')
            ->leftJoin('t.growthGoal', 'gg')
            ->addSelect('gg')
            ->where('t.user = :user')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('t.date', 'ASC')
            ->addOrderBy('t.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        $serialized = array_map(fn (PlannerTask $t) => $this->serializeTask($t), $items);

        return $this->jsonNoStore([
            'date' => $startDate->format('Y-m-d'),
            'total' => \count($serialized),
            'tasks' => $serialized,
        ]);
    }

    #[Route('/api/mobile/v1/agenda', name: 'api_mobile_v1_planner_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->currentMobileUser();
        $payload = $this->decodeJson($request);

        if (null === $payload) {
            return $this->jsonError('Corps JSON attendu.', Response::HTTP_BAD_REQUEST, 'INVALID_JSON');
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ('' === $title) {
            return $this->jsonError('Le titre de la tâche est obligatoire.', Response::HTTP_UNPROCESSABLE_ENTITY, 'MISSING_TITLE');
        }

        $dateStr = (string) ($payload['date'] ?? 'today');
        try {
            $date = new \DateTime($dateStr);
            $date->setTime(0, 0, 0);
        } catch (\Exception) {
            return $this->jsonError('Format de date invalide.', Response::HTTP_BAD_REQUEST, 'INVALID_DATE');
        }

        $task = new PlannerTask();
        $task->setUser($user);
        $task->setTitle(mb_substr($title, 0, 255));
        $task->setDate($date);
        $task->setIsAllDay((bool) ($payload['isAllDay'] ?? true));

        if (!empty($payload['description'])) {
            $task->setDescription(mb_substr((string) $payload['description'], 0, 5000));
        }

        if (!empty($payload['startTime'])) {
            try {
                $task->setStartTime(new \DateTime($date->format('Y-m-d').' '.$payload['startTime']));
                $task->setIsAllDay(false);
            } catch (\Exception) {
                // Heure ignorée si invalide
            }
        }

        if (!empty($payload['endTime'])) {
            try {
                $task->setEndTime(new \DateTime($date->format('Y-m-d').' '.$payload['endTime']));
            } catch (\Exception) {
                // Heure ignorée si invalide
            }
        }

        if (!empty($payload['growthGoalId'])) {
            $goal = $this->em->getRepository(GrowthGoal::class)->find((int) $payload['growthGoalId']);
            if ($goal && $goal->getPlanDeCroissance()?->getUser() === $user) {
                $task->setGrowthGoal($goal);
            }
        }

        $this->em->persist($task);
        $this->em->flush();

        return $this->jsonNoStore($this->serializeTask($task), Response::HTTP_CREATED);
    }

    #[Route('/api/mobile/v1/agenda/{id}', name: 'api_mobile_v1_planner_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->currentMobileUser();
        $task = $this->tasks->find($id);

        if (!$task || $task->getUser() !== $user) {
            return $this->jsonError('Tâche introuvable.', Response::HTTP_NOT_FOUND, 'TASK_NOT_FOUND');
        }

        $payload = $this->decodeJson($request);
        if (null === $payload) {
            return $this->jsonError('Corps JSON attendu.', Response::HTTP_BAD_REQUEST, 'INVALID_JSON');
        }

        if (\array_key_exists('isCompleted', $payload)) {
            $task->setIsCompleted((bool) $payload['isCompleted']);
        }

        if (\array_key_exists('title', $payload)) {
            $title = trim((string) $payload['title']);
            if ('' !== $title) {
                $task->setTitle(mb_substr($title, 0, 255));
            }
        }

        if (\array_key_exists('description', $payload)) {
            $desc = trim((string) $payload['description']);
            $task->setDescription('' === $desc ? null : mb_substr($desc, 0, 5000));
        }

        if (!empty($payload['date'])) {
            try {
                $date = new \DateTime((string) $payload['date']);
                $date->setTime(0, 0, 0);
                $task->setDate($date);
            } catch (\Exception) {
                // ignoré
            }
        }

        $this->em->flush();

        return $this->jsonNoStore($this->serializeTask($task));
    }

    #[Route('/api/mobile/v1/agenda/{id}', name: 'api_mobile_v1_planner_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->currentMobileUser();
        $task = $this->tasks->find($id);

        if (!$task || $task->getUser() !== $user) {
            return $this->jsonError('Tâche introuvable.', Response::HTTP_NOT_FOUND, 'TASK_NOT_FOUND');
        }

        $this->em->remove($task);
        $this->em->flush();

        return $this->jsonNoStore(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTask(PlannerTask $t): array
    {
        return [
            'id' => $t->getId(),
            'title' => $t->getTitle(),
            'description' => $t->getDescription(),
            'date' => $t->getDate()->format('Y-m-d'),
            'isAllDay' => $t->isAllDay(),
            'isCompleted' => $t->isCompleted(),
            'startTime' => $t->getStartTime()?->format('H:i'),
            'endTime' => $t->getEndTime()?->format('H:i'),
            'linkedGoal' => $t->getGrowthGoal()?->getTitle(),
            'linkedGoalId' => $t->getGrowthGoal()?->getId(),
        ];
    }
}
