<?php

namespace App\Media\EventSubscriber;

use App\Media\Service\OriginTracker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Retient la provenance des visiteurs non connectés (pages HTML uniquement). */
final class OriginCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly OriginTracker $tracker, private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('GET') || null !== $this->security->getUser()) {
            return;
        }
        if (!str_contains((string) $event->getResponse()->headers->get('Content-Type', 'text/html'), 'text/html')) {
            return;
        }
        $this->tracker->remember($request, $event->getResponse());
    }
}
