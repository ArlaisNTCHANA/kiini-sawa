<?php

namespace App\Academie\Twig;

use App\Academie\Service\AcademyImages;
use App\Academie\Service\AcademyOverview;
use App\Academie\Service\AcademyPlaces;
use App\Academie\Service\LessonRenderer;
use App\Academie\Service\PracticeCatalog;
use App\Academie\Service\VideoEmbed;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/** Filtres et fonctions des gabarits de l'Académie (studio et apprenant). */
final class AcademyExtension extends AbstractExtension
{
    public function __construct(private readonly LessonRenderer $renderer, private readonly AcademyImages $images)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('lecon_markdown', fn (?string $markdown): string => $this->renderer->toHtml($markdown), ['is_safe' => ['html']]),
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
            new TwigFunction('academie_vignette', $this->images->coverUrl(...)),
            new TwigFunction('academie_six_plans', AcademyOverview::plans(...)),
            new TwigFunction('academie_plans_couverts', AcademyOverview::coveredPlans(...)),
            new TwigFunction('academie_quotidien', AcademyOverview::daily(...)),
            new TwigFunction('academie_chiffres', AcademyOverview::stats(...)),
            new TwigFunction('academie_activites', AcademyOverview::lessonActivities(...)),
            new TwigFunction('academie_programme', AcademyOverview::programme(...)),
            new TwigFunction('academie_duree', AcademyOverview::duration(...)),
        ];
    }
}
