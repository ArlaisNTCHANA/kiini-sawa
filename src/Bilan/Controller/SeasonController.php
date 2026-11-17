<?php

namespace App\Bilan\Controller;

use App\Bilan\Service\Seasons;
use App\Compte\Entity\User;
use App\PlanDeVie\Entity\Season;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Ma saison » (livre, chapitre 11) : une saison de trois mois, un thème, une nature (semis, croissance, récolte,
 * hiver), puis la revue de saison et le choix de la suivante (chapitre 23).
 */
#[Route('/bilan/saison', name: 'app_season')]
#[IsGranted('ROLE_USER')]
class SeasonController extends AbstractController
{
    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $repo = $em->getRepository(Season::class);
        $seasons = $repo->findBy(['user' => $user], ['startDate' => 'DESC']);
        $current = $seasons[0] ?? null;
        $today = new \DateTimeImmutable('today');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('season', $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

                return $this->redirectToRoute('app_season');
            }
            $kind = $request->request->getString('kind');
            $kind = isset(Seasons::KINDS[$kind]) ? $kind : null;
            $theme = mb_substr(trim($request->request->getString('theme')), 0, 255) ?: null;

            switch ($request->request->getString('action')) {
                case 'update':
                    if ($current) {
                        $current->setTheme($theme)->setKind($kind);
                        $this->addFlash('success', 'Votre saison est à jour.');
                    }
                    break;
                case 'review':
                    if ($current) {
                        $current->setReviewNote($request->request->getString('review_note'))->setReviewedAt(new \DateTimeImmutable());
                        $this->addFlash('success', 'Revue de saison enregistrée.');
                    }
                    break;
                case 'start':
                    if ($current && $current->getEndDate() > \DateTime::createFromImmutable($today)) {
                        $current->setEndDate(\DateTime::createFromImmutable($today)); // la saison précédente se termine aujourd'hui
                    }
                    $season = (new Season())->setUser($user)->setTheme($theme)->setKind($kind)
                        ->setStartDate(\DateTime::createFromImmutable($today))
                        ->setEndDate(\DateTime::createFromImmutable($today->modify('+'.Seasons::MONTHS.' months')));
                    $em->persist($season);
                    $this->addFlash('success', 'Nouvelle saison commencée. Vous pouvez dire « pas maintenant » au reste.');
                    break;
            }
            $em->flush();

            return $this->redirectToRoute('app_season');
        }

        $active = $current && $current->getEndDate() >= \DateTime::createFromImmutable($today) ? $current : null;
        $daysLeft = $active ? (int) $today->diff(\DateTimeImmutable::createFromMutable($active->getEndDate()))->days : null;

        return $this->render('bilan/season.html.twig', [
            'current' => $current,
            'activeSeason' => $active,
            'daysLeft' => $daysLeft,
            'progress' => $active ? (int) round(100 * (1 - $daysLeft / max(1, (int) \DateTimeImmutable::createFromMutable($active->getStartDate())->diff(\DateTimeImmutable::createFromMutable($active->getEndDate()))->days))) : null,
            'reviewDue' => $current && (null === $active || $daysLeft <= 14) && null === $current->getReviewedAt(),
            'history' => \array_slice($seasons, 1, 8),
            'kinds' => Seasons::KINDS,
        ]);
    }
}
