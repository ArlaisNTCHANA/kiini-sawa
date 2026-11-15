<?php

namespace App\Shared\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Génération PDF (Dompdf) commune : aucune ressource externe, polices DejaVu (accents),
 * cache des polices dans var/ (et non dans vendor/, partagé entre processus).
 */
final class PdfFactory
{
    public function __construct(#[Autowire('%kernel.cache_dir%/dompdf')] private readonly string $cacheDir)
    {
    }

    public function render(string $html): string
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setFontCache($this->cacheDir);
        $options->setTempDir($this->cacheDir);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return (string) $pdf->output();
    }
}
