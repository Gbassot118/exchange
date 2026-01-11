<?php

declare(strict_types=1);

namespace App\Domain\Decision\Event;

final readonly class DecisionValidated
{
    public function __construct(
        public string $decisionId,
        public string $sessionId,
        public string $selectedOptionId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
