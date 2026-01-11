<?php

declare(strict_types=1);

namespace App\Domain\Session\Event;

final readonly class SessionStatusChanged
{
    public function __construct(
        public string $sessionId,
        public string $previousStatus,
        public string $newStatus,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
