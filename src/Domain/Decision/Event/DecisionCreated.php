<?php

declare(strict_types=1);

namespace App\Domain\Decision\Event;

final readonly class DecisionCreated
{
    public function __construct(
        public string $decisionId,
        public string $sessionId,
        public string $title,
        public int $optionCount,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
