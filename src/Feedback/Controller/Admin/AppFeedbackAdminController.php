<?php

namespace App\Feedback\Controller\Admin;

use App\Feedback\Controller\AppFeedbackController;
use App\Feedback\Entity\AppFeedback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/avis', name: 'admin_app_feedback')]
#[IsGranted('ROLE_ADMIN')]
class AppFeedbackAdminController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $status = 'traites' === $request->query->getString('vue') ? 'DONE' : 'PENDING';

        return $this->render('feedback/admin/index.html.twig', [
            'items' => $em->getRepository(AppFeedback::class)->findBy(['status' => $status], ['createdAt' => 'DESC'], 200),
            'status' => $status,
            'pendingCount' => $em->getRepository(AppFeedback::class)->count(['status' => 'PENDING']),
            'categories' => AppFeedbackController::CATEGORIES,
        ]);
    }

    #[Route('/{id}/traite', name: '_done', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function done(AppFeedback $item, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_feedback', $request->request->getString('_csrf_token'))) {
            $item->setStatus('PENDING' === $item->getStatus() ? 'DONE' : 'PENDING');
            $em->flush();
        }

        return $this->redirectToRoute('admin_app_feedback');
    }
}
