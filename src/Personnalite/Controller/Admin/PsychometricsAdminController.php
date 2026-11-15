<?php

namespace App\Personnalite\Controller\Admin;

use App\Personnalite\Psychometrie\IpipItemBank;
use App\Personnalite\Psychometrie\NormService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Qualité scientifique des questionnaires : passations, fidélité (alpha de Cronbach) mesurée
 * sur les réponses réelles, comparée à la fidélité d'origine de l'IPIP-NEO, et état des normes.
 */
#[IsGranted('ROLE_ADMIN')]
class PsychometricsAdminController extends AbstractController
{
    #[Route('/admin/tests-fidelite', name: 'admin_psychometrics', methods: ['GET'])]
    public function index(NormService $norms, EntityManagerInterface $em): Response
    {
        return $this->render('personnalite/admin/psychometrics.html.twig', [
            'counts' => $em->getConnection()->fetchAllKeyValue('SELECT instrument, COUNT(*) FROM assessment GROUP BY instrument'),
            'reliability' => $norms->reliability(),
            'originalAlpha' => IpipItemBank::FACET_ALPHA,
            'norms' => $norms->norms(),
            'minSample' => NormService::MIN_NORM_SAMPLE,
            'domains' => IpipItemBank::DOMAINS,
        ]);
    }
}
