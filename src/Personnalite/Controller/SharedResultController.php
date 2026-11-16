<?php

namespace App\Personnalite\Controller;

use App\Personnalite\Entity\Assessment;
use App\Personnalite\Psychometrie\ReportBuilder;
use App\Personnalite\Psychometrie\ShareCard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Page publique d'un résultat partagé : invite à passer le test à son tour. */
class SharedResultController extends AbstractController
{
    #[Route('/resultat/{token}', name: 'app_shared_result', requirements: ['token' => '[a-f0-9]{24}'], methods: ['GET'])]
    public function show(string $token, EntityManagerInterface $em, ReportBuilder $reports): Response
    {
        $assessment = $em->getRepository(Assessment::class)->findOneBy(['shareToken' => $token])
            ?? throw $this->createNotFoundException('Ce résultat n\'est plus partagé.');

        return $this->render('personnalite/shared_result.html.twig', [
            'card' => ShareCard::from($reports->build($assessment)),
            'firstName' => $assessment->getUser()->getUserProfile()?->getFirstName() ?: 'Un membre',
        ]);
    }
}
