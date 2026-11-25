<?php

namespace App\Academie\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Images des parcours, envoyées dans le studio : photo ou illustration (JPEG, PNG, WebP, 10 Mo au plus).
 *
 * Chaque image est **réenregistrée** (redimensionnée à 1600 pixels de large au plus, en WebP) : les métadonnées et tout
 * contenu caché disparaissent. Rangées dans `var/academie-images/<parcours>/` (hors du dossier public, que le déploiement
 * remplace), servies par `/academie/images/<parcours>/<nom>.webp`. Le nom est aléatoire.
 */
final class AcademyImages
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_WIDTH = 1600;
    private const PATH = '#^/academie/images/(\d+)/([a-f0-9]{16})\.webp$#';

    public function __construct(#[Autowire('%kernel.project_dir%/var/academie-images')] private readonly string $dir)
    {
    }

    /** Adresse publique d'une image du studio ? (le texte des leçons n'affiche que celles-là) */
    public static function isPublicPath(string $path): bool
    {
        return 1 === preg_match(self::PATH, $path);
    }

    public static function publicPath(int $courseId, string $name): string
    {
        return '/academie/images/'.$courseId.'/'.$name.'.webp';
    }

    /**
     * Enregistre l'image envoyée.
     *
     * @return string nom de l'image (16 caractères)
     *
     * @throws \InvalidArgumentException message prêt à afficher
     */
    public function store(int $courseId, UploadedFile $file): string
    {
        if (!$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Image trop lourde ou envoi interrompu : 10 Mo au plus.');
        }
        $info = @getimagesize($file->getPathname());
        $type = $info[2] ?? null;
        $source = match ($type) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getPathname()),
            \IMAGETYPE_PNG => @imagecreatefrompng($file->getPathname()),
            \IMAGETYPE_WEBP => @imagecreatefromwebp($file->getPathname()),
            default => false,
        };
        if (false === $source) {
            throw new \InvalidArgumentException('Ce fichier n\'est pas une image JPEG, PNG ou WebP lisible.');
        }
        $width = imagesx($source);
        $height = imagesy($source);
        if ($width > self::MAX_WIDTH) {
            $resized = imagescale($source, self::MAX_WIDTH, (int) round($height * self::MAX_WIDTH / $width));
            $source = $resized;
        }
        if (false === $source) {
            throw new \InvalidArgumentException('Cette image n\'a pas pu être préparée.');
        }
        imagepalettetotruecolor($source);
        imagealphablending($source, true);
        imagesavealpha($source, true);

        $folder = $this->dir.'/'.$courseId;
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new \RuntimeException('Dossier des images impossible à créer.');
        }
        $name = bin2hex(random_bytes(8));
        $ok = imagewebp($source, $folder.'/'.$name.'.webp', 82);
        if (!$ok) {
            throw new \InvalidArgumentException('Cette image n\'a pas pu être enregistrée.');
        }

        return $name;
    }

    /** @return list<array{name: string, path: string, size: int, date: int}> du plus récent au plus ancien */
    public function list(int $courseId): array
    {
        $files = glob($this->dir.'/'.$courseId.'/*.webp') ?: [];
        $out = [];
        foreach ($files as $f) {
            $name = basename($f, '.webp');
            if (1 === preg_match('/^[a-f0-9]{16}$/', $name)) {
                $out[] = ['name' => $name, 'path' => self::publicPath($courseId, $name), 'size' => (int) filesize($f), 'date' => (int) filemtime($f)];
            }
        }
        usort($out, fn ($a, $b) => $b['date'] <=> $a['date']);

        return $out;
    }

    /** Chemin du fichier sur le disque, ou null s'il n'existe pas (nom vérifié : aucune sortie du dossier possible). */
    public function file(int $courseId, string $name): ?string
    {
        if (1 !== preg_match('/^[a-f0-9]{16}$/', $name)) {
            return null;
        }
        $path = $this->dir.'/'.$courseId.'/'.$name.'.webp';

        return is_file($path) ? $path : null;
    }

    /** Adresse de la vignette d'un parcours si son image existe encore, sinon null (vignette dessinée). */
    public function coverUrl(\App\Academie\Entity\Course $course): ?string
    {
        $name = $course->getCoverImage();
        if (null === $name || null === $course->getId() || null === $this->file($course->getId(), $name)) {
            return null;
        }

        return self::publicPath($course->getId(), $name);
    }

    public function delete(int $courseId, string $name): bool
    {
        $path = $this->file($courseId, $name);

        return null !== $path && unlink($path);
    }

    /** Supprime toutes les images d'un parcours (parcours supprimé). */
    public function deleteAll(int $courseId): void
    {
        foreach ($this->list($courseId) as $image) {
            $this->delete($courseId, $image['name']);
        }
        @rmdir($this->dir.'/'.$courseId);
    }
}
