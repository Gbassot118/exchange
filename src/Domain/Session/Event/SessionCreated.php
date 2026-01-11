<?php

declare(strict_types=1);

namespace App\Domain\Session\Event;

final readonly class SessionCreated
{
    public function __construct(
        public string $sessionId,
        public string $title,
        public string $creatorPseudo,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
