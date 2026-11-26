<?php

namespace App\Methode\Controller;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Methode\Service\BookLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La méthode : les chapitres du livre, lisibles dans l'application.
 * Trois niveaux :
 * - visiteur : les premiers chapitres en entier (BookLibrary::PUBLIC_CHAPTERS), un extrait des autres ;
 * - compte gratuit : « l'essentiel » de chaque chapitre (verset, Sanctuaire, Ce que j'écris, À retenir) ;
 * - PRO (essai compris) : le texte intégral. L'administrateur peut ouvrir le texte intégral à tous
 *   (Configuration, « Le livre en entier », AppConfig::enableMethodFree).
 * Le livre est un produit en soi (vente, KDP) : l'application fait pratiquer, le livre enseigne.
 */
#[Route('/methode', name: 'app_methode_')]
class MethodeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(BookLibrary $book): Response
    {
        return $this->render('methode/index.html.twig', [
            'chapters' => $book->all(),
            'publicCount' => BookLibrary::PUBLIC_CHAPTERS,
        ]);
    }

    #[Route('/{slug}', name: 'chapter', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function chapter(string $slug, BookLibrary $book, Entitlements $entitlements, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        // Point 8 : ouvert depuis un plan (encart « Comprendre cette étape »), le chapitre propose d'y revenir.
        // Seulement une adresse du site (pas de redirection vers un autre site).
        $retour = $request->query->getString('retour');
        $retour = preg_match('#^/(?![/\\\\])[^\s]*$#', $retour) && !str_starts_with($retour, '/methode') ? $retour : null;
        if (isset(BookLibrary::LEGACY_SLUGS[$slug])) {
            return $this->redirectToRoute('app_methode_chapter', ['slug' => BookLibrary::LEGACY_SLUGS[$slug]], Response::HTTP_MOVED_PERMANENTLY);
        }
        $chapter = $book->get($slug) ?? throw $this->createNotFoundException('Chapitre introuvable.');

        return $this->render('methode/chapter.html.twig', [
            'chapter' => $chapter,
            'mode' => $this->mode($slug, $book, $entitlements),
            'previous' => $chapter['previous'] ? $book->get($chapter['previous']) : null,
            'next' => $chapter['next'] ? $book->get($chapter['next']) : null,
            'retour' => $retour,
        ]);
    }

    /** full (texte intégral), essentials (l'essentiel, compte gratuit) ou locked (visiteur, extrait). */
    private function mode(string $slug, BookLibrary $book, Entitlements $entitlements): string
    {
        $user = $this->getUser();
        if ($book->isPublic($slug)) {
            return 'full';
        }
        if (!$user instanceof User) {
            return 'locked';
        }

        return $entitlements->can($user, 'method') ? 'full' : 'essentials';
    }
}
