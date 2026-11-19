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
 *
 * Mode strict (« fail-closed », par défaut) : une requête SANS aucun de ces en-têtes mais qui PORTE le cookie de session est refusée
 * (403). Un navigateur ne produit jamais cela ; un script ou un outil qui rejouerait un cookie volé non plus, sans le savoir. Une requête
 * sans en-têtes et SANS cookie de session (curl sans connexion, application mobile avec jeton, tests de l'API) reste acceptée : elle ne
 * porte pas les droits d'une victime. Réglage : variable APP_SAME_ORIGIN_STRICT (1 par défaut ; la suite de tests la met à 0 sauf
 * pour les tests de cette protection).
 */
final class SameOriginRequestSubscriber implements EventSubscriberInterface
{
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(#[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(bool:APP_SAME_ORIGIN_STRICT)%')] private readonly bool $strict = true)
    {
    }

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

        // Aucun en-tête d'origine : acceptable seulement si la requête ne porte pas de session (ou de « se souvenir de moi »)
        return $this->strict && ($request->hasPreviousSession() || $request->cookies->has('REMEMBERME'));
    }
}
