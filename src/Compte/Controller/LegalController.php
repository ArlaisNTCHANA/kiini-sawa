<?php

namespace App\Compte\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages légales publiques. Le contenu est dans templates/legal/<page>.html.twig.
 * Les mentions entre crochets [À COMPLÉTER] doivent être remplies avant la mise en ligne.
 */
class LegalController extends AbstractController
{
    public const PAGES = [
        'mentions-legales' => 'Mentions légales',
        'confidentialite' => 'Politique de confidentialité',
        'cgu' => 'Conditions générales d\'utilisation',
        'cgv' => 'Conditions générales de vente',
    ];

    /** Date de dernière mise à jour affichée sur chaque page. */
    public const UPDATED_AT = '15 novembre 2026';

    #[Route('/{page}', name: 'app_legal', requirements: ['page' => 'mentions-legales|confidentialite|cgu|cgv'], methods: ['GET'], priority: -10)]
    public function show(string $page): Response
    {
        return $this->render('legal/'.$page.'.html.twig', [
            'page' => $page,
            'title' => self::PAGES[$page],
            'pages' => self::PAGES,
            'updatedAt' => self::UPDATED_AT,
        ]);
    }
}
