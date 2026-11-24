<?php

namespace App\Academie\Service;

use App\Academie\Entity\Lesson;

/**
 * Vidéos des leçons : seuls YouTube et Vimeo sont acceptés. On ne garde que l'identifiant de la vidéo ; l'adresse du
 * lecteur est reconstruite ici (YouTube sans cookie publicitaire avant lecture : youtube-nocookie.com ; Vimeo sans
 * suivi : dnt=1). Le lecteur n'est chargé qu'au clic de l'apprenant (gabarit academie/_video.html.twig), et la CSP
 * (Shared/EventSubscriber/SecurityHeadersSubscriber) n'autorise que ces deux lecteurs.
 */
final class VideoEmbed
{
    /**
     * Lit un lien collé par l'auteur. Renvoie [fournisseur, identifiant] ou null si le lien n'est pas reconnu.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ('' === $url) {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^(www\.|m\.)/', '', $host) ?? $host;
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (\in_array($host, ['youtube.com', 'youtube-nocookie.com', 'youtu.be'], true)) {
            $id = match (true) {
                'youtu.be' === $host => ltrim($path, '/'),
                '/watch' === $path => (string) ($query['v'] ?? ''),
                default => preg_match('#^/(?:embed|shorts|live|v)/([^/?]+)#', $path, $m) ? $m[1] : '',
            };

            return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? [Lesson::MEDIA_YOUTUBE, $id] : null;
        }

        if (\in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            if (preg_match('#^/(?:video/)?(\d{6,12})(?:/([0-9a-f]{6,20}))?/?$#', $path, $m)) {
                $hash = $m[2] ?? (isset($query['h']) && \is_string($query['h']) && preg_match('/^[0-9a-f]{6,20}$/', $query['h']) ? $query['h'] : '');

                return [Lesson::MEDIA_VIMEO, $m[1].('' !== $hash ? '/'.$hash : '')];
            }
        }

        return null;
    }

    /** Adresse du lecteur intégré, ou null si l'identifiant enregistré n'est pas valide. */
    public static function embedUrl(string $provider, ?string $ref): ?string
    {
        if (Lesson::MEDIA_YOUTUBE === $provider && null !== $ref && preg_match('/^[A-Za-z0-9_-]{11}$/', $ref)) {
            return 'https://www.youtube-nocookie.com/embed/'.$ref.'?rel=0&autoplay=1';
        }
        if (Lesson::MEDIA_VIMEO === $provider && null !== $ref && preg_match('#^(\d{6,12})(?:/([0-9a-f]{6,20}))?$#', $ref, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1].'?dnt=1&autoplay=1'.(isset($m[2]) ? '&h='.$m[2] : '');
        }

        return null;
    }

    /** Lien lisible pour l'auteur (champ du formulaire). */
    public static function watchUrl(string $provider, ?string $ref): string
    {
        return match ($provider) {
            Lesson::MEDIA_YOUTUBE => 'https://www.youtube.com/watch?v='.$ref,
            Lesson::MEDIA_VIMEO => 'https://vimeo.com/'.$ref,
            default => '',
        };
    }

    public static function providerLabel(string $provider): string
    {
        return [Lesson::MEDIA_YOUTUBE => 'YouTube', Lesson::MEDIA_VIMEO => 'Vimeo'][$provider] ?? '';
    }
}
