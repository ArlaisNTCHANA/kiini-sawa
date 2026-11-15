<?php

namespace App\Methode\Twig;

use App\Methode\Service\BookLibrary;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * {{ book_panel('wizard.1') }} : encart « Comprendre cette étape » relié au chapitre du livre.
 * N'affiche rien si l'écran n'a pas de chapitre associé.
 */
final class BookExtension extends AbstractExtension
{
    public function __construct(private readonly BookLibrary $book)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('book_panel', $this->panel(...), ['is_safe' => ['html'], 'needs_environment' => true])];
    }

    public function panel(Environment $twig, string $step, bool $open = false): string
    {
        $chapter = $this->book->forStep($step);

        return $chapter ? $twig->render('methode/_panel.html.twig', ['chapter' => $chapter, 'open' => $open]) : '';
    }
}
