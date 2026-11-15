<?php

namespace App\Journal\Controller;

use App\Journal\Entity\ReflectionNote;
use App\Journal\Entity\JournalCollection;
use App\Journal\Enum\ReflectionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class JournalController extends AbstractController
{
    #[Route('/journal', name: 'app_journal')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Get initial entries (limit 20, offset 0)
        $allEntries = $this->getNormalizedEntries($user, $em);
        $totalCount = count($allEntries);
        $initialEntries = array_slice($allEntries, 0, 20);

        // Get collections
        $collections = $em->getRepository(JournalCollection::class)->findBy(
            ['user' => $user],
            ['name' => 'ASC']
        );

        return $this->render('journal/index.html.twig', [
            'initialEntries' => $initialEntries,
            'totalCount' => $totalCount,
            'collections' => $collections,
        ]);
    }

    #[Route('/api/journal/entries', name: 'api_journal_entries', methods: ['GET'])]
    public function getEntries(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $collectionId = $request->query->get('collectionId') ? (int)$request->query->get('collectionId') : null;
        $type = $request->query->get('type') ?: null;
        $limit = $request->query->getInt('limit', 20);
        $offset = $request->query->getInt('offset', 0);

        $allEntries = $this->getNormalizedEntries($user, $em, $collectionId, $type);
        $totalCount = count($allEntries);
        $paginatedEntries = array_slice($allEntries, $offset, $limit);

        return new JsonResponse([
            'entries' => $paginatedEntries,
            'totalCount' => $totalCount
        ]);
    }

    #[Route('/api/journal/entries/create', name: 'api_journal_create_entry', methods: ['POST'])]
    public function createEntry(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['content'])) {
            return new JsonResponse(['error' => 'Content is required'], Response::HTTP_BAD_REQUEST);
        }

        $note = new ReflectionNote();
        $note->setUser($user);
        $note->setContent(trim($data['content']));
        $note->setDate(new \DateTime());
        $note->setMood($data['mood'] ?? 'HAPPY');
        $note->setEnergy(isset($data['energy']) ? (int)$data['energy'] : 7);
        $note->setLocation(!empty($data['location']) ? trim($data['location']) : null);

        $typeStr = $data['type'] ?? 'REFLECTION';
        $typeEnum = ReflectionType::tryFrom($typeStr) ?: ReflectionType::REFLECTION;
        $note->setType($typeEnum);

        if (!empty($data['collectionId'])) {
            $collection = $em->getRepository(JournalCollection::class)->findOneBy([
                'id' => (int)$data['collectionId'],
                'user' => $user
            ]);
            if ($collection) {
                $note->setCollection($collection);
            }
        }

        $em->persist($note);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/journal/entries/{id}', name: 'api_journal_delete_entry', methods: ['DELETE'])]
    public function deleteEntry(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $note = $em->getRepository(ReflectionNote::class)->findOneBy([
            'id' => $id,
            'user' => $user
        ]);

        if (!$note) {
            return new JsonResponse(['error' => 'Note not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($note);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/journal/collections/create', name: 'api_journal_create_collection', methods: ['POST'])]
    public function createCollection(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['name'])) {
            return new JsonResponse(['error' => 'Collection name is required'], Response::HTTP_BAD_REQUEST);
        }

        $collection = new JournalCollection();
        $collection->setUser($user);
        $collection->setName(trim($data['name']));
        $collection->setColor($data['color'] ?? '#6366f1');
        $collection->setIcon($data['icon'] ?? 'Folder');

        $em->persist($collection);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/journal/collections/{id}', name: 'api_journal_delete_collection', methods: ['DELETE'])]
    public function deleteCollection(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $collection = $em->getRepository(JournalCollection::class)->findOneBy([
            'id' => $id,
            'user' => $user
        ]);

        if (!$collection) {
            return new JsonResponse(['error' => 'Collection not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($collection);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    private function getNormalizedEntries($user, EntityManagerInterface $em, ?int $collectionId = null, ?string $type = null): array
    {
        // 1. Fetch Reflection Notes
        $qb = $em->getRepository(ReflectionNote::class)->createQueryBuilder('n')
            ->select('n', 'c', 'g', 'gg')
            ->leftJoin('n.collection', 'c')
            ->leftJoin('n.goal', 'g')
            ->leftJoin('n.growthGoal', 'gg')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.date', 'DESC');

        if ($collectionId) {
            $qb->andWhere('n.collection = :collectionId')
               ->setParameter('collectionId', $collectionId);
        }
        if ($type) {
            $qb->andWhere('n.type = :type')
               ->setParameter('type', $type);
        }

        /** @var ReflectionNote[] $reflections */
        $reflections = $qb->getQuery()->getResult();

        $normalizedReflections = [];
        foreach ($reflections as $r) {
            $collData = null;
            if ($r->getCollection()) {
                $collData = [
                    'name' => $r->getCollection()->getName(),
                    'color' => $r->getCollection()->getColor(),
                ];
            }

            $source = 'Journal libre';
            if ($r->getGoal()) {
                $source = $r->getGoal()->getTitle();
            } elseif ($r->getGrowthGoal()) {
                $source = $r->getGrowthGoal()->getTitle();
            }

            $normalizedReflections[] = [
                'id' => $r->getId(),
                'type' => $r->getType()->value,
                'content' => $r->getContent(),
                'date' => $r->getDate()->format(\DateTime::ATOM),
                'mood' => $r->getMood(),
                'energy' => $r->getEnergy(),
                'location' => $r->getLocation(),
                'collection' => $collData,
                'source' => $source,
                'category' => 'REFLECTION',
            ];
        }

        // 2. Fetch Weekly Review Notes (scores) - only if no collection filter is active
        $normalizedScores = [];
        if (!$collectionId) {
            $scoreQb = $em->getRepository(\App\PlanDeCroissance\Entity\GrowthGoalScore::class)->createQueryBuilder('s')
                ->select('s', 'g')
                ->join('s.growthGoal', 'g')
                ->join('g.planDeCroissance', 'p')
                ->where('p.user = :user')
                ->andWhere('s.note IS NOT NULL')
                ->andWhere('s.note != :empty')
                ->setParameter('user', $user)
                ->setParameter('empty', '')
                ->orderBy('s.date', 'DESC');
            
            if ($type && $type !== 'MILESTONE') {
                $scoreQb->andWhere('1 = 0');
            }

            /** @var \App\PlanDeCroissance\Entity\GrowthGoalScore[] $scores */
            $scores = $scoreQb->getQuery()->getResult();

            foreach ($scores as $s) {
                $scoreVal = $s->getScore();
                $mood = 'NEUTRAL';
                if ($scoreVal >= 8) {
                    $mood = 'HAPPY';
                } elseif ($scoreVal <= 4) {
                    $mood = 'SAD';
                }

                $normalizedScores[] = [
                    'id' => $s->getId(),
                    'type' => 'MILESTONE',
                    'content' => $s->getNote(),
                    'date' => $s->getDate()->format(\DateTime::ATOM),
                    'mood' => $mood,
                    'energy' => null,
                    'location' => null,
                    'collection' => null,
                    'source' => $s->getGrowthGoal()?->getTitle() ?? 'Objectif de croissance',
                    'category' => 'REVIEW',
                ];
            }
        }

        // 3. Merge and Sort
        $allEntries = array_merge($normalizedReflections, $normalizedScores);
        usort($allEntries, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return $allEntries;
    }
}
