<?php

namespace App\Academie\Controller;

use App\Academie\Service\AcademyImages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Images des leçons (envoyées dans le studio). Accessibles sans connexion (route publique dans security.yaml) : ce sont
 * des illustrations, au nom aléatoire, et l'application mobile doit pouvoir les afficher.
 */
class AcademyImageController extends AbstractController
{
    #[Route('/academie/images/{course}/{name}.webp', name: 'app_academie_image', requirements: ['course' => '\d+', 'name' => '[a-f0-9]{16}'], methods: ['GET'])]
    public function show(int $course, string $name, AcademyImages $images): Response
    {
        $path = $images->file($course, $name) ?? throw $this->createNotFoundException();
        $response = new BinaryFileResponse($path, 200, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff']);
        $response->setPublic();
        $response->setMaxAge(30 * 86400);
        $response->setImmutable();

        return $response;
    }
}
