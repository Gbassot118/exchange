<?php

declare(strict_types=1);

namespace App\Domain\Estimation\Event;

final readonly class EstimationRevealed
{
    public function __construct(
        public string $estimationId,
        public string $sessionId,
        public ?float $average,
        public int $voteCount,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
