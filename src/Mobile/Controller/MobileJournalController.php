<?php

namespace App\Mobile\Controller;

use App\Compte\Entity\User;
use App\Journal\Entity\JournalCollection;
use App\Journal\Entity\ReflectionNote;
use App\Journal\Enum\ReflectionType;
use App\PlanDeCroissance\Entity\GrowthGoalScore;
use App\Rapide\Service\QuickDay;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Journal complet (notes paginées, filtres, carnets) pour l'application mobile.
 */
#[Route('/api/mobile/v1/journal')]
class MobileJournalController extends AbstractController
{
    use MobileJsonTrait;

    private const DEFAULT_PAGE = 20;
    private const MAX_PAGE_SIZE = 50;
    private const MAX_OFFSET = 5000;

    #[Route('', name: 'api_mobile_journal_list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->currentMobileUser();

        $limit = max(1, min(self::MAX_PAGE_SIZE, $request->query->getInt('limit', self::DEFAULT_PAGE)));
        $offset = max(0, min(self::MAX_OFFSET, $request->query->getInt('offset', 0)));

        $typeParam = trim((string) $request->query->get('type', ''));
        $typeEnum = null;
        if ('' !== $typeParam) {
            $typeEnum = ReflectionType::tryFrom($typeParam);
            if (null === $typeEnum) {
                return $this->jsonError('Type d\'entrée invalide.', Response::HTTP_BAD_REQUEST);
            }
        }

        $carnetRaw = $request->query->get('carnetId');
        $carnetId = null;
        if (null !== $carnetRaw && '' !== $carnetRaw) {
            $carnetId = (int) $carnetRaw;
            if ($carnetId <= 0) {
                return $this->jsonError('Carnet invalide.', Response::HTTP_BAD_REQUEST);
            }
        }

        $totalCount = $this->countEntries($user, $em, $carnetId, $typeEnum);
        $normalized = $this->fetchNormalizedEntries($user, $em, $carnetId, $typeEnum, $offset + $limit);
        $entries = \array_slice($normalized, $offset, $limit);

        $carnets = $em->getRepository(JournalCollection::class)->findBy(['user' => $user], ['name' => 'ASC']);

