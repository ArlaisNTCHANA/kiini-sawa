<?php

namespace App\Academie\Controller\Admin;

use App\Academie\Entity\Course;
use App\Academie\Service\AcademyStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin › Les chiffres › Suivi de l'Académie (27/11/2026) : par parcours, qui commence, qui avance, où l'on s'arrête.
 * Des totaux seulement (AcademyStats).
 */
#[Route('/admin/academie/suivi', name: 'admin_academy_stats')]
#[IsGranted('ROLE_ADMIN')]
class AcademyStatsAdminController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(AcademyStats $stats): Response
    {
        return $this->render('academie/admin/suivi/index.html.twig', ['rows' => $stats->overview()]);
    }

    #[Route('/{id}', name: '_course', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function course(Course $course, AcademyStats $stats): Response
    {
        return $this->render('academie/admin/suivi/course.html.twig', ['course' => $course, 'stats' => $stats->course($course)]);
    }
}
