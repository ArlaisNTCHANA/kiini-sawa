<?php

namespace App\Bilan\Controller;

use App\Compte\Entity\User;
use App\PlanDHygiene\Entity\DailyLog;
use App\PlanDHygiene\Entity\PlanDHygiene;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Bilan du jour (livre, chapitre 17) : chaque soir, en deux minutes, mon intention (tenue ?), mon énergie, mon humeur,
 * mon stress (de 1 à 5) et un sujet de gratitude. Enregistré dans le journal quotidien du Plan d'Hygiène de vie
 * (DailyLog), qui porte déjà l'intention du jour saisie dans l'Agenda.
 */
#[Route('/bilan/du-jour', name: 'app_daily_review')]
#[IsGranted('ROLE_USER')]
class DailyReviewController extends AbstractController
{
    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $plan = $user->getPlanDHygiene();
        if (!$plan) {
            $plan = (new PlanDHygiene())->setUser($user);
            $user->setPlanDHygiene($plan);
            $em->persist($plan);
            $em->flush();
        }
        $today = new \DateTime('today');
        $repo = $em->getRepository(DailyLog::class);
        $log = $repo->findOneBy(['planDHygiene' => $plan, 'date' => $today]);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('daily_review', $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

                return $this->redirectToRoute('app_daily_review');
            }
            if (!$log) {
                $log = (new DailyLog())->setPlanDHygiene($plan)->setDate($today);
                $em->persist($log);
            }
            $level = fn (string $k) => ($v = $request->request->getInt($k)) >= 1 && $v <= 5 ? $v : null;
            $intention = trim($request->request->getString('intention'));
            if ('' !== $intention) {
                $log->setIntention(mb_substr($intention, 0, 255));
            }
            $kept = $request->request->getString('intention_kept');
            $log->setIntentionKept('' === $kept ? null : '1' === $kept)
                ->setEnergyLevel($level('energy'))
                ->setMoodLevel($level('mood'))
                ->setStressLevel($level('stress'))
                ->setGratitude($request->request->getString('gratitude'));
            $em->flush();
            $this->addFlash('success', 'Bilan du jour enregistré. Bonne soirée.');

            return $this->redirectToRoute('app_daily_review');
        }

        $recent = $repo->createQueryBuilder('l')
            ->where('l.planDHygiene = :plan')->andWhere('l.date >= :from')
            ->setParameter('plan', $plan)->setParameter('from', new \DateTime('-13 days midnight'))
            ->orderBy('l.date', 'DESC')->getQuery()->getResult();
        $avg = function (string $getter) use ($recent): ?float {
            $values = array_filter(array_map(fn (DailyLog $l) => $l->{$getter}(), $recent), fn ($v) => null !== $v);

            return $values ? round(array_sum($values) / \count($values), 1) : null;
        };

        return $this->render('bilan/daily_review.html.twig', [
            'log' => $log,
            'recent' => $recent,
            'averages' => ['énergie' => $avg('getEnergyLevel'), 'humeur' => $avg('getMoodLevel'), 'stress' => $avg('getStressLevel')],
        ]);
    }
}
