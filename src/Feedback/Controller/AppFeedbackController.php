<?php

namespace App\Feedback\Controller;

use App\Feedback\Entity\AppFeedback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** « Donner mon avis » : bug, idée ou remarque sur l'application, lus par l'équipe dans l'administration. */
class AppFeedbackController extends AbstractController
{
    public const CATEGORIES = ['IDEE' => 'Une idée', 'BUG' => 'Quelque chose ne marche pas', 'REMARQUE' => 'Une remarque', 'MERCI' => 'Un encouragement'];

    #[Route('/donner-mon-avis', name: 'app_app_feedback', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $content = mb_substr(trim($request->request->getString('content')), 0, 4000);
            $category = $request->request->getString('category');
            if (!$this->isCsrfTokenValid('app_feedback', $request->request->getString('_csrf_token'))) {
                $error = 'La page a expiré, merci de réessayer.';
            } elseif (mb_strlen($content) < 5) {
                $error = 'Écrivez quelques mots.';
            } else {
                $context = trim($request->request->getString('page'));
                $em->persist((new AppFeedback())->setUser($this->getUser())->setCategory(isset(self::CATEGORIES[$category]) ? $category : 'REMARQUE')
                    ->setContent($content.('' !== $context ? "\n\n(Page : ".mb_substr($context, 0, 200).')' : ''))->setStatus('PENDING'));
                $em->flush();
                $this->addFlash('success', 'Merci ! Votre message a bien été transmis à l\'équipe.');

                return $this->redirectToRoute('app_app_feedback');
            }
        }

        return $this->render('feedback/app_feedback.html.twig', ['categories' => self::CATEGORIES, 'error' => $error, 'from' => $request->query->getString('page')]);
    }
}
