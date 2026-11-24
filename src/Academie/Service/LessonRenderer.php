<?php

namespace App\Academie\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Rend le texte d'une leçon (LessonMarkdown) avec ses schémas dessinés (gabarits `academie/schemas/<code>.html.twig`).
 * Pour l'application mobile, les adresses des images deviennent absolues.
 */
final class LessonRenderer
{
    /** Schémas disponibles : code => description (affichée dans l'aide du studio). */
    public const DIAGRAMS = [
        'equation-usine' => 'L\'équation de l\'usine : ce qui sort ÷ ce qui entre',
        'machine-vivant' => 'La machine (ligne droite) face au vivant (saisons)',
        'production-ressources' => 'Production = Productivité × Ressources (le piège)',
        'chronos-kairos' => 'Chronos (la montre) et Kairos (le moment juste)',
        'quatre-derives' => 'Les quatre dérives',
        'oscillation' => 'Le 24/7 face à l\'oscillation effort / repos',
        'saisons' => 'Les quatre saisons d\'une vie (La Méthode, chapitre 11)',
        'usine-foret' => 'Quitter l\'usine, entrer dans la forêt',
        'racine' => 'La loi de la racine : l\'invisible nourrit le visible',
        'cinq-principes' => 'Les cinq principes de la fécondité',
        'carte-navigation' => 'La carte de navigation (quand je me sens… je reviens à…)',
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function toHtml(?string $markdown, bool $absolute = false): string
    {
        $base = $absolute ? rtrim($this->urls->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL), '/') : '';

        return LessonMarkdown::toHtml($markdown, $this->diagram(...), $base);
    }

    public function diagram(string $code): ?string
    {
        if (!isset(self::DIAGRAMS[$code])) {
            return null;
        }

        return '<figure class="academie-schema academie-schema--'.$code.'">'.$this->twig->render('academie/schemas/'.$code.'.html.twig').'</figure>';
    }
}
