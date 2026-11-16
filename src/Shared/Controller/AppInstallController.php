<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Application installable (PWA) : page d'installation sur téléphone et page affichée hors connexion.
 */
class AppInstallController extends AbstractController
{
    #[Route('/installer', name: 'app_install', methods: ['GET'])]
    public function install(): Response
    {
        return $this->render('shared/install.html.twig');
    }

    /** Mise en cache par le service worker : aucune donnée personnelle. */
    #[Route('/hors-ligne', name: 'app_offline', methods: ['GET'])]
    public function offline(): Response
    {
        return $this->render('shared/offline.html.twig');
    }
}
