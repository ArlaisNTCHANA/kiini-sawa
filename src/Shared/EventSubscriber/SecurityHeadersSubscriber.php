<?php

namespace App\Shared\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En-têtes de sécurité ajoutés à toutes les réponses.
 *
 * La CSP limite les scripts, styles et polices à l'application elle-même :
 * polices et bibliothèques sont auto-hébergées (aucun appel à Google ou à un CDN, RGPD). 'unsafe-inline' et 'unsafe-eval'
 * restent requis tant que les templates contiennent des scripts inline et
 * qu'Alpine.js est utilisé dans sa version standard. À durcir quand le JS
 * sera sorti dans des contrôleurs Stimulus (voir docs/plan-v1.md, phase 3).
 *
 * Toute nouvelle origine externe doit être ajoutée ici.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    private const CSP = [
        "default-src 'self'",
        "script-src 'self' data: 'unsafe-inline' 'unsafe-eval'", // data: = modules CSS vides générés par AssetMapper
        "style-src 'self' 'unsafe-inline'",
        "font-src 'self' data:",
        "img-src 'self' data: blob: https:",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
    ];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        // Ne pas révéler la version de PHP (en-tête ajouté par PHP lui-même, hors de l'objet Response)
        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        // HTTPS imposé au navigateur pendant un an, uniquement quand la page est déjà servie en HTTPS
        if ($event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Le profiler Symfony (dev) gère sa propre page : on ne la contraint pas
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/_profiler')) {
            $headers->set('Content-Security-Policy', implode('; ', self::CSP));
        }
    }
}
