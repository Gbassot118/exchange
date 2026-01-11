<?php

declare(strict_types=1);

namespace App\Domain\Estimation\Event;

final readonly class EstimationVoteCast
{
    public function __construct(
        public string $estimationId,
        public string $sessionId,
        public string $participantId,
        public string $participantPseudo,
        public int $voteCount,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
