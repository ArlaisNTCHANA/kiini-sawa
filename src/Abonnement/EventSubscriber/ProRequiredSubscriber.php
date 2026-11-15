<?php

namespace App\Abonnement\EventSubscriber;

use App\Abonnement\Exception\ProRequiredException;
use App\Abonnement\Service\Entitlements;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProRequiredSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 20]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        if (!$e instanceof ProRequiredException) {
            return;
        }
        $request = $event->getRequest();
        $isLimit = isset(Entitlements::LIMITS[$e->feature]);
        $upgradeUrl = $this->urls->generate('app_subscription', $isLimit ? ['limite' => $e->feature] : ['fonction' => $e->feature]);

        $event->setResponse(str_starts_with($request->getPathInfo(), '/api/') || 'json' === $request->getContentTypeFormat()
            ? new JsonResponse(['success' => false, 'error' => $e->getMessage(), 'upgradeUrl' => $upgradeUrl], 402)
            : new RedirectResponse($upgradeUrl));
    }
}
