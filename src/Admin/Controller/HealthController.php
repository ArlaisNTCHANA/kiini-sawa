<?php

namespace App\Admin\Controller;

use App\Admin\Service\LaunchChecker;
use App\Admin\Service\SiteHealth;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Admin › Réglages › Santé du site (27/11/2026) : la santé au jour le jour, puis le contrôle avant ouverture. */
#[Route('/admin/sante', name: 'admin_health', methods: ['GET'])]
#[IsGranted('ROLE_ADMIN')]
class HealthController extends AbstractController
{
    public function __invoke(SiteHealth $health, LaunchChecker $launch): Response
    {
        $checks = $health->checks();

        return $this->render('admin/health/index.html.twig', [
            'checks' => $checks, 'overall' => $health->overall($checks), 'launch' => $launch->run(),
        ]);
    }
}
