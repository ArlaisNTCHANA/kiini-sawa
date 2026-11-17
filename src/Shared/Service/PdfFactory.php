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
    /** Polices de la marque (statiques, OFL) : famille => [graisse/style => fichier]. */
    private const FONTS = [
        'Inter' => [['normal', 'normal', 'Inter-Regular.ttf'], ['bold', 'normal', 'Inter-Bold.ttf']],
        'Playfair Display' => [['bold', 'normal', 'PlayfairDisplay-Bold.ttf'], ['normal', 'normal', 'PlayfairDisplay-Bold.ttf'], ['normal', 'italic', 'PlayfairDisplay-Italic.ttf'], ['bold', 'italic', 'PlayfairDisplay-Italic.ttf']],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/dompdf')] private readonly string $cacheDir,
        #[Autowire('%kernel.project_dir%/resources/fonts/pdf')] private readonly string $fontDir,
    ) {
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
        $options->setChroot([$this->fontDir, $this->cacheDir]);
        $pdf = new Dompdf($options);
        $this->registerFonts($pdf);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return (string) $pdf->output();
    }

    /** Inter et Playfair Display (une seule fois : Dompdf garde les métriques dans le cache). */
    private function registerFonts(Dompdf $pdf): void
    {
        $metrics = $pdf->getFontMetrics();
        foreach (self::FONTS as $family => $variants) {
            foreach ($variants as [$weight, $style, $file]) {
                $key = strtolower(str_replace(' ', '_', $family)).'_'.$weight.'_'.$style;
                if (null !== $metrics->getFont($family, ('bold' === $weight ? 'bold' : 'normal').('italic' === $style ? '_italic' : '')) && is_file($this->cacheDir.'/installed-'.$key)) {
                    continue;
                }
                if (is_file($this->fontDir.'/'.$file) && $metrics->registerFont(['family' => $family, 'weight' => $weight, 'style' => $style], $this->fontDir.'/'.$file)) {
                    @touch($this->cacheDir.'/installed-'.$key);
                }
            }
        }
    }
}
