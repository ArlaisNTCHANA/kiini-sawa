<?php

namespace App\Mobile\Controller;

use App\Abonnement\Service\Entitlements;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use App\Rituels\Repository\HabitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion complète des rituels (liste, création avec plafond PRO, modification, suppression) pour le mobile.
 */
#[Route('/api/mobile/v1/rituels')]
class MobileHabitController extends AbstractController
{
    use MobileJsonTrait;

    private const TEXT_FIELDS = [
        'implementationWhen' => 'setImplementationWhen',
        'implementationThen' => 'setImplementationThen',
        'supportStrategy' => 'setSupportStrategy',
        'rootingIntention' => 'setRootingIntention',
        'rootingAction' => 'setRootingAction',
        'rootingMoment' => 'setRootingMoment',
        'rootingAnchor' => 'setRootingAnchor',
        'rootingBenefit' => 'setRootingBenefit',
        'rootingFacilitator' => 'setRootingFacilitator',
    ];

    #[Route('', name: 'api_mobile_habits_list', methods: ['GET'])]
    public function list(HabitRepository $repo, EntityManagerInterface $em, Entitlements $entitlements): JsonResponse
    {
        $user = $this->currentMobileUser();
        $habits = $repo->findByWithAllRelations($user);

        $yesterday = new \DateTime('yesterday');
        $doneYesterday = array_flip(array_map('intval', array_column($em->createQuery(
            'SELECT IDENTITY(l.habit) AS habit FROM App\Rituels\Entity\HabitLog l JOIN l.habit h WHERE h.user = :user AND l.date = :day AND l.status = true'
        )->setParameter('user', $user)->setParameter('day', $yesterday->format('Y-m-d'))->getArrayResult(), 'habit')));

        $missedYesterdayIds = [];
        foreach ($habits as $habit) {
            if (HabitFrequency::DAILY === $habit->getFrequency()
                && null !== $habit->getCreatedAt()
                && $habit->getCreatedAt() < $yesterday
                && !isset($doneYesterday[$habit->getId()])) {
                $missedYesterdayIds[] = $habit->getId();
            }
        }

        return $this->jsonNoStore([
            'habits' => array_map($this->serializeHabit(...), $habits),
            'missedYesterdayIds' => $missedYesterdayIds,
            'limit' => $entitlements->limit($user, 'habits'),
            'count' => \count($habits),
        ]);
    }

