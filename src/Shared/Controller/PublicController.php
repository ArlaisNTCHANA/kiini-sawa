<?php

namespace App\Shared\Controller;

use App\Methode\Service\BookLibrary;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Pages techniques publiques : santé du service (surveillance), robots.txt, sitemap.xml.
 */
class PublicController extends AbstractController
{
    /** Pour l'hébergeur ou un service de surveillance : 200 si l'application et la base répondent. */
    #[Route('/sante', name: 'app_health', methods: ['GET'])]
    public function health(Connection $db): JsonResponse
    {
        try {
            $db->executeQuery('SELECT 1');
            $ok = true;
        } catch (\Throwable) {
            $ok = false;
        }

        return new JsonResponse(['status' => $ok ? 'ok' : 'erreur', 'base' => $ok], $ok ? 200 : 503, ['Cache-Control' => 'no-store']);
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $public = ['/', '/inscription', '/methode', '/mentions-legales', '/confidentialite', '/cgu', '/cgv'];
        $lines = ['User-agent: *'];
        foreach ($public as $p) {
            $lines[] = 'Allow: '.$p.('/' === $p ? '$' : '');
        }
        $lines[] = 'Disallow: /';
        $lines[] = '';
        $lines[] = 'Sitemap: '.$this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new Response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $urls = [['app_home', [], '1.0'], ['app_register', [], '0.8'], ['app_methode_index', [], '0.7']];
        foreach (\array_slice(array_values(BookLibrary::CHAPTERS), 0, BookLibrary::PUBLIC_CHAPTERS) as $slug) {
            $urls[] = ['app_methode_chapter', ['slug' => $slug], '0.6'];
        }
        foreach (['mentions-legales', 'confidentialite', 'cgu', 'cgv'] as $page) {
            $urls[] = ['app_legal', ['page' => $page], '0.3'];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as [$route, $params, $priority]) {
            $xml .= '  <url><loc>'.htmlspecialchars($this->generateUrl($route, $params, UrlGeneratorInterface::ABSOLUTE_URL)).'</loc><priority>'.$priority.'</priority></url>'."\n";
        }

        return new Response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
