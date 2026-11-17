<?php

namespace App\Personnalite\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Icônes et logo des rapports, en images SVG/PNG « data: » : rendu identique à l'écran et dans le PDF (Dompdf),
 * sans dépendre des caractères disponibles dans les polices.
 */
final class ReportVisualsExtension extends AbstractExtension
{
    /** Tracés simples (grille 24×24, trait), dessinés pour KIINI SAWA. */
    private const ICONS = [
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'alert' => '<path d="M12 3.5L2.8 19.5h18.4z"/><path d="M12 9.5v4.5"/><path d="M12 16.8v.4"/>',
        'work' => '<rect x="3" y="7.5" width="18" height="12" rx="2"/><path d="M8.5 7.5V5.5a1.5 1.5 0 0 1 1.5-1.5h4a1.5 1.5 0 0 1 1.5 1.5v2"/><path d="M3 12.5h18"/>',
        'heart' => '<path d="M12 20s-7.5-4.6-7.5-10A4.2 4.2 0 0 1 12 7.2 4.2 4.2 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/>',
        'cross' => '<path d="M12 3v18"/><path d="M6.5 8.5h11"/>',
        'star' => '<path d="M12 3.5l2.6 5.5 6 .8-4.4 4.1 1.1 6-5.3-2.9-5.3 2.9 1.1-6-4.4-4.1 6-.8z"/>',
        'book' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H11v15H5.5A1.5 1.5 0 0 0 4 20.5z"/><path d="M20 5.5A1.5 1.5 0 0 0 18.5 4H13v15h5.5a1.5 1.5 0 0 1 1.5 1.5z"/>',
        'target' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        'flame' => '<path d="M12 21c-3.9 0-6.5-2.6-6.5-6.2C5.5 10 12 7 11 3c3 1.6 7.5 5.6 7.5 11.8 0 3.6-2.6 6.2-6.5 6.2z"/>',
        'up' => '<path d="M7 17L17 7"/><path d="M9 7h8v8"/>',
        'down' => '<path d="M7 7l10 10"/><path d="M17 9v8H9"/>',
        'box' => '<rect x="4" y="4" width="16" height="16" rx="3"/>',
        'dot' => '<circle cx="12" cy="12" r="6" fill="currentColor"/>',
        'ring' => '<circle cx="12" cy="12" r="6"/>',
        'leaf' => '<path d="M5 19C5 10 10 5 20 4c0 9-5 15-14 15"/><path d="M5 19l8-8"/>',
        'compass' => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>',
        'quote' => '<path d="M5 18c2.5-1 4-3 4-6V7H4v5h3"/><path d="M15 18c2.5-1 4-3 4-6V7h-5v5h3"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
    ];

    private ?string $logo = null;

    public function __construct(#[Autowire('%kernel.project_dir%/public/icons/icon-192.png')] private readonly string $logoPath)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('report_icon', $this->icon(...)),
            new TwigFunction('brand_logo', $this->logo(...)),
        ];
    }

    public function icon(string $name, string $color = '#0F172A', int $size = 15): Markup
    {
        $paths = str_replace('currentColor', $color, self::ICONS[$name] ?? self::ICONS['dot']);
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='$color' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>".str_replace('"', "'", $paths).'</svg>';

        return new Markup(sprintf('<img src="data:image/svg+xml;base64,%s" alt="" width="%d" height="%d" style="width: %dpx; height: %dpx; vertical-align: middle; margin-right: 5px;">', base64_encode($svg), $size, $size, $size, $size), 'UTF-8');
    }

    public function logo(): string
    {
        return $this->logo ??= is_file($this->logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($this->logoPath)) : '';
    }
}
