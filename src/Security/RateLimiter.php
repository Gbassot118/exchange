<?php

declare(strict_types=1);

namespace App\Security;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Simple rate limiter implementation using Symfony cache.
 *
 * Implements a sliding window rate limiting algorithm.
 */
class RateLimiter
{
    private const DEFAULT_LIMIT = 60;
    private const DEFAULT_WINDOW = 60; // seconds

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {}

    /**
     * Check if a request is allowed for the given identifier.
     *
     * @param string $identifier Unique identifier (e.g., IP address, user ID)
     * @param string $endpoint Endpoint being accessed
     * @param int $limit Maximum requests allowed in the window
     * @param int $windowSeconds Time window in seconds
     * @return RateLimitResult
     */
    public function check(
        string $identifier,
        string $endpoint = 'default',
        int $limit = self::DEFAULT_LIMIT,
        int $windowSeconds = self::DEFAULT_WINDOW,
    ): RateLimitResult {
        $key = $this->generateKey($identifier, $endpoint);
        $now = time();
        $windowStart = $now - $windowSeconds;

        $cacheItem = $this->cache->getItem($key);
        $requests = $cacheItem->isHit() ? $cacheItem->get() : [];

        // Remove old requests outside the window
        $requests = array_filter($requests, fn(int $timestamp) => $timestamp > $windowStart);

        $currentCount = count($requests);
        $remaining = max(0, $limit - $currentCount);
        $resetAt = $now + $windowSeconds;

        if ($currentCount >= $limit) {
            // Find when the oldest request in the window will expire
            $oldestInWindow = min($requests);
            $retryAfter = $oldestInWindow + $windowSeconds - $now;

            return new RateLimitResult(
                allowed: false,
                limit: $limit,
                remaining: 0,
                resetAt: $resetAt,
                retryAfter: max(1, $retryAfter),
            );
        }

        // Add current request
        $requests[] = $now;

        // Save to cache
        $cacheItem->set($requests);
        $cacheItem->expiresAfter($windowSeconds + 10); // Add buffer
        $this->cache->save($cacheItem);

        return new RateLimitResult(
            allowed: true,
            limit: $limit,
            remaining: $remaining - 1,
            resetAt: $resetAt,
            retryAfter: 0,
        );
    }

    /**
     * Consume a request for the given identifier (same as check but always records).
     */
    public function consume(
        string $identifier,
        string $endpoint = 'default',
        int $limit = self::DEFAULT_LIMIT,
        int $windowSeconds = self::DEFAULT_WINDOW,
    ): RateLimitResult {
        return $this->check($identifier, $endpoint, $limit, $windowSeconds);
    }

    /**
     * Reset the rate limit for an identifier.
     */
    public function reset(string $identifier, string $endpoint = 'default'): void
    {
        $key = $this->generateKey($identifier, $endpoint);
        $this->cache->deleteItem($key);
    }

    private function generateKey(string $identifier, string $endpoint): string
    {
        // Sanitize the key to be cache-safe
        $sanitized = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $identifier . '_' . $endpoint);
        return 'rate_limit_' . $sanitized;
    }
}
