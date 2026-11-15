<?php

namespace App\Shared\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Protection CSRF globale : toute requête qui modifie des données
 * (POST, PUT, PATCH, DELETE) doit provenir du site lui-même.
 *
 * Les navigateurs envoient toujours l'en-tête Origin (ou Sec-Fetch-Site) sur
 * ces requêtes ; un site tiers ne peut ni les retirer ni les falsifier.
 * Une requête sans aucun de ces en-têtes ne vient pas d'un navigateur
 * (tests, curl, application mobile) : elle ne porte donc pas les cookies
 * d'une victime et est acceptée.
 */
final class SameOriginRequestSubscriber implements EventSubscriberInterface
{
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public static function getSubscribedEvents(): array
    {
        // Avant le pare-feu de sécurité (priorité 8)
        return [KernelEvents::REQUEST => ['onKernelRequest', 16]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!\in_array($request->getMethod(), self::UNSAFE_METHODS, true)) {
            return;
        }

        if ($this->isCrossSite($request)) {
            $message = 'Requête refusée : elle ne provient pas de KIINI SAWA.';
            $event->setResponse(
                str_starts_with($request->getPathInfo(), '/api/')
                    ? new JsonResponse(['error' => $message], Response::HTTP_FORBIDDEN)
                    : new Response($message, Response::HTTP_FORBIDDEN)
            );
        }
    }

    private function isCrossSite(Request $request): bool
    {
        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        if (null !== $fetchSite) {
            // "none" = saisie directe dans la barre d'adresse ou favori
            return !\in_array($fetchSite, ['same-origin', 'none'], true);
        }

        $origin = $request->headers->get('Origin');
        if (null !== $origin) {
            return $origin !== $request->getSchemeAndHttpHost();
        }

        $referer = $request->headers->get('Referer');
        if (null !== $referer) {
            return !str_starts_with($referer, $request->getSchemeAndHttpHost().'/');
        }

        return false;
    }
}
