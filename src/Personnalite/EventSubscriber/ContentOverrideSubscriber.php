<?php

namespace App\Personnalite\EventSubscriber;

use App\Personnalite\Psychometrie\Content;
use App\Personnalite\Repository\ContentOverrideRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Branche les textes modifiés dans l'administration (chargés seulement si une page en a besoin).
 */
final class ContentOverrideSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ContentOverrideRepository $overrides)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 100]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            Content::setLoader(fn () => $this->overrides->map());
        }
    }
}
