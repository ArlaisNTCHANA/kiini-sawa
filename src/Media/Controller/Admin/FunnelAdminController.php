<?php

namespace App\Media\Controller\Admin;

use App\Media\Service\FunnelReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Entonnoir d'acquisition : où l'on perd les gens entre la vitrine et le premier mois d'usage. */
#[Route('/admin/entonnoir', name: 'admin_funnel')]
#[IsGranted('ROLE_ADMIN')]
class FunnelAdminController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request, FunnelReport $report): Response
    {
        $days = \in_array($days = $request->query->getInt('jours', 30), [7, 30, 90], true) ? $days : 30;

        return $this->render('media/admin/funnel.html.twig', $report->funnel($days, new \DateTimeImmutable('today')));
    }
}
