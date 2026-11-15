<?php

namespace App\Bilan\Controller;

use App\Bilan\Entity\MonthlyReview;
use App\Bilan\Entity\WeeklyReview;
use App\Compte\Entity\User;
use App\PlanDeVie\Service\PlanSnapshotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Revue mensuelle : progrès, stagnation, blocage, ajustement, décision.
 * Du 1er au 7 du mois, on revoit le mois précédent ; ensuite, le mois en cours.
 * Chaque revue enregistre aussi une version datée des plans.
 */
class MonthlyReviewController extends AbstractController
{
    public const FIELDS = [
        'progress' => ['Ce qui a progressé', 'Les avancées, même modestes, sur mes plans et mes rituels.'],
        'stagnation' => ['Ce qui stagne', 'Là où je n\'ai pas avancé comme prévu.'],
        'blocker' => ['Mon principal blocage', 'La cause profonde : temps, peur, croyance, environnement…'],
        'adjustment' => ['L\'ajustement à faire', 'Ce que je change dans mon organisation ou mes rituels.'],
        'decision' => ['Ma décision pour le mois prochain', 'Une seule décision claire, qui fera la différence.'],
    ];

    #[Route('/bilan/revue-mensuelle', name: 'app_monthly_review', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em, PlanSnapshotService $snapshots): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $month = self::reviewedMonth(new \DateTimeImmutable('today'));
        $repo = $em->getRepository(MonthlyReview::class);
        $review = $repo->findOneBy(['user' => $user, 'month' => \DateTime::createFromImmutable($month)]);
        $monthLabel = (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMMM y'))->format($month);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('monthly_review', $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

                return $this->redirectToRoute('app_monthly_review');
            }
            $isNew = null === $review;
            $review ??= (new MonthlyReview())->setUser($user)->setMonth(\DateTime::createFromImmutable($month));
            foreach (array_keys(self::FIELDS) as $field) {
                $value = trim($request->request->getString($field));
                $review->{'set'.ucfirst($field)}('' === $value ? null : mb_substr($value, 0, 5000));
            }
            $em->persist($review);
            $em->flush();
            if ($isNew) {
                $snapshots->snapshot($user, 'Revue de '.$monthLabel);
            }
            $this->addFlash('success', 'Revue de '.$monthLabel.' enregistrée'.($isNew ? ', avec une version datée de vos plans' : '').'.');

            return $this->redirectToRoute('app_monthly_review');
        }

        // Synthèse du mois : revues hebdomadaires et objectifs
        $end = $month->modify('first day of next month');
        $weekly = $em->getRepository(WeeklyReview::class)->createQueryBuilder('w')
            ->where('w.user = :user')->andWhere('w.weekStart >= :start')->andWhere('w.weekStart < :end')
            ->setParameter('user', $user)->setParameter('start', $month)->setParameter('end', $end)
            ->orderBy('w.weekStart')->getQuery()->getResult();
        $scores = array_filter(array_map(fn (WeeklyReview $w) => $w->getAverageScore(), $weekly), fn ($s) => null !== $s);
        $goals = $user->getPlanDeVie()?->getLifeGoals()->toArray() ?? [];

        return $this->render('bilan/monthly_review.html.twig', [
            'month' => $month,
            'monthLabel' => $monthLabel,
            'review' => $review,
            'fields' => self::FIELDS,
            'weekly' => $weekly,
            'averageScore' => $scores ? round(array_sum($scores) / \count($scores), 1) : null,
            'goalsCount' => \count($goals),
            'goalsDone' => \count(array_filter($goals, fn ($g) => $g->getProgress() >= 100)),
            'averageProgress' => $goals ? (int) round(array_sum(array_map(fn ($g) => $g->getProgress(), $goals)) / \count($goals)) : null,
            'history' => $repo->findBy(['user' => $user], ['month' => 'DESC'], 12),
        ]);
    }

    /** Mois passé en revue : le précédent pendant la première semaine, sinon le mois en cours. */
    public static function reviewedMonth(\DateTimeImmutable $today): \DateTimeImmutable
    {
        return ((int) $today->format('j') <= 7 ? $today->modify('first day of last month') : $today->modify('first day of this month'))->setTime(0, 0);
    }
}