    #[Route('', name: 'api_mobile_habits_create', methods: ['POST'])]
    public function create(
        Request $request,
        HabitRepository $repo,
        EntityManagerInterface $em,
        Entitlements $entitlements,
    ): JsonResponse {
        $user = $this->currentMobileUser();
        $existingCount = max($user->getHabits()->count(), $repo->count(['user' => $user]));
        $entitlements->requireBelowLimit($user, 'habits', $existingCount, 'Limite de rituels de l\'offre gratuite atteinte.');

        $data = $this->decodeJson($request);
        if (null === $data) {
            return $this->jsonError('Requête JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $title = \is_string($data['title'] ?? null) ? trim($data['title']) : '';
        if ('' === $title || mb_strlen($title) > 255) {
            return $this->jsonError('Le titre du rituel doit contenir entre 1 et 255 caractères.', Response::HTTP_BAD_REQUEST);
        }

        $habit = new Habit();
        $habit->setTitle($title);

        if ($error = $this->applyFields($habit, $data)) {
            return $this->jsonError($error, Response::HTTP_BAD_REQUEST);
        }

        $user->addHabit($habit);
        $em->persist($habit);
        $em->flush();

        return $this->jsonNoStore(['habit' => $this->serializeHabit($habit)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_mobile_habits_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request, HabitRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->currentMobileUser();
        $habit = $repo->findOneBy(['id' => $id, 'user' => $user]);
        if (null === $habit) {
            return $this->jsonError('Rituel introuvable.', Response::HTTP_NOT_FOUND);
        }

        $data = $this->decodeJson($request);
        if (null === $data) {
            return $this->jsonError('Requête JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (\array_key_exists('title', $data)) {
            $title = \is_string($data['title']) ? trim($data['title']) : '';
            if ('' === $title || mb_strlen($title) > 255) {
                return $this->jsonError('Le titre du rituel doit contenir entre 1 et 255 caractères.', Response::HTTP_BAD_REQUEST);
            }
            $habit->setTitle($title);
        }

        if ($error = $this->applyFields($habit, $data)) {
            return $this->jsonError($error, Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->jsonNoStore(['habit' => $this->serializeHabit($habit)]);
    }

    #[Route('/{id}', name: 'api_mobile_habits_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, HabitRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->currentMobileUser();
        $habit = $repo->findOneBy(['id' => $id, 'user' => $user]);
        if (null === $habit) {
            return $this->jsonError('Rituel introuvable.', Response::HTTP_NOT_FOUND);
        }

        $user->removeHabit($habit);
        $em->remove($habit);
        $em->flush();

        return $this->jsonNoStore(['ok' => true]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyFields(Habit $habit, array $data): ?string
    {
        if (\array_key_exists('frequency', $data)) {
            if (!\is_string($data['frequency']) || null === ($freq = HabitFrequency::tryFrom($data['frequency']))) {
                return 'Fréquence de rituel invalide.';
            }
            $habit->setFrequency($freq);
        }

        if (\array_key_exists('endDate', $data)) {
            $rawDate = $data['endDate'];
            if (null === $rawDate || '' === $rawDate) {
                $habit->setEndDate(null);
            } elseif (!\is_string($rawDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
                return 'Format de date de fin invalide (AAAA-MM-JJ).';
            } else {
                $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);
                if (!$dt || $dt->format('Y-m-d') !== $rawDate) {
                    return 'Date de fin invalide.';
                }
                $habit->setEndDate(\DateTime::createFromImmutable($dt));
            }
        }

        if (\array_key_exists('recurrenceDay', $data)) {
            $v = $data['recurrenceDay'];
            if (null !== $v && (!is_numeric($v) || (int) $v < 0 || (int) $v > 31)) {
                return 'Jour de récurrence invalide.';
            }
            $habit->setRecurrenceDay(null === $v ? null : (int) $v);
        }

        if (\array_key_exists('recurrenceWeek', $data)) {
            $v = $data['recurrenceWeek'];
            if (null !== $v && (!is_numeric($v) || (int) $v < 1 || (int) $v > 5)) {
                return 'Semaine de récurrence invalide.';
            }
            $habit->setRecurrenceWeek(null === $v ? null : (int) $v);
        }

        foreach (self::TEXT_FIELDS as $field => $setter) {
            if (!\array_key_exists($field, $data)) {
                continue;
            }
            $raw = $data[$field];
            if (null !== $raw && !\is_string($raw)) {
                return 'Champ texte invalide.';
            }
            $value = null === $raw ? '' : trim($raw);
            if (mb_strlen($value) > 1000) {
                return 'Un champ d\'ancrage dépasse 1000 caractères.';
            }
            $habit->{$setter}('' === $value ? null : $value);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHabit(Habit $habit): array
    {
        return [
            'id' => $habit->getId(),
            'title' => (string) $habit->getTitle(),
            'frequency' => $habit->getFrequency()->value,
            'done' => $habit->isCompletedForCurrentPeriod(),
            'endDate' => $habit->getEndDate()?->format('Y-m-d'),
            'recurrenceDay' => $habit->getRecurrenceDay(),
            'recurrenceWeek' => $habit->getRecurrenceWeek(),
            'implementationWhen' => $habit->getImplementationWhen(),
            'implementationThen' => $habit->getImplementationThen(),
            'supportStrategy' => $habit->getSupportStrategy(),
            'rootingIntention' => $habit->getRootingIntention(),
            'rootingAction' => $habit->getRootingAction(),
            'rootingMoment' => $habit->getRootingMoment(),
            'rootingAnchor' => $habit->getRootingAnchor(),
            'rootingBenefit' => $habit->getRootingBenefit(),
            'rootingFacilitator' => $habit->getRootingFacilitator(),
            'createdAt' => $habit->getCreatedAt()?->format(\DATE_ATOM),
        ];
    }
}
