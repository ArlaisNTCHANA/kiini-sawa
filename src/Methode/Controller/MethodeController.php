<?php

namespace App\Methode\Controller;

use App\Methode\Service\BookLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * La méthode : les chapitres du livre, lisibles dans l'application.
 */
#[Route('/methode', name: 'app_methode_')]
#[IsGranted('ROLE_USER')]
class MethodeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(BookLibrary $book): Response
    {
        return $this->render('methode/index.html.twig', ['chapters' => $book->all()]);
    }

    #[Route('/{slug}', name: 'chapter', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function chapter(string $slug, BookLibrary $book): Response
    {
        $chapter = $book->get($slug) ?? throw $this->createNotFoundException('Chapitre introuvable.');

        return $this->render('methode/chapter.html.twig', [
            'chapter' => $chapter,
            'previous' => $chapter['previous'] ? $book->get($chapter['previous']) : null,
            'next' => $chapter['next'] ? $book->get($chapter['next']) : null,
        ]);
    }
}
