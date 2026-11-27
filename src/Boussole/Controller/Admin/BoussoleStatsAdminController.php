<?php

namespace App\Boussole\Controller\Admin;

use App\Boussole\Service\BoussoleStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Admin › Les chiffres › La Boussole 360 (27/11/2026). Totaux seulement (BoussoleStats). */
#[Route('/admin/boussole', name: 'admin_boussole_stats', methods: ['GET'])]
#[IsGranted('ROLE_ADMIN')]
class BoussoleStatsAdminController extends AbstractController
{
    public function __invoke(Request $request, BoussoleStats $stats): Response
    {
        $days = \in_array($request->query->getInt('jours', 30), [7, 30, 90, 365], true) ? $request->query->getInt('jours', 30) : 30;

        return $this->render('boussole/admin/stats.html.twig', ['r' => $stats->report($days)]);
    }
}
