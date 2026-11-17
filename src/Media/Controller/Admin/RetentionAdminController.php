<?php

namespace App\Media\Controller\Admin;

use App\Media\Controller\Admin\OriginAdminController;
use App\Media\Service\RetentionReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/retention', name: 'admin_retention')]
#[IsGranted('ROLE_ADMIN')]
class RetentionAdminController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(RetentionReport $report): Response
    {
        $today = new \DateTimeImmutable('now');

        return $this->render('media/admin/retention.html.twig', [
            'headline' => $report->headline($today),
            'weeks' => $report->byWeek($today),
            'channels' => $report->byChannel($today),
            'horizons' => RetentionReport::HORIZONS,
            'sources' => OriginAdminController::SOURCES,
        ]);
    }
}
