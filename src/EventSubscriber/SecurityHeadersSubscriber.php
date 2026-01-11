<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security headers to all HTTP responses.
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;

        // Prevent clickjacking attacks
        $headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS filter in browsers (legacy, but still useful)
        $headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy - don't leak referrer to external sites
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy - restrict browser features
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Content Security Policy - restrict resource loading
        // Note: Adjusted to allow CDN resources used by the application (Tailwind, HTMX)
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' wss: https:",  // For WebSocket/Mercure connections
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $headers->set('Content-Security-Policy', $csp);

        // For HTTPS environments, enable HSTS
        // Uncomment in production with proper HTTPS setup
        // $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
