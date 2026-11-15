<?php

namespace App\Shared\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Une saisie mal formée (date illisible, JSON invalide…) est une erreur
 * de l'utilisateur, pas du serveur : on répond 400 avec un message clair
 * au lieu d'une erreur 500.
 */
final class InvalidInputExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $message = $this->messageFor($event->getThrowable());
        if (null === $message) {
            return;
        }

        $request = $event->getRequest();
        $wantsJson = str_starts_with($request->getPathInfo(), '/api/')
            || 'json' === $request->getContentTypeFormat()
            || \in_array('application/json', $request->getAcceptableContentTypes(), true);

        $event->setResponse($wantsJson
            ? new JsonResponse(['success' => false, 'error' => $message], Response::HTTP_BAD_REQUEST)
            : new Response($message, Response::HTTP_BAD_REQUEST));
    }

    private function messageFor(\Throwable $e): ?string
    {
        for ($t = $e; null !== $t; $t = $t->getPrevious()) {
            if ($t instanceof \DateMalformedStringException) {
                return 'Date invalide : utilisez le format AAAA-MM-JJ.';
            }
            if ($t instanceof \JsonException) {
                return 'Données envoyées invalides.';
            }
        }

        return null;
    }
}
