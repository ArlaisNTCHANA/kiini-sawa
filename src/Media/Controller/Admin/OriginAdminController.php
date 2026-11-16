<?php

namespace App\Media\Controller\Admin;

use App\Media\Repository\SignupOriginRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Provenance des inscrits : quels canaux et quelles campagnes font venir des inscrits, puis des abonnés payants.
 * Comprend un générateur de liens suivis pour les réseaux sociaux.
 */
#[Route('/admin/provenance', name: 'admin_origins')]
#[IsGranted('ROLE_ADMIN')]
class OriginAdminController extends AbstractController
{
    public const PERIODS = ['7' => '7 jours', '30' => '30 jours', '90' => '90 jours', '365' => '12 mois', 'tout' => 'Depuis le début'];
    public const SOURCES = ['tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp', 'newsletter' => 'Newsletter', 'eglise' => 'Église / partenaire'];
    public const MEDIUMS = ['bio' => 'Lien de la bio', 'video' => 'Vidéo / publication', 'story' => 'Story', 'message' => 'Message', 'email' => 'Email', 'direct' => 'Direct / QR code'];

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request, SignupOriginRepository $origins): Response
    {
        $period = $request->query->getString('periode', '30');
        $period = isset(self::PERIODS[$period]) ? $period : '30';
        $since = 'tout' === $period ? null : new \DateTimeImmutable('-'.$period.' days midnight');

        $bySource = $origins->funnel('source', $since);
        $totals = ['signups' => 0, 'verified' => 0, 'onboarded' => 0, 'paying' => 0];
        foreach ($bySource as $row) {
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k];
            }
        }

        return $this->render('media/admin/origins.html.twig', [
            'period' => $period,
            'periods' => self::PERIODS,
            'bySource' => $bySource,
            'byCampaign' => $origins->funnel('campaign', $since),
            'totals' => $totals,
            'latest' => $origins->latest(),
            'sources' => self::SOURCES,
            'mediums' => self::MEDIUMS,
            'baseUrl' => $request->getSchemeAndHttpHost(),
        ]);
    }
}
