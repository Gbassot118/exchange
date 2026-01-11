<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Security\RateLimiter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies rate limiting to API endpoints.
 */
class RateLimitSubscriber implements EventSubscriberInterface
{
    /**
     * Rate limit configurations per route pattern.
     * Format: 'route_pattern' => [limit, window_seconds]
     */
    private const RATE_LIMITS = [
        // Strict limit for join endpoint (brute force protection)
        '/api/sessions/join/' => [10, 60],
        // Standard API limit
        '/api/' => [60, 60],
    ];

    public function __construct(
        private readonly RateLimiter $rateLimiter,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only apply rate limiting to API endpoints
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Get client identifier (IP address)
        $identifier = $request->getClientIp() ?? 'unknown';

        // Find applicable rate limit
        [$limit, $window] = $this->findRateLimit($path);

        // Check rate limit
        $result = $this->rateLimiter->consume($identifier, $path, $limit, $window);

        // Store result for response headers
        $request->attributes->set('_rate_limit_result', $result);

        if (!$result->allowed) {
            $response = new JsonResponse([
                'error' => 'Trop de requêtes. Veuillez réessayer plus tard.',
                'retry_after' => $result->retryAfter,
            ], Response::HTTP_TOO_MANY_REQUESTS);

            foreach ($result->getHeaders() as $name => $value) {
                $response->headers->set($name, (string) $value);
            }

            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $result = $request->attributes->get('_rate_limit_result');

        if ($result === null) {
            return;
        }

        $response = $event->getResponse();

        // Add rate limit headers to all API responses
        foreach ($result->getHeaders() as $name => $value) {
            $response->headers->set($name, (string) $value);
        }
    }

    /**
     * Find the rate limit configuration for a path.
     *
     * @return array{int, int} [limit, window_seconds]
     */
    private function findRateLimit(string $path): array
    {
        foreach (self::RATE_LIMITS as $pattern => $config) {
            if (str_starts_with($path, $pattern)) {
                return $config;
            }
        }

        // Default limit
        return [60, 60];
    }
}
