<?php

namespace App\Bilan\Controller;

use App\Bilan\Repository\WeeklyReviewRepository;
use App\Bilan\Service\WeeklyReviewService;
use App\Compte\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Revue hebdomadaire : rétrospective, évaluation des quatre plans, rituels, journal, priorités.
 */
class WeeklyReviewController extends AbstractController
{
    #[Route('/bilan/revue-hebdomadaire', name: 'app_weekly_review', methods: ['GET', 'POST'])]
    public function index(Request $request, WeeklyReviewService $service, WeeklyReviewRepository $reviews): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('weekly_review', $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

                return $this->redirectToRoute('app_weekly_review');
            }
            $review = $service->save($user, $request->request);
            $avg = $review->getAverageScore();
            $this->addFlash('success', 'Revue de la semaine enregistrée'.(null !== $avg ? ' (moyenne '.str_replace('.', ',', (string) $avg).'/10)' : '').'. Rendez-vous la semaine prochaine !');

            return $this->redirectToRoute('app_weekly_review');
        }

        $week = $service->currentWeekStart();
        $current = $reviews->findForWeek($user, $week);
        $previous = [];
        foreach ($current?->getItems() ?? [] as $item) {
            $previous[$item['type'].'_'.$item['id']] = $item;
        }

        return $this->render('bilan/weekly_review.html.twig', [
            'week' => $week,
            'plans' => WeeklyReviewService::PLANS,
            'items' => $service->items($user),
            'habits' => $service->habitsThisWeek($user),
            'journalCount' => $service->journalEntriesThisWeek($user),
            'review' => $current,
            'previous' => $previous,
            'history' => $reviews->findRecent($user),
        ]);
    }
}
