<?php

namespace App\Dashboard\Controller\Admin;

use App\Dashboard\Entity\Quote;
use App\Dashboard\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration des citations du jour affichées sur l'accueil.
 * Une citation datée s'affiche ce jour-là ; les autres tournent chaque jour.
 */
#[Route('/admin/citations', name: 'admin_quotes')]
#[IsGranted('ROLE_ADMIN')]
class QuoteController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(QuoteRepository $quotes): Response
    {
        return $this->render('dashboard/admin/quotes.html.twig', [
            'quotes' => $quotes->findBy([], ['displayDate' => 'DESC', 'id' => 'ASC']),
            'today' => $quotes->findForDate(new \DateTimeImmutable('today')),
        ]);
    }

    #[Route('/ajouter', name: '_add', methods: ['POST'])]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_quote', $request->request->getString('_csrf_token'))) {
            return $this->redirectToRoute('admin_quotes');
        }
        $content = trim($request->request->getString('content'));
        if ('' === $content) {
            $this->addFlash('error', 'Le texte de la citation est obligatoire.');

            return $this->redirectToRoute('admin_quotes');
        }
        $date = \DateTime::createFromFormat('!Y-m-d', $request->request->getString('displayDate')) ?: null;
        $em->persist((new Quote())
            ->setContent($content)
            ->setAuthor(trim($request->request->getString('author')) ?: null)
            ->setCategory(trim($request->request->getString('category')) ?: 'MOTIVATION')
            ->setDisplayDate($date));
        $em->flush();
        $this->addFlash('success', 'Citation ajoutée.');

        return $this->redirectToRoute('admin_quotes');
    }

    #[Route('/{id}/basculer', name: '_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Quote $quote, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_quote', $request->request->getString('_csrf_token'))) {
            $quote->setIsActive(!$quote->isActive());
            $em->flush();
        }

        return $this->redirectToRoute('admin_quotes');
    }

    #[Route('/{id}/supprimer', name: '_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Quote $quote, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_quote', $request->request->getString('_csrf_token'))) {
            $em->remove($quote);
            $em->flush();
            $this->addFlash('success', 'Citation supprimée.');
        }

        return $this->redirectToRoute('admin_quotes');
    }
}
