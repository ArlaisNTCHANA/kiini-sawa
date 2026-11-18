<?php

namespace App\Contenu\EventSubscriber;

use App\Contenu\Service\SiteTexts;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** En mode aperçu, un bandeau rappelle à l'administrateur qu'il voit les brouillons (et permet d'en sortir). */
final class PreviewBannerSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SiteTexts $texts)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -10]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!$event->isMainRequest() || !$request->hasSession() || !$request->getSession()->has(SiteTexts::PREVIEW_KEY) || $request->isXmlHttpRequest()
            || !str_contains((string) $response->headers->get('Content-Type'), 'text/html') || str_starts_with($request->getPathInfo(), '/admin') || !$this->texts->isPreview()) {
            return;
        }
        $content = (string) $response->getContent();
        if (!str_contains($content, '</body>')) {
            return;
        }
        $banner = '<div role="status" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:#92400E;color:#fff;font:600 13px/1.4 system-ui,sans-serif;padding:6px 12px;text-align:center;">'
            .'Aperçu des brouillons (visible par vous seul) · <a href="/admin/textes" style="color:#fff;text-decoration:underline;">Retour aux textes</a></div>';
        $response->setContent(str_replace('</body>', $banner.'</body>', $content));
    }
}
