<?php

declare(strict_types=1);

namespace App\Domain\Decision\Event;

final readonly class VoteCast
{
    public function __construct(
        public string $decisionId,
        public string $sessionId,
        public string $participantPseudo,
        public string $optionId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
