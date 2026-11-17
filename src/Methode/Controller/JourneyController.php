<?php

namespace App\Methode\Controller;

use App\Compte\Entity\User;
use App\Methode\Entity\JourneyProgress;
use App\Methode\Repository\JourneyProgressRepository;
use App\Methode\Service\BookLibrary;
use App\Methode\Service\Journey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Le parcours de 90 jours (livre, annexe A) : chaque semaine, les chapitres à lire et les écrans où écrire son plan.
 * Le livre enseigne, l'application fait pratiquer : c'est la colonne vertébrale qui relie les deux.
 */
#[Route('/parcours', name: 'app_journey')]
#[IsGranted('ROLE_USER')]
class JourneyController extends AbstractController
{
    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function index(Request $request, JourneyProgressRepository $repo, EntityManagerInterface $em, BookLibrary $book): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $progress = $repo->findOneBy(['user' => $user]);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('journey', $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

                return $this->redirectToRoute('app_journey');
            }
            switch ($request->request->getString('action')) {
                case 'start':
                    $progress ??= (new JourneyProgress())->setUser($user);
                    $progress->setStartedAt(new \DateTimeImmutable('today'));
                    $em->persist($progress);
                    $this->addFlash('success', 'Votre parcours commence aujourd\'hui. Une heure de lecture, une heure de Sanctuaire, une heure de mise en pratique : à vous de jouer.');
                    break;
                case 'toggle':
                    if ($progress) {
                        $week = $request->request->getInt('week');
                        $progress->toggleWeek($week);
                        if ($progress->isFinished()) {
                            $this->addFlash('success', 'Treize semaines ! Accordez-vous une demi-journée pour relire vos six plans d\'un trait, puis célébrez. Vous avez fait ce que la plupart des gens ne feront jamais.');
                        }
                    }
                    break;
            }
            $em->flush();

            return $this->redirectToRoute('app_journey', [], Response::HTTP_SEE_OTHER);
        }

        $weeks = [];
        foreach (Journey::PLAN as $n => $week) {
            $weeks[$n] = $week + ['books' => array_values(array_filter(array_map(fn (string $slug) => $book->get($slug), $week['chapters'])))];
        }

        return $this->render('methode/journey.html.twig', [
            'progress' => $progress,
            'weeks' => $weeks,
            'current' => $progress?->currentWeek(),
        ]);
    }
}
