<?php

namespace App\Abonnement\Controller\Admin;

use App\Abonnement\Entity\Transaction;
use App\Organisation\Entity\SeatOrder;
use App\Abonnement\Service\SubscriptionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration des paiements : validation des paiements manuels et revenus.
 */
#[Route('/admin/paiements', name: 'admin_payments')]
#[IsGranted('ROLE_ADMIN')]
class PaymentController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Transaction::class);
        $conn = $em->getConnection();
        $revenue = $conn->fetchAllAssociative(
            "SELECT DATE_FORMAT(processed_at, '%Y-%m') AS month, currency, SUM(amount) AS total, COUNT(*) AS count
             FROM (SELECT processed_at, currency, amount FROM transaction WHERE status = 'VALIDATED' AND method <> 'GIFT' AND processed_at IS NOT NULL
                   UNION ALL SELECT processed_at, currency, amount FROM seat_order WHERE status = 'VALIDATED') p
             GROUP BY month, currency ORDER BY month DESC LIMIT 24"
        );

        return $this->render('abonnement/admin/payments.html.twig', [
            'pending' => $repo->findBy(['status' => Transaction::STATUS_PENDING], ['createdAt' => 'ASC']),
            'pendingOrders' => $em->getRepository(SeatOrder::class)->findBy(['status' => SeatOrder::STATUS_PENDING], ['createdAt' => 'ASC']),
            'processed' => $repo->createQueryBuilder('t')->where('t.status <> :p')->setParameter('p', Transaction::STATUS_PENDING)->orderBy('t.processedAt', 'DESC')->setMaxResults(30)->getQuery()->getResult(),
            'revenue' => $revenue,
            'totals' => $conn->fetchAllKeyValue("SELECT currency, SUM(amount) FROM (SELECT currency, amount FROM transaction WHERE status = 'VALIDATED' AND method <> 'GIFT' UNION ALL SELECT currency, amount FROM seat_order WHERE status = 'VALIDATED') p GROUP BY currency"),
            'proCount' => (int) $conn->fetchOne("SELECT COUNT(*) FROM subscription WHERE tier <> 'FREE' AND status = 'ACTIVE' AND current_period_end > NOW()"),
            'trialCount' => (int) $conn->fetchOne("SELECT COUNT(*) FROM subscription WHERE status = 'TRIAL' AND trial_end_at > NOW()"),
            'userCount' => (int) $conn->fetchOne('SELECT COUNT(*) FROM `user`'),
        ]);
    }

    #[Route('/{id}/valider', name: '_validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validate(Transaction $transaction, Request $request, SubscriptionManager $manager): Response
    {
        if ($this->isCsrfTokenValid('admin_payment', $request->request->getString('_csrf_token'))) {
            $manager->validate($transaction, trim($request->request->getString('note')) ?: null);
            $this->addFlash('success', 'Paiement validé : PRO activé et client prévenu.');
        }

        return $this->redirectToRoute('admin_payments');
    }

    #[Route('/{id}/refuser', name: '_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reject(Transaction $transaction, Request $request, SubscriptionManager $manager): Response
    {
        $reason = trim($request->request->getString('reason'));
        if ($this->isCsrfTokenValid('admin_payment', $request->request->getString('_csrf_token')) && '' !== $reason) {
            $manager->reject($transaction, $reason);
            $this->addFlash('success', 'Paiement refusé : le client a été prévenu avec le motif.');
        } else {
            $this->addFlash('error', 'Indiquez le motif du refus : il est envoyé au client.');
        }

        return $this->redirectToRoute('admin_payments');
    }
}
