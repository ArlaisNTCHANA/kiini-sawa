<?php

namespace App\Academie\Twig;

use App\Academie\Service\AcademyPlaces;
use App\Academie\Service\LessonMarkdown;
use App\Academie\Service\PracticeCatalog;
use App\Academie\Service\VideoEmbed;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/** Filtres et fonctions des gabarits de l'Académie (studio et apprenant). */
final class AcademyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('lecon_markdown', LessonMarkdown::toHtml(...), ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('academie_lieu', AcademyPlaces::label(...)),
            new TwigFunction('academie_plans', AcademyPlaces::plans(...)),
            new TwigFunction('academie_apparence', AcademyPlaces::look(...)),
            new TwigFunction('academie_exercice', PracticeCatalog::label(...)),
            new TwigFunction('academie_bouton', PracticeCatalog::button(...)),
            new TwigFunction('academie_video', VideoEmbed::embedUrl(...)),
            new TwigFunction('academie_video_source', VideoEmbed::providerLabel(...)),
        ];
    }
}
