<?php

namespace App\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Parsedown;

class DocController extends AbstractController
{

    #[Route('/admin/doc/{page}', name: 'admin_doc', requirements: ['page' => '[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)?'], defaults: ['page' => 'index'])]
    public function index(string $page): Response
    {
        // Prevent directory traversal attacks
        // Une page à la racine de docs/ ou dans un sous-dossier (ex. modules/plan_de_vie)
        if (!preg_match('#^[a-zA-Z0-9_\-]+(/[a-zA-Z0-9_\-]+)?$#', $page)) {
            throw new NotFoundHttpException('Page de documentation non valide.');
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $filePath = sprintf('%s/docs/%s.md', $projectDir, $page);

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException(sprintf('La page de documentation "%s" n\'existe pas.', $page));
        }

        $markdownContent = file_get_contents($filePath);

        // Compile Markdown to HTML using Parsedown
        $parsedown = new Parsedown();
        $htmlContent = $parsedown->text($markdownContent);

        // Rewrite relative markdown links (e.g. "api.md" -> "/admin/doc/api")
        $htmlContent = preg_replace('/href="([^#"]+)\.md(#?[^"]*)"/i', 'href="/admin/doc/$1$2"', $htmlContent);

        // Scan the docs directory to build a dynamic navigation menu
        $docsDir = $projectDir . '/docs';
        $menuItems = [];
        if (is_dir($docsDir)) {
            $files = scandir($docsDir);
            foreach ($files as $file) {
                if (str_ends_with($file, '.md')) {
                    $slug = substr($file, 0, -3);
                    $title = $this->getDocTitle($docsDir . '/' . $file, $slug);
                    $menuItems[] = [
                        'slug' => $slug,
                        'title' => $title,
                        'active' => $slug === $page,
                    ];
                }
            }
        }

        // Sort menu items so index is always first
        usort($menuItems, function ($a, $b) {
            if ($a['slug'] === 'index') return -1;
            if ($b['slug'] === 'index') return 1;
            return strcmp($a['title'], $b['title']);
        });

        $response = $this->render('admin/doc.html.twig', [
            'content' => $htmlContent,
            'menu' => $menuItems,
            'currentPage' => $page,
        ]);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        return $response;
    }

    private function getDocTitle(string $path, string $fallback): string
    {
        $f = fopen($path, 'r');
        if ($f) {
            $firstLine = fgets($f);
            fclose($f);
            if ($firstLine && str_starts_with($firstLine, '# ')) {
                return trim(substr($firstLine, 2));
            }
        }
        return ucfirst(str_replace('_', ' ', $fallback));
    }
}
