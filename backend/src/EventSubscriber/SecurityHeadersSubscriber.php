<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds baseline security response headers. Locally this stands in for the "WAF / hardening"
 * layer; in production Cloudflare adds/strengthens these at the edge as well.
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        // The API only ever returns JSON, so lock the content security policy right down.
        $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        // Meaningful once traffic is HTTPS (Cloudflare / DO terminate TLS in production).
        $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