        return $this->jsonNoStore([
            'entries' => $entries,
            'totalCount' => $totalCount,
            'hasMore' => ($offset + \count($entries)) < $totalCount,
            'carnets' => array_map($this->serializeCarnet(...), $carnets),
            'types' => QuickDay::JOURNAL_TYPES,
        ]);
    }

    #[Route('', name: 'api_mobile_journal_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->currentMobileUser();
        $data = $this->decodeJson($request);
        if (null === $data) {
            return $this->jsonError('Requête JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $content = \is_string($data['content'] ?? null) ? trim($data['content']) : '';
        if ('' === $content) {
            return $this->jsonError('Le texte de la note est requis.', Response::HTTP_BAD_REQUEST);
        }
        if (mb_strlen($content) > 5000) {
            return $this->jsonError('La note ne peut pas dépasser 5000 caractères.', Response::HTTP_BAD_REQUEST);
        }

        $type = ReflectionType::REFLECTION;
        if (\array_key_exists('type', $data) && null !== $data['type'] && '' !== $data['type']) {
            if (!\is_string($data['type']) || null === ($parsedType = ReflectionType::tryFrom($data['type']))) {
                return $this->jsonError('Type de note invalide.', Response::HTTP_BAD_REQUEST);
            }
            $type = $parsedType;
        }

        $note = (new ReflectionNote())
            ->setUser($user)
            ->setContent($content)
            ->setDate(new \DateTime())
            ->setType($type);

        if (\is_string($data['location'] ?? null) && '' !== trim($data['location'])) {
            $note->setLocation(mb_substr(trim($data['location']), 0, 255));
        }

        if (\array_key_exists('carnetId', $data) && null !== $data['carnetId'] && '' !== $data['carnetId']) {
            $carnet = $em->getRepository(JournalCollection::class)->findOneBy([
                'id' => (int) $data['carnetId'],
                'user' => $user,
            ]);
            if (null === $carnet) {
                return $this->jsonError('Carnet introuvable.', Response::HTTP_NOT_FOUND);
            }
            $note->setCollection($carnet);
        }

        $em->persist($note);
        $em->flush();

        return $this->jsonNoStore(['entry' => $this->serializeNote($note)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_mobile_journal_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->currentMobileUser();
        $note = $em->getRepository(ReflectionNote::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (null === $note) {
            return $this->jsonError('Note introuvable.', Response::HTTP_NOT_FOUND);
        }

        $em->remove($note);
        $em->flush();

        return $this->jsonNoStore(['ok' => true]);
    }

    #[Route('/carnets', name: 'api_mobile_carnets_create', methods: ['POST'])]
    public function createCarnet(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->currentMobileUser();
        $data = $this->decodeJson($request);
        if (null === $data) {
            return $this->jsonError('Requête JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $name = \is_string($data['name'] ?? null) ? trim($data['name']) : '';
        if ('' === $name || mb_strlen($name) > 100) {
            return $this->jsonError('Le nom du carnet doit contenir entre 1 et 100 caractères.', Response::HTTP_BAD_REQUEST);
        }

        $color = '#2E6B53';
        if (\is_string($data['color'] ?? null) && preg_match('/^#[0-9A-Fa-f]{6}$/', trim($data['color']))) {
            $color = trim($data['color']);
        }

        $carnet = (new JournalCollection())
            ->setUser($user)
            ->setName($name)
            ->setColor($color)
            ->setIcon('book-open');

        $em->persist($carnet);
        $em->flush();

        return $this->jsonNoStore(['carnet' => $this->serializeCarnet($carnet)], Response::HTTP_CREATED);
    }

    #[Route('/carnets/{id}', name: 'api_mobile_carnets_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteCarnet(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->currentMobileUser();
        $carnet = $em->getRepository(JournalCollection::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (null === $carnet) {
            return $this->jsonError('Carnet introuvable.', Response::HTTP_NOT_FOUND);
        }

        foreach ($carnet->getNotes() as $note) {
            $note->setCollection(null);
        }
        $em->remove($carnet);
        $em->flush();

        return $this->jsonNoStore(['ok' => true]);
    }

    private function countEntries(User $user, EntityManagerInterface $em, ?int $carnetId, ?ReflectionType $type): int
    {
        $notes = $em->getRepository(ReflectionNote::class)->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->setParameter('user', $user);

        if (null !== $carnetId) {
            $notes->andWhere('n.collection = :carnetId')->setParameter('carnetId', $carnetId);
        }
        if (null !== $type) {
            $notes->andWhere('n.type = :type')->setParameter('type', $type);
        }

        $total = (int) $notes->getQuery()->getSingleScalarResult();

        if (null === $carnetId && (null === $type || ReflectionType::MILESTONE === $type)) {
            $total += (int) $em->getRepository(GrowthGoalScore::class)->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->join('s.growthGoal', 'g')
                ->join('g.planDeCroissance', 'p')
                ->where('p.user = :user')
                ->andWhere('s.note IS NOT NULL')
                ->andWhere('s.note != :empty')
                ->setParameter('user', $user)
                ->setParameter('empty', '')
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $total;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchNormalizedEntries(
        User $user,
        EntityManagerInterface $em,
        ?int $carnetId,
        ?ReflectionType $type,
        int $take,
    ): array {
        $qb = $em->getRepository(ReflectionNote::class)->createQueryBuilder('n')
            ->select('n', 'c', 'g', 'gg')
            ->leftJoin('n.collection', 'c')
            ->leftJoin('n.goal', 'g')
            ->leftJoin('n.growthGoal', 'gg')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.date', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($take);

        if (null !== $carnetId) {
            $qb->andWhere('n.collection = :carnetId')->setParameter('carnetId', $carnetId);
        }
        if (null !== $type) {
            $qb->andWhere('n.type = :type')->setParameter('type', $type);
        }

        /** @var ReflectionNote[] $reflections */
        $reflections = $qb->getQuery()->getResult();
        $entries = array_map($this->serializeNote(...), $reflections);

        if (null === $carnetId && (null === $type || ReflectionType::MILESTONE === $type)) {
            /** @var GrowthGoalScore[] $scores */
            $scores = $em->getRepository(GrowthGoalScore::class)->createQueryBuilder('s')
                ->select('s', 'g')
                ->join('s.growthGoal', 'g')
                ->join('g.planDeCroissance', 'p')
                ->where('p.user = :user')
                ->andWhere('s.note IS NOT NULL')
                ->andWhere('s.note != :empty')
                ->setParameter('user', $user)
                ->setParameter('empty', '')
                ->orderBy('s.date', 'DESC')
                ->addOrderBy('s.id', 'DESC')
                ->setMaxResults($take)
                ->getQuery()
                ->getResult();

            foreach ($scores as $score) {
                $entries[] = [
                    'id' => $score->getId(),
                    'type' => ReflectionType::MILESTONE->value,
                    'typeLabel' => QuickDay::JOURNAL_TYPES[ReflectionType::MILESTONE->value] ?? 'Victoire',
                    'content' => (string) $score->getNote(),
                    'date' => $score->getDate()?->format(\DATE_ATOM),
                    'location' => null,
                    'carnet' => null,
                    'source' => $score->getGrowthGoal()?->getTitle() ?? 'Objectif de croissance',
                    'category' => 'REVIEW',
                    'deletable' => false,
                ];
            }
        }

        usort($entries, static function (array $a, array $b): int {
            $cmp = strcmp((string) $b['date'], (string) $a['date']);

            return 0 !== $cmp ? $cmp : ((int) $b['id'] <=> (int) $a['id']);
        });

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNote(ReflectionNote $note): array
    {
        $source = 'Journal libre';
        if (null !== $note->getGoal()) {
            $source = (string) $note->getGoal()->getTitle();
        } elseif (null !== $note->getGrowthGoal()) {
            $source = (string) $note->getGrowthGoal()->getTitle();
        }

        $col = $note->getCollection();
        $typeValue = $note->getType()->value;

        return [
            'id' => $note->getId(),
            'type' => $typeValue,
            'typeLabel' => QuickDay::JOURNAL_TYPES[$typeValue] ?? 'Pensée',
            'content' => (string) $note->getContent(),
            'date' => $note->getDate()?->format(\DATE_ATOM),
            'location' => $note->getLocation(),
            'carnet' => null !== $col ? [
                'id' => $col->getId(),
                'name' => (string) $col->getName(),
                'color' => $col->getColor(),
            ] : null,
            'source' => $source,
            'category' => 'REFLECTION',
            'deletable' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCarnet(JournalCollection $carnet): array
    {
        return [
            'id' => $carnet->getId(),
            'name' => (string) $carnet->getName(),
            'color' => $carnet->getColor(),
            'icon' => $carnet->getIcon(),
        ];
    }
}
