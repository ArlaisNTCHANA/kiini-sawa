<?php

namespace App\PlanDeVie\Service;

use App\Compte\Entity\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Export PDF d'un plan (gabarits simplifiés dans templates/plan_de_vie/pdf/, adaptés à Dompdf).
 */
final class PlanPdfRenderer
{
    public const TEMPLATES = [
        'VIE' => 'plan_de_vie/pdf/plan_de_vie.html.twig',
        'CROISSANCE' => 'plan_de_vie/pdf/plan_de_croissance.html.twig',
    ];

    public function __construct(private readonly Environment $twig, private readonly PlanDataProvider $data)
    {
    }

    public function render(User $user, string $planKey): string
    {
        $data = 'VIE' === $planKey ? $this->data->getLifePlanData($user) : $this->data->getStrategiesData($user);
        $html = $this->twig->render(self::TEMPLATES[$planKey], $data + [
            'title' => PlanCatalog::label($planKey),
            'name' => $user->getDisplayName(),
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(false); // aucune ressource externe chargée
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return (string) $pdf->output();
    }

    public static function filename(string $planKey): string
    {
        return 'kiini-sawa-'.strtolower(str_replace([' ', '\''], '-', iconv('UTF-8', 'ASCII//TRANSLIT', PlanCatalog::label($planKey)))).'-'.date('Y-m-d').'.pdf';
    }
}
