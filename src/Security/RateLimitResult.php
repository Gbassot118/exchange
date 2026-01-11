<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Result of a rate limit check.
 */
final class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $limit,
        public readonly int $remaining,
        public readonly int $resetAt,
        public readonly int $retryAfter,
    ) {}

    /**
     * Get headers to include in the response.
     *
     * @return array<string, string|int>
     */
    public function getHeaders(): array
    {
        $headers = [
            'X-RateLimit-Limit' => $this->limit,
            'X-RateLimit-Remaining' => $this->remaining,
            'X-RateLimit-Reset' => $this->resetAt,
        ];

        if (!$this->allowed) {
            $headers['Retry-After'] = $this->retryAfter;
        }

        return $headers;
    }
}
